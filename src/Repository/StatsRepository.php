<?php

/** StatsRepository — Couche d'accès aux données pour les statistiques et exports. */

namespace App\Repository;

use App\Enum\ReportType;
use App\DTO\IndicateursData;
use App\DTO\RamiStats;
use App\DTO\SiteStatsRow;
use App\DTO\SynthesisRow;
use App\Enum\ReportState;
use PDO;

class StatsRepository
{
    public const EXPORT_MAX_ROWS = 50000;

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

    /** @return list<SynthesisRow> */
    public function getSynthesis(string $year, int $siteId = 0): array
    {
        $stateColumns = [];
        foreach (ReportState::cases() as $state) {
            $stateColumns[] = 'SUM(CASE WHEN r.etat = ' . $this->pdo->quote($state->value) . ' THEN 1 ELSE 0 END) as ' . $state->value;
        }
        $stateColumnsSql = implode(",\n                ", $stateColumns);

        $sql = "
            SELECT s.id as site_id, s.code, s.nom,
                r.type,
                {$stateColumnsSql},
                COUNT(r.uuid) as total
            FROM sites s
            LEFT JOIN reports r ON r.site_id = s.id
                AND r.created_at >= :year_start AND r.created_at < :year_next
        ";

        $params = [':year_start' => $year . '-01-01 00:00:00', ':year_next' => ((int) $year + 1) . '-01-01 00:00:00'];

        if ($siteId > 0) {
            $sql .= ' AND r.site_id = :site_id';
            $params[':site_id'] = $siteId;
        }

        $sql .= ' GROUP BY s.id, r.type ORDER BY s.code, r.type';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        if (!is_array($rows)) {
            return [];
        }
        $result = [];
        foreach ($rows as $row) {
            $result[] = new SynthesisRow(
                siteId: (int) $row['site_id'],
                type: (string) $row['type'],
                nouveau: (int) ($row[ReportState::Nouveau->value] ?? 0),
                enCours: (int) ($row[ReportState::EnCours->value] ?? 0),
                traite: (int) ($row[ReportState::Traite->value] ?? 0),
                abandonne: (int) ($row[ReportState::Abandonne->value] ?? 0),
                reouvert: (int) ($row[ReportState::Reouvert->value] ?? 0),
                total: (int) $row['total'],
            );
        }
        return $result;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<mixed, mixed>
     */
    public function getExportData(array $filters = []): array
    {
        $sql = '
            SELECT r.uuid, r.reference, r.type, r.objet, r.description,
                   r.date_evenement, r.heure_evenement, r.lieu,
                   r.declarant_id, r.declarant_nom, r.declarant_prenom,
                   r.pour_compte_de, r.pour_compte_nom, r.pour_compte_prenom,
                   r.nature_auteur, r.type_acte,
                   r.site_id, r.site_text, r.pole, r.service_affectation,
                   r.telephone_mobile, r.consent_syndicat,
                   r.is_confidential, r.etat,
                   r.repondant_id, r.date_reponse, r.reponse,
                   r.attachment_name, r.attachment_mime,
                   r.created_at, r.updated_at,
                   s.code as site_code, s.nom as site_nom,
                   rep.nom as repondant_nom, rep.prenom as repondant_prenom
            FROM reports r
            LEFT JOIN sites s ON r.site_id = s.id
            LEFT JOIN users rep ON r.repondant_id = rep.id
            WHERE 1=1
        ';
        $params = [];

        if (!empty($filters['type'])) {
            $sql .= ' AND r.type = :type';
            $params[':type'] = $filters['type'];
        }

        if (!empty($filters['site_id'])) {
            $sql .= ' AND r.site_id = :site_id';
            $params[':site_id'] = $filters['site_id'];
        }

        if (!empty($filters['declarant_id'])) {
            $sql .= ' AND r.declarant_id = :declarant_id';
            $params[':declarant_id'] = $filters['declarant_id'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= ' AND r.date_evenement >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= ' AND r.date_evenement <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }

        if (!empty($filters['etats']) && is_array($filters['etats'])) {
            $placeholders = [];
            foreach ($filters['etats'] as $i => $etat) {
                $key = ':etat_' . $i;
                $placeholders[] = $key;
                $params[$key] = $etat;
            }
            $sql .= ' AND r.etat IN (' . implode(', ', $placeholders) . ')';
        }

        $sql .= ' ORDER BY r.created_at DESC LIMIT ' . self::EXPORT_MAX_ROWS;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function getIndicateurs(string $year = '', int $siteId = 0): IndicateursData
    {
        $params = [];

        // Build dynamic per-registry CASE columns from active registries
        $registryRepo = RegistryRepository::instance();
        $enabledRegistries = $registryRepo->findEnabled();
        $typeColumns = [];
        $defaultRegistryTotals = [];
        foreach ($enabledRegistries as $reg) {
            $code = (string) $reg['code'];
            $safeCode = str_replace("'", "''", $code);
            $typeColumns[] = "SUM(CASE WHEN type = '{$safeCode}' THEN 1 ELSE 0 END) as total_" . str_replace('-', '_', $code);
            $defaultRegistryTotals['total_' . str_replace('-', '_', $code)] = 0;
        }
        $typeColumnsSql = !empty($typeColumns) ? ",\n                " . implode(",\n                ", $typeColumns) : '';

        $stateColumns = [];
        foreach (ReportState::cases() as $state) {
            $stateColumns[] = 'SUM(CASE WHEN etat = ' . $this->pdo->quote($state->value) . ' THEN 1 ELSE 0 END) as total_' . $state->value;
        }
        $stateColumnsSql = implode(",\n                ", $stateColumns);

        $sql = "
            SELECT
                COUNT(*) as total_reports,
                {$stateColumnsSql}
                {$typeColumnsSql}
            FROM reports
            WHERE 1=1
        ";

        if (!empty($year)) {
            $sql .= ' AND created_at >= :year_start AND created_at < :year_next';
            $params[':year_start'] = $year . '-01-01 00:00:00';
            $params[':year_next'] = ((int) $year + 1) . '-01-01 00:00:00';
        }

        if ($siteId > 0) {
            $sql .= ' AND site_id = :site_id';
            $params[':site_id'] = $siteId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();

        if (!is_array($result)) {
            return new IndicateursData(
                totalReports: 0,
                totalNouveau: 0,
                totalEnCours: 0,
                totalTraite: 0,
                registryTotals: $defaultRegistryTotals,
            );
        }

        $registryTotals = [];
        foreach ($enabledRegistries as $reg) {
            $key = 'total_' . str_replace('-', '_', (string) $reg['code']);
            $registryTotals[$key] = (int) ($result[$key] ?? 0);
        }

        return new IndicateursData(
            totalReports: (int) ($result['total_reports'] ?? 0),
            totalNouveau: (int) ($result['total_' . ReportState::Nouveau->value] ?? 0),
            totalEnCours: (int) ($result['total_' . ReportState::EnCours->value] ?? 0),
            totalTraite: (int) ($result['total_' . ReportState::Traite->value] ?? 0),
            registryTotals: $registryTotals,
        );
    }

    /** @return list<SiteStatsRow> */
    public function getBySite(string $year = '', int $siteId = 0): array
    {
        // Build dynamic per-registry CASE columns from active registries
        $registryRepo = RegistryRepository::instance();
        $enabledRegistries = $registryRepo->findEnabled();
        $typeColumns = [];
        foreach ($enabledRegistries as $reg) {
            $code = (string) $reg['code'];
            $safeCode = str_replace("'", "''", $code);
            $typeColumns[] = "SUM(CASE WHEN r.type = '{$safeCode}' THEN 1 ELSE 0 END) as " . str_replace('-', '_', $code);
        }
        $typeColumnsSql = !empty($typeColumns) ? ",\n                " . implode(",\n                ", $typeColumns) : '';

        $stateColumns = [];
        foreach (ReportState::cases() as $state) {
            $stateColumns[] = 'SUM(CASE WHEN r.etat = ' . $this->pdo->quote($state->value) . ' THEN 1 ELSE 0 END) as ' . $state->value;
        }
        $stateColumnsSql = implode(",\n                ", $stateColumns);

        $sql = "
            SELECT s.code, s.nom,
                COUNT(r.uuid) as total,
                {$stateColumnsSql}
                {$typeColumnsSql}
            FROM sites s
            LEFT JOIN reports r ON r.site_id = s.id
        ";

        $params = [];
        $where = [];

        if (!empty($year)) {
            $where[] = 'r.created_at >= :year_start AND r.created_at < :year_next';
            $params[':year_start'] = $year . '-01-01 00:00:00';
            $params[':year_next'] = ((int) $year + 1) . '-01-01 00:00:00';
        }

        if ($siteId > 0) {
            $where[] = 'r.site_id = :site_id';
            $params[':site_id'] = $siteId;
        }

        if (!empty($where)) {
            $sql .= ' AND ' . implode(' AND ', $where);
        }

        $sql .= ' GROUP BY s.id ORDER BY s.code';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        if (!is_array($rows)) {
            return [];
        }
        $result = [];
        foreach ($rows as $row) {
            $registryCounts = [];
            foreach ($enabledRegistries as $reg) {
                $code = (string) $reg['code'];
                $registryCounts[$code] = (int) ($row[$code] ?? 0);
            }
            $result[] = new SiteStatsRow(
                code: (string) $row['code'],
                total: (int) $row['total'],
                registryCounts: $registryCounts,
            );
        }
        return $result;
    }

    /** @return list<mixed> */
    public function getAvailableYears(): array
    {
        $stmt = $this->pdo->query("
            SELECT DISTINCT strftime('%Y', created_at) as year
            FROM reports
            ORDER BY year DESC
        ");
        $rows = $stmt !== false ? $stmt->fetchAll() : [];
        return array_column(is_array($rows) ? $rows : [], 'year');
    }

    public function getRamiStructuredStats(string $year = ''): RamiStats
    {
        $params = [];
        $yearFilter = '';
        if (!empty($year)) {
            $yearFilter = ' AND created_at >= :year_start AND created_at < :year_next';
            $params[':year_start'] = $year . '-01-01 00:00:00';
            $params[':year_next'] = ((int) $year + 1) . '-01-01 00:00:00';
        }

        $ramiType = $this->pdo->quote(ReportType::Rami->value);

        $sqlNature = "SELECT nature_auteur, COUNT(*) as count
            FROM reports
            WHERE type = {$ramiType} AND nature_auteur IS NOT NULL AND nature_auteur != ''{$yearFilter}
            GROUP BY nature_auteur
            ORDER BY count DESC";
        $stmt = $this->pdo->prepare($sqlNature);
        $stmt->execute($params);
        /** @var list<array{nature_auteur: string, count: int}> $byNature */
        $byNature = $stmt->fetchAll();

        $sqlType = "SELECT type_acte, COUNT(*) as count
            FROM reports
            WHERE type = {$ramiType} AND type_acte IS NOT NULL AND type_acte != ''{$yearFilter}
            GROUP BY type_acte
            ORDER BY count DESC";
        $stmt = $this->pdo->prepare($sqlType);
        $stmt->execute($params);
        /** @var list<array{type_acte: string, count: int}> $byType */
        $byType = $stmt->fetchAll();

        return new RamiStats(
            byNatureAuteur: is_array($byNature) ? $byNature : [],
            byTypeActe: is_array($byType) ? $byType : [],
        );
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }
}
