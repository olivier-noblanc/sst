<?php

/** RegistryFieldRepository — Couche d'accès aux données pour les fields custom par registre. */

namespace App\Repository;

use App\DTO\CreateRegistryFieldCommand;
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

    /** @return list<array{id: int, registry_id: int, field_code: string, label: string, field_type: string, options: ?string, is_required: int, sort_order: int, created_at: string}> */
    public function findByRegistry(int $registryId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM registry_fields WHERE registry_id = :id ORDER BY sort_order ASC, field_code ASC');
        $stmt->execute([':id' => $registryId]);
        /** @var list<array{id: int, registry_id: int, field_code: string, label: string, field_type: string, options: ?string, is_required: int, sort_order: int, created_at: string}> $rows */
        $rows = $stmt->fetchAll();
        return $rows;
    }

    /** @return array{id: int, registry_id: int, field_code: string, label: string, field_type: string, options: ?string, is_required: int, sort_order: int, created_at: string}|null */
    public function findByCode(int $registryId, string $fieldCode): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM registry_fields WHERE registry_id = :rid AND field_code = :fc');
        $stmt->execute([':rid' => $registryId, ':fc' => $fieldCode]);
        /** @var array{id: int, registry_id: int, field_code: string, label: string, field_type: string, options: ?string, is_required: int, sort_order: int, created_at: string}|null $row */
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function create(int $registryId, CreateRegistryFieldCommand $command): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO registry_fields (registry_id, field_code, label, field_type, options, is_required, sort_order)
            VALUES (:rid, :fc, :label, :ft, :opts, :req, :so)
        ');
        $stmt->execute([
            ':rid'   => $registryId,
            ':fc'    => $command->fieldCode,
            ':label' => $command->label,
            ':ft'    => $command->fieldType,
            ':opts'  => $command->options,
            ':req'   => $command->isRequired,
            ':so'    => $command->sortOrder,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM registry_fields WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
