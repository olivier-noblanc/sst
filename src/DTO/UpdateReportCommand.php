<?php
/** UpdateReportCommand — DTO pour l'édition d'un signalement. */

namespace App\DTO;

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
        public readonly ?string $natureAuteur = null,
        public readonly ?string $typeActe = null,
        public readonly ?string $pourCompteNom = null,
        public readonly ?string $pourComptePrenom = null,
        public readonly ?string $attachmentBlob = null,
        public readonly ?string $attachmentName = null,
        public readonly ?string $attachmentMime = null,
    ) {}

    public static function fromPost(array $post): self
    {
        $natureAuteur = trim($post['nature_auteur'] ?? '');
        $typeActe = trim($post['type_acte'] ?? '');
        $pourCompte = isset($post['pour_compte']) && $post['pour_compte'] === '1';

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
            natureAuteur: $natureAuteur ?: null,
            typeActe: $typeActe ?: null,
            pourCompteNom: $pourCompte ? trim($post['pour_compte_nom'] ?? '') : null,
            pourComptePrenom: $pourCompte ? trim($post['pour_compte_prenom'] ?? '') : null,
        );
    }

    /**
     * Convert to snake_case array for DB.
     * Excludes attachment fields when null so updateReport() keeps existing attachment.
     */
    public function toArray(): array
    {
        $data = get_object_vars($this);
        // Only include attachment fields if explicitly set (non-null)
        // This preserves existing attachment when no new file/upload is provided
        if ($data['attachmentBlob'] === null && $data['attachmentName'] === null && $data['attachmentMime'] === null) {
            unset($data['attachmentBlob'], $data['attachmentName'], $data['attachmentMime']);
        }
        return $data;
    }
}
