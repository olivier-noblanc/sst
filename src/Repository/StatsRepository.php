<?php

/** StatsRepository — Couche d'accès aux données pour les statistiques et exports. */

namespace App\Repository;

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

    public function getSynthesis(string $year, int $siteId = 0): array
    {
        $sql = "
            SELECT s.id as site_id, s.code, s.nom,
                r.type,
                SUM(CASE WHEN r.etat = 'nouveau' THEN 1 ELSE 0 END) as nouveau,
                SUM(CASE WHEN r.etat = 'en_cours' THEN 1 ELSE 0 END) as en_cours,
                SUM(CASE WHEN r.etat = 'traite' THEN 1 ELSE 0 END) as traite,
                SUM(CASE WHEN r.etat = 'abandonne' THEN 1 ELSE 0 END) as abandonne,
                COUNT(*) as total
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
        return is_array($rows) ? $rows : [];
    }

    public function getExportData(array $filters = []): array
    {
        $sql = '
            SELECT r.uuid, r.reference, r.type, r.objet, r.description,
                   r.date_evenement, r.heure_evenement, r.lieu,
                   r.declarant_id, r.declarant_nom, r.declarant_prenom,
                   r.pour_compte_de, r.pour_compte_nom, r.pour_compte_prenom,
                   r.nature_auteur, r.type_acte,
                   r.site_id, r.is_confidential, r.etat,
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

    public function getIndicateurs(string $year = '', int $siteId = 0): array
    {
        $params = [];
        $sql = "
            SELECT
                COUNT(*) as total_reports,
                SUM(CASE WHEN etat = 'nouveau' THEN 1 ELSE 0 END) as total_nouveau,
                SUM(CASE WHEN etat = 'en_cours' THEN 1 ELSE 0 END) as total_en_cours,
                SUM(CASE WHEN etat = 'traite' THEN 1 ELSE 0 END) as total_traite,
                SUM(CASE WHEN etat = 'abandonne' THEN 1 ELSE 0 END) as total_abandonne,
                SUM(CASE WHEN type = 'rsst' THEN 1 ELSE 0 END) as total_rsst,
                SUM(CASE WHEN type = 'rami' THEN 1 ELSE 0 END) as total_rami,
                SUM(CASE WHEN type = 'dgi' THEN 1 ELSE 0 END) as total_dgi
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
            return [
                'total_reports' => 0, 'total_nouveau' => 0, 'total_en_cours' => 0,
                'total_traite' => 0, 'total_abandonne' => 0,
                'total_rsst' => 0, 'total_rami' => 0, 'total_dgi' => 0,
            ];
        }

        return [
            'total_reports'   => (int) ($result['total_reports'] ?? 0),
            'total_nouveau'   => (int) ($result['total_nouveau'] ?? 0),
            'total_en_cours'  => (int) ($result['total_en_cours'] ?? 0),
            'total_traite'    => (int) ($result['total_traite'] ?? 0),
            'total_abandonne' => (int) ($result['total_abandonne'] ?? 0),
            'total_rsst'      => (int) ($result['total_rsst'] ?? 0),
            'total_rami'      => (int) ($result['total_rami'] ?? 0),
            'total_dgi'       => (int) ($result['total_dgi'] ?? 0),
        ];
    }

    public function getBySite(string $year = '', int $siteId = 0): array
    {
        $sql = "
            SELECT s.code, s.nom,
                COUNT(r.uuid) as total,
                SUM(CASE WHEN r.etat = 'nouveau' THEN 1 ELSE 0 END) as nouveau,
                SUM(CASE WHEN r.etat = 'en_cours' THEN 1 ELSE 0 END) as en_cours,
                SUM(CASE WHEN r.etat = 'traite' THEN 1 ELSE 0 END) as traite,
                SUM(CASE WHEN r.etat = 'abandonne' THEN 1 ELSE 0 END) as abandonne,
                SUM(CASE WHEN r.type = 'rsst' THEN 1 ELSE 0 END) as rsst,
                SUM(CASE WHEN r.type = 'rami' THEN 1 ELSE 0 END) as rami,
                SUM(CASE WHEN r.type = 'dgi' THEN 1 ELSE 0 END) as dgi
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
        return is_array($rows) ? $rows : [];
    }

    public function countByRegistryAndSite(string $type, int $siteId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM reports
            WHERE type = :type AND site_id = :site_id AND etat != 'abandonne'
        ");
        $stmt->execute([':type' => $type, ':site_id' => $siteId]);
        return (int) $stmt->fetchColumn();
    }

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

    public function getRamiStructuredStats(string $year = ''): array
    {
        $params = [];
        $yearFilter = '';
        if (!empty($year)) {
            $yearFilter = ' AND created_at >= :year_start AND created_at < :year_next';
            $params[':year_start'] = $year . '-01-01 00:00:00';
            $params[':year_next'] = ((int) $year + 1) . '-01-01 00:00:00';
        }

        $sqlNature = "SELECT nature_auteur, COUNT(*) as count
            FROM reports
            WHERE type = 'rami' AND nature_auteur IS NOT NULL AND nature_auteur != ''{$yearFilter}
            GROUP BY nature_auteur
            ORDER BY count DESC";
        $stmt = $this->pdo->prepare($sqlNature);
        $stmt->execute($params);
        $byNature = $stmt->fetchAll();

        $sqlType = "SELECT type_acte, COUNT(*) as count
            FROM reports
            WHERE type = 'rami' AND type_acte IS NOT NULL AND type_acte != ''{$yearFilter}
            GROUP BY type_acte
            ORDER BY count DESC";
        $stmt = $this->pdo->prepare($sqlType);
        $stmt->execute($params);
        $byType = $stmt->fetchAll();

        return ['by_nature_auteur' => is_array($byNature) ? $byNature : [], 'by_type_acte' => is_array($byType) ? $byType : []];
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }
}
