<?php

/** ReportFilter — DTO pour les filtres de recherche de signalements. */

namespace App\DTO;

class ReportFilter
{
    public function __construct(
        public readonly string $type,
        public readonly string $etat = '',
        public readonly int $siteId = 0,
        public readonly ?int $declarantId = null,
        public readonly ?int $confidentialFilter = null,
        public readonly ?int $forceSiteId = null,
        public readonly ?string $search = null,
        public readonly bool $seeAllSites = true,
        public readonly bool $chsctConsentOnly = false,
        public readonly ?int $linkedAgentId = null,
        public readonly ?string $linkedAgentVisibility = null,
    ) {}

    /**
     * @param array<string, string> $get
     * @param array<string, string> $user
     */
    public static function fromGet(array $get, array $user): self
    {
        return new self(
            type: $get['type'] ?? '',
            etat: $get['etat'] ?? '',
            siteId: (int) ($get['site'] ?? 0),
            search: trim($get['q'] ?? '') !== '' ? trim($get['q'] ?? '') : null,
        );
    }

    /**
     * @return array{
     *     etat: string,
     *     site_id: int,
     *     q: ?string,
     *     confidential_filter: ?int,
     *     own_only: null,
     *     force_site_id: ?int,
     *     declarant_id: ?int,
     *     chsct_consent_only: bool,
     *     linked_agent_id: ?int,
     *     linked_agent_visibility: ?string
     * }
     */
    public function toArray(): array
    {
        return [
            'etat' => $this->etat,
            'site_id' => $this->siteId,
            'q' => $this->search,
            'confidential_filter' => $this->confidentialFilter,
            'own_only' => null,
            'force_site_id' => $this->forceSiteId,
            'declarant_id' => $this->declarantId,
            'chsct_consent_only' => $this->chsctConsentOnly,
            'linked_agent_id' => $this->linkedAgentId,
            'linked_agent_visibility' => $this->linkedAgentVisibility,
        ];
    }
}
