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
        public readonly bool $isConfidential,
        public readonly bool $consentSyndicat,
        public readonly ?string $natureAuteur = null,
        public readonly ?string $typeActe = null,
        public readonly ?string $pourCompteNom = null,
        public readonly ?string $pourComptePrenom = null,
        public readonly ?string $attachmentBlob = null,
        public readonly ?string $attachmentName = null,
        public readonly ?string $attachmentMime = null,
        /**
         * Audit #4-High — Quand true, le UPDATE doit explicitement SET
         * attachment_blob=NULL, attachment_name=NULL, attachment_mime=NULL
         * pour supprimer la PJ existante. Sans ce flag, toArray() strippait
         * les clés null → l'UPDATE ne touchait pas aux colonnes → la PJ
         * restait en DB même après "Supprimer la pièce jointe".
         */
        public readonly bool $removeAttachment = false,
        /** Batch 3 — Dynamic field values for any registry. */
        public readonly array $fieldValues = [],
    ) {}

    /** @param array<string, string> $post */
    public static function fromPost(array $post): self
    {
        $natureAuteur = trim($post['nature_auteur'] ?? '');
        $typeActe = trim($post['type_acte'] ?? '');
        $pourCompte = isset($post['pour_compte']) && $post['pour_compte'] === '1';

        // Batch 3 — Collect field values dynamically
        $fieldValues = [];
        $knownFieldCodes = ['nature_auteur', 'type_acte', 'pour_compte_nom', 'pour_compte_prenom'];
        foreach ($knownFieldCodes as $code) {
            if (isset($post[$code]) && trim((string) $post[$code]) !== '') {
                $fieldValues[$code] = trim((string) $post[$code]);
            }
        }

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
            isConfidential: isset($post['is_confidential']) && $post['is_confidential'] === '1',
            consentSyndicat: isset($post['consent_syndicat']) && $post['consent_syndicat'] === '1',
            natureAuteur: $natureAuteur !== '' ? $natureAuteur : null,
            typeActe: $typeActe !== '' ? $typeActe : null,
            pourCompteNom: $pourCompte ? trim($post['pour_compte_nom'] ?? '') : null,
            pourComptePrenom: $pourCompte ? trim($post['pour_compte_prenom'] ?? '') : null,
            removeAttachment: isset($post['remove_attachment']) && $post['remove_attachment'] === '1',
            fieldValues: $fieldValues,
        );
    }

    /**
     * Convert to snake_case array for DB.
     * Excludes attachment fields when null (no new upload) so updateReport()
     * keeps existing attachment. When $removeAttachment is true, includes
     * them as null explicitly to clear the existing attachment (audit #4-High).
     */
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        /** @var array<string, mixed> */
        $data = get_object_vars($this);

        if ($this->removeAttachment) {
            // Force NULL on attachment columns to delete the existing PJ.
            $data['attachmentBlob'] = null;
            $data['attachmentName'] = null;
            $data['attachmentMime'] = null;
        } elseif ($data['attachmentBlob'] === null && $data['attachmentName'] === null && $data['attachmentMime'] === null) {
            // No new upload AND no removal request → preserve existing attachment
            unset($data['attachmentBlob'], $data['attachmentName'], $data['attachmentMime']);
        }

        return $data;
    }
}

