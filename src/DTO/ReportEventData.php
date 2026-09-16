<?php

declare(strict_types=1);

namespace App\DTO;

use PDO;

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
        /** @phpstan-ignore shipmonk.deadProperty.neverRead (kept for future event listeners) */
        public ?string $motif = null,
        /** @phpstan-ignore shipmonk.deadProperty.neverRead (kept for DB access in listeners) */
        public ?PDO $pdo = null,
        /**
         * Identité d'OCCURRENCE de l'action (id de la ligne qui matérialise
         * l'action : report_responses.id pour une réponse, report_state_history.id
         * pour une réouverture/un abandon). Elle rend le dedup_key outbox unique
         * par occurrence — sans elle, deux actions successives (2e réponse,
         * reopen/abandon répétés) partagent la clé et la 2e notification est
         * perdue par `ON CONFLICT DO NOTHING`.
         */
        public ?int $actionId = null,
    ) {}

    /**
     * Factory from a ReportData object (most common case).
     *
     * Fiabilisation (council) — $motif permet de faire transiter le motif de
     * réouverture jusqu'aux listeners de notification (l'ancien envoi direct
     * du handler report_reopen l'incluait dans l'e-mail).
     */
    public static function fromReport(
        ReportData $report,
        ?int $userId = null,
        ?PDO $pdo = null,
        ?string $motif = null,
        ?int $actionId = null,
    ): self {
        return new self(
            report: $report,
            reportUuid: $report->uuid,
            type: $report->type,
            siteId: $report->siteId,
            userId: $userId,
            motif: $motif,
            pdo: $pdo,
            actionId: $actionId,
        );
    }

    /**
     * Convenience accessor — always returns a string uuid (empty if null).
     */
    public function uuid(): string
    {
        return $this->reportUuid ?? ($this->report !== null ? $this->report->uuid : '');
    }

    /**
     * Convenience accessor — always returns a string type (empty if null).
     */
    public function typeString(): string
    {
        return $this->type ?? ($this->report !== null ? $this->report->type : '');
    }

    /**
     * Convenience accessor — always returns an int siteId (0 if null).
     */
    public function siteIdInt(): int
    {
        $siteId = $this->siteId ?? ($this->report !== null ? $this->report->siteId : null);
        return $siteId ?? 0;
    }

    /**
     * Convenience accessor — always returns an int userId (0 if null).
     */
    public function userIdInt(): int
    {
        return $this->userId ?? 0;
    }
}
