<?php

/** StatsRepository — Façade de statistiques et exports (délègue à StatsQueryRepository). */

namespace App\Repository;

use App\DTO\IndicateursData;
use App\DTO\RamiStats;
use App\DTO\SiteStatsRow;
use App\DTO\SynthesisRow;
use PDO;

class StatsRepository
{
    public const EXPORT_MAX_ROWS = 50000;

    private readonly StatsQueryRepository $query;

    public function __construct(PDO $pdo)
    {
        $this->query = new StatsQueryRepository($pdo);
    }

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
        return $this->query->getSynthesis($year, $siteId);
    }

    /**
     * @param array{type?: string, site_id?: int, declarant_id?: int, date_from?: string, date_to?: string, etats?: list<string>} $filters
     * @param string|null $registryCode Code du registre pour ajouter dynamiquement les colonnes depuis registry_fields
     * @return list<array{uuid: string, reference: string, type: string, objet: string, description: string, date_evenement: string, heure_evenement: ?string, lieu: string, declarant_id: int, declarant_nom: string, declarant_prenom: string, pour_compte_de: ?string, pour_compte_nom: ?string, pour_compte_prenom: ?string, nature_auteur: ?string, type_acte: ?string, site_id: ?int, site_text: ?string, pole: ?string, service_affectation: ?string, telephone_mobile: ?string, is_confidential: int, consent_syndicat: int, etat: string, repondant_id: ?int, date_reponse: ?string, reponse: ?string, attachment_name: ?string, attachment_mime: ?string, created_at: string, updated_at: string, site_code: ?string, site_nom: ?string, repondant_nom: ?string, repondant_prenom: ?string}>
     */
    public function getExportData(array $filters = [], ?string $registryCode = null): array
    {
        return $this->query->getExportData($filters, $registryCode);
    }

    /**
     * Colonnes physiques réelles de la table reports (oracle R1 — partagé
     * avec ExportService pour n'annoncer en CSV que des colonnes réellement
     * sélectionnées).
     *
     * @return list<string>
     */
    public function getReportPhysicalColumns(): array
    {
        return $this->query->getReportPhysicalColumns();
    }

    public function getIndicateurs(string $year = '', int $siteId = 0): IndicateursData
    {
        return $this->query->getIndicateurs($year, $siteId);
    }

    /** @return list<SiteStatsRow> */
    public function getBySite(string $year = '', int $siteId = 0): array
    {
        return $this->query->getBySite($year, $siteId);
    }

    /** @return list<array{year: string}> */
    public function getAvailableYears(): array
    {
        return $this->query->getAvailableYears();
    }

    /** @api */
    public function getStructuredStatsForRegistry(string $registryCode, string $year = ''): RamiStats
    {
        return $this->query->getStructuredStatsForRegistry($registryCode, $year);
    }

    public function countActive(string $type, int $siteId = 0, int $userId = 0, bool $confidentialMode = false): int
    {
        return $this->query->countActive($type, $siteId, $userId, $confidentialMode);
    }

    public function countByDeclarantId(int $declarantId): int
    {
        return $this->query->countByDeclarantId($declarantId);
    }
}
