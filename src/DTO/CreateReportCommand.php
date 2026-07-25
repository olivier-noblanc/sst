<?php

/** CreateReportCommand — DTO pour la création d'un signalement. */

namespace App\DTO;

use App\Enum\ReportType;

class CreateReportCommand
{
    public function __construct(
        public readonly string $type,
        public readonly string $objet,
        public readonly string $description,
        public readonly string $dateEvenement,
        public readonly ?string $heureEvenement,
        public readonly ?string $lieu,
        public readonly int $declarantId,
        public readonly string $declarantNom,
        public readonly string $declarantPrenom,
        public readonly int $siteId,
        public readonly ?string $siteText,
        public readonly ?string $pole,
        public readonly ?string $serviceAffectation,
        public readonly ?string $telephoneMobile,
        public readonly bool $isConfidential,
        public readonly bool $consentSyndicat,
        public readonly ?string $natureAuteur,
        public readonly ?string $typeActe,
        public readonly ?string $pourCompteNom,
        public readonly ?string $pourComptePrenom,
        public readonly ?string $attachmentBlob,
        public readonly ?string $attachmentName,
        public readonly ?string $attachmentMime,
    ) {}

    /**
     * @param array<string, string> $post
     * @param array<string, mixed> $user
     */
    public static function fromPost(array $post, array $user): self
    {
        $pourCompte = isset($post['pour_compte']) && $post['pour_compte'] === '1';
        $type = $post['type'] ?? '';
        $natureAuteur = trim($post['nature_auteur'] ?? '');
        $typeActe = trim($post['type_acte'] ?? '');
        if ($type === ReportType::Rami->value) {
            $ramiFields = validateRamiFields($natureAuteur, $typeActe);
            $natureAuteur = $ramiFields['nature_auteur'];
            $typeActe = $ramiFields['type_acte'];
        }

        $declarantNom = $user['nom'] ?? '';
        $declarantPrenom = $user['prenom'] ?? '';
        $declarantIdStr = $user['id'] ?? '0';

        return new self(
            type: $post['type'],
            objet: trim($post['objet'] ?? ''),
            description: trim($post['description'] ?? ''),
            dateEvenement: trim($post['date_evenement'] ?? ''),
            heureEvenement: $post['heure_evenement'] ?? null,
            lieu: trim($post['lieu'] ?? ''),
            declarantId: (int) $declarantIdStr,
            declarantNom: $declarantNom,
            declarantPrenom: $declarantPrenom,
            siteId: (int) ($post['site_id'] ?? 0),
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
            attachmentBlob: null,
            attachmentName: null,
            attachmentMime: null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        /** @var array<string, mixed> */
        return get_object_vars($this);
    }
}
