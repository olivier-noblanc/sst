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
    ) {}

    /** @param array<string, string> $post */
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
            isConfidential: isset($post['is_confidential']) && $post['is_confidential'] === '1',
            consentSyndicat: isset($post['consent_syndicat']) && $post['consent_syndicat'] === '1',
            natureAuteur: $natureAuteur !== '' ? $natureAuteur : null,
            typeActe: $typeActe !== '' ? $typeActe : null,
            pourCompteNom: $pourCompte ? trim($post['pour_compte_nom'] ?? '') : null,
            pourComptePrenom: $pourCompte ? trim($post['pour_compte_prenom'] ?? '') : null,
            removeAttachment: isset($post['remove_attachment']) && $post['remove_attachment'] === '1',
        );
    }

    /**
     * Convert to snake_case array for DB.
     * When $removeAttachment is true, includes attachment fields as null
     * to clear the existing attachment (audit #4-High).
     *
     * @return array{
     *     objet: string,
     *     description: string,
     *     dateEvenement: string,
     *     heureEvenement: ?string,
     *     lieu: ?string,
     *     siteText: ?string,
     *     pole: ?string,
     *     serviceAffectation: ?string,
     *     telephoneMobile: ?string,
     *     isConfidential: bool,
     *     consentSyndicat: bool,
     *     natureAuteur: ?string,
     *     typeActe: ?string,
     *     pourCompteNom: ?string,
     *     pourComptePrenom: ?string,
     *     attachmentBlob: ?string,
     *     attachmentName: ?string,
     *     attachmentMime: ?string,
     *     removeAttachment: bool
     * }
     */
    public function toArray(): array
    {
        $attachmentBlob = $this->removeAttachment ? null : $this->attachmentBlob;
        $attachmentName = $this->removeAttachment ? null : $this->attachmentName;
        $attachmentMime = $this->removeAttachment ? null : $this->attachmentMime;

        return [
            'objet' => $this->objet,
            'description' => $this->description,
            'dateEvenement' => $this->dateEvenement,
            'heureEvenement' => $this->heureEvenement,
            'lieu' => $this->lieu,
            'siteText' => $this->siteText,
            'pole' => $this->pole,
            'serviceAffectation' => $this->serviceAffectation,
            'telephoneMobile' => $this->telephoneMobile,
            'isConfidential' => $this->isConfidential,
            'consentSyndicat' => $this->consentSyndicat,
            'natureAuteur' => $this->natureAuteur,
            'typeActe' => $this->typeActe,
            'pourCompteNom' => $this->pourCompteNom,
            'pourComptePrenom' => $this->pourComptePrenom,
            'attachmentBlob' => $attachmentBlob,
            'attachmentName' => $attachmentName,
            'attachmentMime' => $attachmentMime,
            'removeAttachment' => $this->removeAttachment,
        ];
    }
}
