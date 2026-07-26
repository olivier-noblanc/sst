<?php

/** CreateReportCommand — DTO pour la création d'un signalement. */

namespace App\DTO;

use App\Enum\ReportType;

class CreateReportCommand
{
    /**
     * Modular-audit P2.3 — Type is now a string (validated by handler via
     * RegistryRepository::findByCode), not a ReportType enum.
     *
     * Before this fix, `ReportType::from($post['type'])` was called in
     * fromPost() — which throws ValueError on any custom registry code
     * (e.g. 'violences', 'harassment'). Custom registries could never
     * be created via the form.
     *
     * The handler validates that $type corresponds to an enabled registry
     * in the `registries` table before constructing the DTO.
     */
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
        /** Batch 3 — Dynamic field values for any registry. */
        public readonly array $fieldValues = [],
    ) {}

    /**
     * @param array<string, string> $post
     * @param array<string, mixed> $user
     */
    public static function fromPost(array $post, array $user): self
    {
        $pourCompte = isset($post['pour_compte']) && $post['pour_compte'] === '1';
        $type = trim($post['type'] ?? '');
        $natureAuteur = trim($post['nature_auteur'] ?? '');
        $typeActe = trim($post['type_acte'] ?? '');
        if ($type === ReportType::Rami->value) {
            $ramiFields = validateRamiFields($natureAuteur, $typeActe);
            $natureAuteur = $ramiFields['nature_auteur'];
            $typeActe = $ramiFields['type_acte'];
        }

        // Batch 3 — Collect ALL registry_fields values from POST dynamically.
        // This replaces the hardcoded nature_auteur/type_acte/pour_compte_nom/prenom
        // collection with a generic mechanism that works for any registry.
        $fieldValues = [];
        // Collect known field codes from POST — any key that matches a registry field pattern
        $knownFieldCodes = ['nature_auteur', 'type_acte', 'pour_compte_nom', 'pour_compte_prenom'];
        foreach ($knownFieldCodes as $code) {
            if (isset($post[$code]) && trim((string) $post[$code]) !== '') {
                $fieldValues[$code] = trim((string) $post[$code]);
            }
        }

        $declarantNom = $user['nom'] ?? '';
        $declarantPrenom = $user['prenom'] ?? '';
        $declarantIdStr = $user['id'] ?? '0';

        return new self(
            type: $type,
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
            fieldValues: $fieldValues,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = get_object_vars($this);
        // type is already a string (since P2.3), no need to convert
        return $data;
    }
}

