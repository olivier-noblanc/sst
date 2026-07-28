<?php

namespace App\DTO;

class ReportListItem
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $reference,
        public readonly string $type,
        public readonly string $objet,
        public readonly string $dateEvenement,
        public readonly int $declarantId,
        public readonly string $declarantNom,
        public readonly string $declarantPrenom,
        public readonly string $siteCode,
        public readonly string $etat,
        public readonly int $isConfidential,
    ) {}

    /**
     * @return array{
     *     uuid: string,
     *     reference: string,
     *     type: string,
     *     objet: string,
     *     date_evenement: string,
     *     declarant_id: int,
     *     declarant_nom: string,
     *     declarant_prenom: string,
     *     site_code: string,
     *     etat: string,
     *     is_confidential: int
     * }
     */
    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'reference' => $this->reference,
            'type' => $this->type,
            'objet' => $this->objet,
            'date_evenement' => $this->dateEvenement,
            'declarant_id' => $this->declarantId,
            'declarant_nom' => $this->declarantNom,
            'declarant_prenom' => $this->declarantPrenom,
            'site_code' => $this->siteCode,
            'etat' => $this->etat,
            'is_confidential' => $this->isConfidential,
        ];
    }
}
