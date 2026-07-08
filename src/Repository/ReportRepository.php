<?php
/** ReportRepository — Couche d'accès aux données pour les signalements. */

class ReportRepository
{
    public function __construct(private PDO $pdo) {}

    public function findById(string $uuid): ?array
    {
        return getReportByUuid($this->pdo, $uuid);
    }

    public function findPaginated(ReportFilter $filter, int $page = 1, int $perPage = 20): array
    {
        return getReportsByRegistry(
            $this->pdo, $filter->type, $filter->toArray(),
            $filter->forceSiteId ?? 0, $filter->seeAllSites,
            $page, $perPage
        );
    }

    public function findBySite(int $siteId): array
    {
        return getReportsBySite($this->pdo, $siteId);
    }

    public function create(CreateReportCommand $cmd): string
    {
        return createReport($this->pdo, $this->toSnakeCase($cmd->toArray()));
    }

    private function toSnakeCase(array $data): array
    {
        $map = [
            'dateEvenement'       => 'date_evenement',
            'heureEvenement'      => 'heure_evenement',
            'declarantId'         => 'declarant_id',
            'declarantNom'        => 'declarant_nom',
            'declarantPrenom'     => 'declarant_prenom',
            'siteId'              => 'site_id',
            'siteText'            => 'site_text',
            'serviceAffectation'  => 'service_affectation',
            'telephoneMobile'     => 'telephone_mobile',
            'isConfidential'      => 'is_confidential',
            'consentSyndicat'     => 'consent_syndicat',
            'natureAuteur'        => 'nature_auteur',
            'typeActe'            => 'type_acte',
            'pourCompteNom'       => 'pour_compte_nom',
            'pourComptePrenom'    => 'pour_compte_prenom',
            'pourCompteDe'        => 'pour_compte_de',
            'attachmentBlob'      => 'attachment_blob',
            'attachmentName'      => 'attachment_name',
            'attachmentMime'      => 'attachment_mime',
        ];
        $result = [];
        foreach ($data as $key => $value) {
            $result[$map[$key] ?? $key] = $value;
        }
        return $result;
    }

    public function update(string $uuid, UpdateReportCommand $cmd, int $userId): bool
    {
        return updateReport($this->pdo, $uuid, $this->toSnakeCase($cmd->toArray()), $userId);
    }

    public function respond(string $uuid, RespondToReportCommand $cmd, int $userId): array
    {
        return respondToReport($this->pdo, $uuid, $userId, $cmd->reponse, $cmd->nouvelEtat, $cmd->attachment);
    }

    public function abandon(string $uuid, int $userId): bool
    {
        return abandonReport($this->pdo, $uuid, $userId);
    }

    public function countByState(string $type, int $siteId = 0, bool $seeAllSites = true): array
    {
        return countReportsByState($this->pdo, $type, $siteId, $seeAllSites);
    }

    public function getStatistics(string $year = '', int $siteId = 0): array
    {
        return getStatisticsIndicateurs($this->pdo, $year, $siteId);
    }

    public function getExportData(array $filters = []): array
    {
        return getExportData($this->pdo, $filters);
    }

    public function getPdo(): PDO { return $this->pdo; }
}
