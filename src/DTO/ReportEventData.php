<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * ReportEventData — DTO pour les events liés aux signalements.
 *
 * Remplace les array<string, mixed> dans EventDispatcher::dispatch().
 * Permet à Infection de ne pas muter les casts/coalesce sur des arrays non typés.
 */
final readonly class ReportEventData
{
    public function __construct(
        public ?ReportData $report = null,
        public ?string $reportUuid = null,
        public ?string $type = null,
        public ?int $siteId = null,
        public ?int $userId = null,
        public ?string $motif = null,
        public ?\PDO $pdo = null,
    ) {}

    /**
     * Factory from a ReportData object (most common case).
     */
    public static function fromReport(ReportData $report, ?int $userId = null, ?\PDO $pdo = null): self
    {
        return new self(
            report: $report,
            reportUuid: $report->uuid,
            type: $report->type,
            siteId: $report->siteId,
            userId: $userId,
            pdo: $pdo,
        );
    }

    /**
     * Convenience accessor — always returns a string uuid (empty if null).
     */
    public function uuid(): string
    {
        return $this->reportUuid ?? ($this->report?->uuid ?? '');
    }

    /**
     * Convenience accessor — always returns a string type (empty if null).
     */
    public function typeString(): string
    {
        return $this->type ?? ($this->report?->type ?? '');
    }

    /**
     * Convenience accessor — always returns an int siteId (0 if null).
     */
    public function siteIdInt(): int
    {
        return $this->siteId ?? ($this->report?->siteId ?? 0);
    }

    /**
     * Convenience accessor — always returns an int userId (0 if null).
     */
    public function userIdInt(): int
    {
        return $this->userId ?? 0;
    }
}
