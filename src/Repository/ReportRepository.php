<?php

/** ReportRepository — Couche d'accès aux données pour les signalements. */

namespace App\Repository;

use App\DTO\AdjacentUuids;
use App\DTO\CreateReportCommand;
use App\DTO\PaginatedReports;
use App\DTO\ReportData;
use App\DTO\ReportFilter;
use App\DTO\UpdateReportCommand;
use PDO;

class ReportRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public static function instance(): self
    {
        static $instance = null;
        if ($instance === null) {
            // Prefer container instance if available (shared lifecycle)
            if (function_exists('getContainer') && getContainer()->has(self::class)) {
                $instance = getContainer()->get(self::class);
            } else {
                $instance = new self(getDB());
            }
        }
        return $instance;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Read — Reports
    // ═══════════════════════════════════════════════════════════════════════════════

    public function findById(string $uuid): ?ReportData
    {
        return ReportQueryRepository::instance()->findById($uuid);
    }

    public function findPaginated(ReportFilter $filter, int $page = 1, int $perPage = 20): PaginatedReports
    {
        return ReportQueryRepository::instance()->findPaginated($filter, $page, $perPage);
    }

    public function getAdjacentUuids(string $type, ?string $createdAt, string $currentUuid): AdjacentUuids
    {
        return ReportQueryRepository::instance()->getAdjacentUuids($type, $createdAt, $currentUuid);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Read — Responses
    // ═══════════════════════════════════════════════════════════════════════════════

    /** @return list<array{id: int, report_uuid: string, user_id: int|null, reponse: string|null, nouvel_etat: string|null, attachment_blob: string|null, attachment_name: string|null, attachment_mime: string|null, created_at: string, nom: string|null, prenom: string|null}> */
    public function getResponses(string $reportUuid): array
    {
        return ReportResponseRepository::instance()->getResponses($reportUuid);
    }

    /**
     * @param list<string> $uuids
     * @return array<string, list<array{id: int, report_uuid: string, user_id: int|null, reponse: string|null, nouvel_etat: string|null, attachment_blob: string|null, attachment_name: string|null, attachment_mime: string|null, created_at: string, nom: string|null, prenom: string|null}>>
     */
    public function getResponsesForUuids(array $uuids): array
    {
        return ReportResponseRepository::instance()->getResponsesForUuids($uuids);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Write — Reports
    // ═══════════════════════════════════════════════════════════════════════════════

    public function create(CreateReportCommand $cmd): string
    {
        return ReportWriteRepository::instance()->create($cmd);
    }

    public function update(string $uuid, UpdateReportCommand $cmd, int $userId): bool
    {
        return ReportWriteRepository::instance()->update($uuid, $cmd, $userId);
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function getTypeByUuid(string $uuid): ?string
    {
        $stmt = $this->pdo->prepare('SELECT type FROM reports WHERE uuid = :uuid');
        $stmt->execute([':uuid' => $uuid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? ($row['type'] ?? null) : null;
    }

    public function getNextSequence(string $type, int $year): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO report_sequence (type, year, last_sequence)
            VALUES (:type, :year, 1)
            ON CONFLICT(type, year) DO UPDATE SET last_sequence = last_sequence + 1
            RETURNING last_sequence
        ');
        $stmt->execute([':type' => $type, ':year' => $year]);
        return (int) $stmt->fetchColumn();
    }

    public function logAccess(string $reportUuid, int $userId, string $role): void
    {
        ReportAuditRepository::instance()->logAccess($reportUuid, $userId, $role);
    }

    /**
     * Find overdue reports (nouveau state, older than cutoff) for delay alerts.
     *
     * Délégué à ReportAnonymizationRepository.
     *
     * @return list<array{uuid: string, reference: string, type: string, objet: string, created_at: string, site_id: int|null, site_code: string|null, site_nom: string|null, declarant_nom: string|null, declarant_prenom: string|null}>
     */
    public function findOverdue(string $cutoffDate): array
    {
        return ReportAnonymizationRepository::instance()->findOverdue($cutoffDate);
    }

    /**
     * Find reports eligible for RGPD anonymization (final state, older than cutoff, not yet anonymized).
     *
     * Délégué à ReportAnonymizationRepository.
     *
     * @return list<array{uuid: string, reference: string, type: string, declarant_nom: string, declarant_prenom: string, date_evenement: string, etat: string}>
     */
    public function findAnonymizable(string $cutoffDate): array
    {
        return ReportAnonymizationRepository::instance()->findAnonymizable($cutoffDate);
    }

    /**
     * Anonymize a single report (RGPD).
     *
     * Délégué à ReportAnonymizationRepository.
     */
    public function anonymize(string $uuid): bool
    {
        return ReportAnonymizationRepository::instance()->anonymize($uuid);
    }
}
