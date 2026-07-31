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
}
