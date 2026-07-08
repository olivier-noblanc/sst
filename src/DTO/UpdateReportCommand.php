<?php
/** UpdateReportCommand — DTO pour l'édition d'un signalement. */

class UpdateReportCommand
{
    public function __construct(
        public readonly string $objet,
        public readonly string $description,
        public readonly string $dateEvenement,
        public readonly ?string $heureEvenement,
        public readonly ?string $lieu,
        public readonly ?string $siteText,
        public readonly ?string $pole,
        public readonly ?string $serviceAffectation,
        public readonly ?string $telephoneMobile,
        public readonly int $isConfidential,
        public readonly int $consentSyndicat,
    ) {}

    public static function fromPost(array $post): self
    {
        return new self(
            objet: trim($post['objet'] ?? ''),
            description: trim($post['description'] ?? ''),
            dateEvenement: trim($post['date_evenement'] ?? ''),
            heureEvenement: $post['heure_evenement'] ?? null,
            lieu: trim($post['lieu'] ?? ''),
            siteText: trim($post['site_text'] ?? ''),
            pole: trim($post['pole'] ?? ''),
            serviceAffectation: trim($post['service_affectation'] ?? ''),
            telephoneMobile: trim($post['telephone_mobile'] ?? ''),
            isConfidential: isset($post['is_confidential']) && $post['is_confidential'] === '1' ? 1 : 0,
            consentSyndicat: isset($post['consent_syndicat']) && $post['consent_syndicat'] === '1' ? 1 : 0,
        );
    }

    public function toArray(): array { return get_object_vars($this); }
}