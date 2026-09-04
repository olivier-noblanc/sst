<?php

/** StatsQueryRepository — Requêtes/statistiques et exports (lecture seule). */

namespace App\Repository;

use App\DTO\IndicateursData;
use App\DTO\RamiStats;
use App\DTO\SiteStatsRow;
use App\DTO\SynthesisRow;
use App\Enum\ReportState;
use PDO;

class StatsQueryRepository
{
    public const EXPORT_MAX_ROWS = 50000;

    /**
     * Colonnes du SELECT de base de getExportData() qui correspondent 1:1 à des
     * colonnes de la table reports (sans alias). Sert à dé-dupliquer les colonnes
     * dynamiques issues de registry_fields : un field_code déjà sélectionné ici
     * ne doit pas être re-ajouté au SELECT (les doublons PDO::FETCH_ASSOC se
     * masquent silencieusement et polluent la requête).
     */
    private const array BASE_EXPORT_COLUMNS = [
        'uuid', 'reference', 'type', 'objet', 'description',
        'date_evenement', 'heure_evenement', 'lieu',
        'declarant_id', 'declarant_nom', 'declarant_prenom',
        'pour_compte_de', 'pour_compte_nom', 'pour_compte_prenom',
        'nature_auteur', 'type_acte',
        'site_id', 'site_text', 'pole', 'service_affectation',
        'telephone_mobile', 'consent_syndicat',
        'is_confidential', 'etat',
        'repondant_id', 'date_reponse', 'reponse',
        'attachment_name', 'attachment_mime',
        'created_at', 'updated_at',
    ];

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

        // Audit #61 — site filter must be a WHERE clause on s.id, not added to
        // the LEFT JOIN's ON clause. Before this fix, `AND r.site_id = :site_id`
        // was added to the ON clause, which doesn't filter the sites returned
        // (LEFT JOIN still includes all sites, just with NULL report data for
        // non-matching sites). The user saw all sites even when filtering by one.
        if ($siteId > 0) {
            $sql .= ' WHERE s.id = :site_id';
            $params[':site_id'] = $siteId;
        }

        $sql .= ' GROUP BY s.id, r.type ORDER BY s.code, r.type';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
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
     * Colonnes physiques réelles de la table reports (PRAGMA table_info).
     *
     * Oracle R1 — source unique de vérité pour « ce qui peut réellement être
     * sélectionné » : utilisée par getExportData() (colonnes dynamiques du
     * SELECT) ET par ExportService::getDynamicExportFields() (annoncé en
     * en-têtes CSV) pour garantir qu'aucune colonne annoncée n'est vide —
     * un registry_field sans colonne physique (métadonnée, case à cocher de
     * formulaire comme 'pour_compte') n'est jamais sélectionné ni exporté.
     *
     * @return list<string>
     */
    public function getReportPhysicalColumns(): array
    {
        $colsStmt = $this->pdo->query('PRAGMA table_info(reports)');
        $reportColumns = $colsStmt !== false ? $colsStmt->fetchAll() : [];
        $names = [];
        foreach ($reportColumns as $column) {
            $columnName = $column['name'] ?? null;
            if (is_string($columnName) && $columnName !== '') {
                $names[] = $columnName;
            }
        }
        return $names;
    }

    /**
     * @param array{type?: string, site_id?: int, declarant_id?: int, date_from?: string, date_to?: string, etats?: list<string>} $filters
     * @param string|null $registryCode Code du registre pour ajouter dynamiquement les colonnes depuis registry_fields
     * @return list<array{uuid: string, reference: string, type: string, objet: string, description: string, date_evenement: string, heure_evenement: ?string, lieu: string, declarant_id: int, declarant_nom: string, declarant_prenom: string, pour_compte_de: ?string, pour_compte_nom: ?string, pour_compte_prenom: ?string, nature_auteur: ?string, type_acte: ?string, site_id: ?int, site_text: ?string, pole: ?string, service_affectation: ?string, telephone_mobile: ?string, is_confidential: int, consent_syndicat: int, etat: string, repondant_id: ?int, date_reponse: ?string, reponse: ?string, attachment_name: ?string, attachment_mime: ?string, created_at: string, updated_at: string, site_code: ?string, site_nom: ?string, repondant_nom: ?string, repondant_prenom: ?string}>
     */

    public function getExportData(array $filters = [], ?string $registryCode = null): array
    {
        // Build dynamic columns from registry_fields if registryCode is provided
        $dynamicColumns = '';
        if ($registryCode !== null) {
            $registryRepo = RegistryRepository::instance();
            $registry = $registryRepo->findByCode($registryCode);

            if ($registry !== null) {
                $registryId = (int) $registry['id'];
                $stmt = $this->pdo->prepare('SELECT field_code FROM registry_fields WHERE registry_id = ?');
                $stmt->execute([$registryId]);
                $fieldKeys = array_column($stmt->fetchAll(), 'field_code');

                // Only add registry fields that are (a) real columns of the
                // reports table and (b) not already selected in $baseColumns.
                // Real RAMI registry_fields include 'pour_compte' (a form
                // checkbox, not a physical column) — adding it verbatim
                // generated "r.pour_compte" → PDOException "no such column"
                // and every RAMI export crashed. 'nature_auteur', 'type_acte',
                // 'pour_compte_nom', 'pour_compte_prenom' are real columns but
                // already selected in the base SELECT (duplicates).
                // Oracle R1 — le filtre PRAGMA est factorisé dans
                // getReportPhysicalColumns(), réutilisé par ExportService pour
                // n'annoncer en CSV que des colonnes réellement sélectionnées.
                $existingColumns = array_flip($this->getReportPhysicalColumns());
                $baseSelected = array_flip(self::BASE_EXPORT_COLUMNS);

                foreach ($fieldKeys as $fieldKey) {
                    // Sanitize field key to prevent SQL injection
                    // (string) normalise le null de preg_replace en '' — rejeté
                    // par le check ci-dessous, comportement runtime inchangé
                    $safeKey = (string) preg_replace('/[^a-zA-Z_]/', '', (string) $fieldKey);
                    if ($safeKey !== '' && isset($existingColumns[$safeKey]) && !isset($baseSelected[$safeKey])) {
                        $dynamicColumns .= ', r.' . $safeKey;
                    }
                }
            }
        }

        $baseColumns = '
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
        ';

        $sql = $baseColumns . $dynamicColumns . '
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

        if (!empty($filters['etats'])) {
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
        /** @var list<array{uuid: string, reference: string, type: string, objet: string, description: string, date_evenement: string, heure_evenement: ?string, lieu: string, declarant_id: int, declarant_nom: string, declarant_prenom: string, pour_compte_de: ?string, pour_compte_nom: ?string, pour_compte_prenom: ?string, nature_auteur: ?string, type_acte: ?string, site_id: ?int, site_text: ?string, pole: ?string, service_affectation: ?string, telephone_mobile: ?string, is_confidential: int, consent_syndicat: int, etat: string, repondant_id: ?int, date_reponse: ?string, reponse: ?string, attachment_name: ?string, attachment_mime: ?string, created_at: string, updated_at: string, site_code: ?string, site_nom: ?string, repondant_nom: ?string, repondant_prenom: ?string}> $rows */
        $rows = $stmt->fetchAll();
        return $rows;
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
            // Sanitize alias: only [a-zA-Z0-9_] — prevents SQL syntax errors
            // on custom registry codes with dots, hyphens, etc.
            $safeAlias = preg_replace('/[^a-zA-Z0-9_]/', '_', $code);
            $typeColumns[] = "SUM(CASE WHEN type = '{$safeCode}' THEN 1 ELSE 0 END) as total_{$safeAlias}";
            $defaultRegistryTotals['total_' . $safeAlias] = 0;
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
            $safeAlias = preg_replace('/[^a-zA-Z0-9_]/', '_', (string) $reg['code']);
            $key = 'total_' . $safeAlias;
            $registryTotals[$key] = (int) ($result[$key] ?? 0);
        }

        return new IndicateursData(
            totalReports: (int) ($result['total_reports'] ?? 0),
            totalNouveau: (int) ($result['total_' . ReportState::Nouveau->value] ?? 0),
            totalEnCours: (int) ($result['total_' . ReportState::EnCours->value] ?? 0),
            totalTraite: (int) ($result['total_' . ReportState::Traite->value] ?? 0),
            totalAbandonne: (int) ($result['total_' . ReportState::Abandonne->value] ?? 0),
            totalReouvert: (int) ($result['total_' . ReportState::Reouvert->value] ?? 0),
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
            $safeAlias = preg_replace('/[^a-zA-Z0-9_]/', '_', $code);
            $typeColumns[] = "SUM(CASE WHEN r.type = '{$safeCode}' THEN 1 ELSE 0 END) as {$safeAlias}";
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
        $onExtra = [];
        $where = [];

        // Year filter goes in the ON clause to preserve the LEFT JOIN semantics:
        // sites with no reports in the year still appear with total=0.
        if (!empty($year)) {
            $onExtra[] = 'r.created_at >= :year_start AND r.created_at < :year_next';
            $params[':year_start'] = $year . '-01-01 00:00:00';
            $params[':year_next'] = ((int) $year + 1) . '-01-01 00:00:00';
        }

        if (!empty($onExtra)) {
            $sql .= ' AND ' . implode(' AND ', $onExtra);
        }

        // Audit #62 — site filter on s.id (WHERE), not r.site_id (LEFT JOIN ON).
        // Same bug as #61: filtering on r.site_id in the ON clause doesn't filter
        // the sites returned (LEFT JOIN keeps all sites with NULL report data).
        if ($siteId > 0) {
            $where[] = 's.id = :site_id';
            $params[':site_id'] = $siteId;
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' GROUP BY s.id ORDER BY s.code';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $result = [];
        foreach ($rows as $row) {
            $registryCounts = [];
            foreach ($enabledRegistries as $reg) {
                $code = (string) $reg['code'];
                $safeAlias = preg_replace('/[^a-zA-Z0-9_]/', '_', $code);
                $registryCounts[$code] = (int) ($row[$safeAlias] ?? 0);
            }
            $result[] = new SiteStatsRow(
                code: (string) $row['code'],
                total: (int) $row['total'],
                registryCounts: $registryCounts,
            );
        }
        return $result;
    }

    /** @return list<array{year: string}> */
    public function getAvailableYears(): array
    {
        // Audit #74 — convert created_at (UTC) to Europe/Paris before extracting
        // the year. Otherwise a report created at Paris 2025-01-01 00:30 (which is
        // 2024-12-31 23:30 UTC) would appear in year 2024 instead of 2025 in the
        // year filter dropdown.
        // +1 hour shifts UTC to Europe/Paris winter time (CET). DST is ignored —
        // for accurate DST handling we'd need full timezone logic, but for a
        // year filter dropdown, +/-1h is good enough.
        //
        // NOTE: strftime() is a SQLite function but some CI environments (older
        // SQLite or PHP 8.5 with deprecated strftime) may return NULL. Using
        // substr() on the datetime result is more portable and always works.
        $stmt = $this->pdo->query("
            SELECT DISTINCT substr(datetime(created_at, '+1 hour'), 1, 4) as year
            FROM reports
            ORDER BY year DESC
        ");
        $rows = $stmt !== false ? $stmt->fetchAll() : [];
        return array_column($rows, 'year');
    }

    /** @api */
    public function getStructuredStatsForRegistry(string $registryCode, string $year = ''): RamiStats
    {
        $byNature = [];
        $byType = [];

        // Find registry
        $regRepo = new RegistryRepository($this->pdo);
        $registry = $regRepo->findByCode($registryCode);
        if ($registry === null) {
            return new RamiStats(byNatureAuteur: $byNature, byTypeActe: $byType);
        }
        $registryId = (int) $registry['id'];

        // Get active field_codes for this registry from registry_fields
        $stmt = $this->pdo->prepare('SELECT field_code FROM registry_fields WHERE registry_id = :rid');
        $stmt->execute([':rid' => $registryId]);
        /** @var list<string> $activeFieldCodes */
        $activeFieldCodes = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Backward compat: if registry_fields is empty for this registry,
        // assume legacy behavior (nature_auteur + type_acte are active)
        // This ensures existing tests and RAMI/DGI/RSST registries work
        // even if registry_fields wasn't seeded for them.
        if (empty($activeFieldCodes)) {
            $activeFieldCodes = ['nature_auteur', 'type_acte'];
        }

        $params = [];
        $yearFilter = '';
        if (!empty($year)) {
            $yearFilter = ' AND created_at >= :year_start AND created_at < :year_next';
            $params[':year_start'] = $year . '-01-01 00:00:00';
            $params[':year_next'] = ((int) $year + 1) . '-01-01 00:00:00';
        }

        $params[':type'] = $registryCode;

        if (in_array('nature_auteur', $activeFieldCodes, true)) {
            $sqlNature = "SELECT nature_auteur, COUNT(*) as count
                FROM reports
                WHERE type = :type AND nature_auteur IS NOT NULL AND nature_auteur != ''{$yearFilter}
                GROUP BY nature_auteur
                ORDER BY count DESC";
            $stmt = $this->pdo->prepare($sqlNature);
            $stmt->execute($params);
            /** @var list<array{nature_auteur: string, count: int}> $byNature */
            $byNature = $stmt->fetchAll();
        }

        if (in_array('type_acte', $activeFieldCodes, true)) {
            $sqlType = "SELECT type_acte, COUNT(*) as count
                FROM reports
                WHERE type = :type AND type_acte IS NOT NULL AND type_acte != ''{$yearFilter}
                GROUP BY type_acte
                ORDER BY count DESC";
            $stmt = $this->pdo->prepare($sqlType);
            $stmt->execute($params);
            /** @var list<array{type_acte: string, count: int}> $byType */
            $byType = $stmt->fetchAll();
        }

        return new RamiStats(
            byNatureAuteur: $byNature,
            byTypeActe: $byType,
        );
    }

    public function countActive(string $type, int $siteId = 0, int $userId = 0, bool $confidentialMode = false): int
    {
        $sql = "SELECT COUNT(*) FROM reports WHERE type = :type AND etat != '" . ReportState::Abandonne->value . "'";
        $params = [':type' => $type];

        if ($siteId > 0) {
            $sql .= ' AND site_id = :site_id';
            $params[':site_id'] = $siteId;
        }
        if ($confidentialMode && $userId > 0) {
            $sql .= ' AND (is_confidential = 0 OR declarant_id = :user_id)';
            $params[':user_id'] = $userId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function countByDeclarantId(int $declarantId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM reports WHERE declarant_id = :uid');
        $stmt->execute([':uid' => $declarantId]);
        return (int) $stmt->fetchColumn();
    }
}
