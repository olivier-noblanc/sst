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
        public readonly SiteId $siteId,
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
     * @param array{id: int|string, nom: string, prenom: string} $user
     */
    public static function fromPost(array $post, array $user): self
    {
        $pourCompte = isset($post['pour_compte']) && $post['pour_compte'] === '1';
        // Modular-audit P2.3 — accept any string code (custom registries included).
        // Handler is responsible for validating via RegistryRepository::findByCode().
        $type = trim($post['type'] ?? '');
        $natureAuteur = trim($post['nature_auteur'] ?? '');
        $typeActe = trim($post['type_acte'] ?? '');
        // RAMI-specific fields validation (kept for backwards compat with the
        // historical RAMI registry — custom registries use registry_fields DB
        // for their dynamic fields, not these hardcoded columns).
        if ($type === ReportType::Rami->value) {
            $ramiFields = validateRamiFields($natureAuteur, $typeActe);
            $natureAuteur = $ramiFields['nature_auteur'];
            $typeActe = $ramiFields['type_acte'];
        }

        $declarantNom = $user['nom'];
        $declarantPrenom = $user['prenom'];
        $declarantIdStr = $user['id'];

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
            siteId: SiteId::fromInput((int) ($post['site_id'] ?? 0)),
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

    /**
     * @return array{
     *     type: string,
     *     objet: string,
     *     description: string,
     *     dateEvenement: string,
     *     heureEvenement: ?string,
     *     lieu: ?string,
     *     declarantId: int,
     *     declarantNom: string,
     *     declarantPrenom: string,
     *     siteId: ?int,
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
     *     attachmentMime: ?string
     * }
     */
    public function toArray(): array
    {
        /** @var array{type: string, objet: string, description: string, dateEvenement: string, heureEvenement: ?string, lieu: ?string, declarantId: int, declarantNom: string, declarantPrenom: string, siteId: ?int, siteText: ?string, pole: ?string, serviceAffectation: ?string, telephoneMobile: ?string, isConfidential: bool, consentSyndicat: bool, natureAuteur: ?string, typeActe: ?string, pourCompteNom: ?string, pourComptePrenom: ?string, attachmentBlob: ?string, attachmentName: ?string, attachmentMime: ?string} $data */
        $data = get_object_vars($this);
        // SiteId is a value object — convert to ?int for downstream consumers
        $data['siteId'] = $this->siteId->toSql();
        return $data;
    }
}
