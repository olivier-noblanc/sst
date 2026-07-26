<?php

/** RegistryFieldRepository — Couche d'accès aux données pour les fields custom par registre. */

namespace App\Repository;

use PDO;

class RegistryFieldRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public static function instance(): self
    {
        static $instance = null;
        if ($instance === null) {
            if (function_exists('getContainer') && getContainer()->has(self::class)) {
                $instance = getContainer()->get(self::class);
            } else {
                $instance = new self(getDB());
            }
        }
        return $instance;
    }

    /** @return list<array<string, mixed>> */
    public function findByRegistry(int $registryId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM registry_fields WHERE registry_id = :id ORDER BY sort_order ASC, field_code ASC');
        $stmt->execute([':id' => $registryId]);
        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();
        return $rows;
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM registry_fields WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findByCode(int $registryId, string $fieldCode): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM registry_fields WHERE registry_id = :rid AND field_code = :fc');
        $stmt->execute([':rid' => $registryId, ':fc' => $fieldCode]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function create(int $registryId, array $data): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO registry_fields (registry_id, field_code, label, field_type, options, is_required, sort_order)
            VALUES (:rid, :fc, :label, :ft, :opts, :req, :so)
        ');
        $stmt->execute([
            ':rid'   => $registryId,
            ':fc'    => $data['field_code'],
            ':label' => $data['label'],
            ':ft'    => $data['field_type'] ?? 'text',
            ':opts'  => $data['options'] ?? null,
            ':req'   => $data['is_required'] ?? 0,
            ':so'    => $data['sort_order'] ?? 0,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        $sets = [];
        $params = [':id' => $id];
        foreach (['label', 'field_type', 'options', 'is_required', 'sort_order'] as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        if (empty($sets)) {
            return false;
        }
        $sql = 'UPDATE registry_fields SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM registry_fields WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Report field values — modular storage (Batch 1)
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Get all field values for a single report.
     * Returns {field_code => value} mapping.
     *
     * @return array<string, string>
     */
    public function findValuesForReport(string $reportUuid): array
    {
        $stmt = $this->pdo->prepare('
            SELECT rf.field_code, rfv.value
            FROM report_field_values rfv
            JOIN registry_fields rf ON rfv.field_id = rf.id
            WHERE rfv.report_uuid = :uuid
        ');
        $stmt->execute([':uuid' => $reportUuid]);
        $rows = $stmt->fetchAll();
        $result = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (is_array($row) && isset($row['field_code'])) {
                    $result[(string) $row['field_code']] = (string) ($row['value'] ?? '');
                }
            }
        }
        return $result;
    }

    /**
     * Bulk-fetch field values for multiple reports (avoids N+1 in export).
     * Returns {report_uuid => {field_code => value}} mapping.
     *
     * @param list<string> $uuids
     * @return array<string, array<string, string>>
     */
    public function findValuesForUuids(array $uuids): array
    {
        if (empty($uuids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($uuids), '?'));
        $stmt = $this->pdo->prepare("
            SELECT rfv.report_uuid, rf.field_code, rfv.value
            FROM report_field_values rfv
            JOIN registry_fields rf ON rfv.field_id = rf.id
            WHERE rfv.report_uuid IN ($placeholders)
        ");
        $stmt->execute($uuids);
        $rows = $stmt->fetchAll();
        $result = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (is_array($row) && isset($row['report_uuid'], $row['field_code'])) {
                    $uuid = (string) $row['report_uuid'];
                    $code = (string) $row['field_code'];
                    if (!isset($result[$uuid])) {
                        $result[$uuid] = [];
                    }
                    $result[$uuid][$code] = (string) ($row['value'] ?? '');
                }
            }
        }
        return $result;
    }

    /**
     * Save (upsert) field values for a report.
     * Looks up field_id from registry_id + field_code.
     *
     * @param string $reportUuid
     * @param int $registryId
     * @param array<string, ?string> $values  {field_code => value}
     */
    public function saveValues(string $reportUuid, int $registryId, array $values): void
    {
        if (empty($values)) {
            return;
        }
        foreach ($values as $fieldCode => $value) {
            $field = $this->findByCode($registryId, $fieldCode);
            if ($field === null) {
                continue; // Unknown field — skip
            }
            $fieldId = (int) $field['id'];
            $stmt = $this->pdo->prepare('
                INSERT INTO report_field_values (report_uuid, field_id, value)
                VALUES (:uuid, :fid, :val)
                ON CONFLICT(report_uuid, field_id) DO UPDATE SET
                    value = :val2, updated_at = datetime(\'now\')
            ');
            $stmt->execute([
                ':uuid' => $reportUuid,
                ':fid' => $fieldId,
                ':val' => $value,
                ':val2' => $value,
            ]);
        }
    }

    /**
     * Get structured stats for a registry's select-type fields.
     * Returns [{field_code, value, count}] for grouping/aggregation.
     *
     * @return list<array{field_code: string, value: string, count: int}>
     */
    public function findStructuredStatsForRegistry(int $registryId, string $yearFilter = ''): array
    {
        $params = [':rid' => $registryId];
        $yearSql = '';
        if (!empty($yearFilter)) {
            $yearSql = ' AND r.created_at >= :year_start AND r.created_at < :year_next';
            $params[':year_start'] = $yearFilter . '-01-01 00:00:00';
            $params[':year_next'] = ((int) $yearFilter + 1) . '-01-01 00:00:00';
        }
        $stmt = $this->pdo->prepare("
            SELECT rf.field_code, rfv.value, COUNT(*) as count
            FROM report_field_values rfv
            JOIN registry_fields rf ON rfv.field_id = rf.id
            JOIN reports r ON rfv.report_uuid = r.uuid
            WHERE rf.registry_id = :rid
              AND rf.field_type = 'select'
              AND rfv.value IS NOT NULL AND rfv.value != ''
              $yearSql
            GROUP BY rf.field_code, rfv.value
            ORDER BY rf.field_code, count DESC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $result = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $result[] = [
                    'field_code' => (string) $row['field_code'],
                    'value' => (string) $row['value'],
                    'count' => (int) $row['count'],
                ];
            }
        }
        return $result;
    }
}
