<?php

/**
 * CustomFieldsService — Logique métier des champs dynamiques des registres
 * personnalisés (registry_fields / registry_field_values).
 *
 * Points de vérité :
 * - Les valeurs des champs dynamiques vivent dans registry_field_values.
 * - Les codes listés dans COMMAND_MAPPED_CODES ont un chemin de persistance
 *   dédié (colonnes physiques de `reports` via CreateReportCommand /
 *   UpdateReportCommand) : ils sont VALIDÉS ici mais jamais extraits ni
 *   écrits dans registry_field_values — pas de double source de vérité.
 *   (Même philosophie que ExportService::EMITTED_KEYS pour l'export.)
 */

namespace App\Services;

use App\Repository\RegistryFieldRepository;
use App\Repository\RegistryRepository;

class CustomFieldsService
{
    public const int TEXT_MAX_LENGTH = 500;
    public const int TEXTAREA_MAX_LENGTH = 5000;

    /**
     * Codes de champs déjà consommés/persistés par le chemin standard
     * (colonnes physiques de `reports` via les commandes). Un
     * registry_field portant l'un de ces codes est validé depuis le POST
     * mais sa valeur n'est ni extraite dans le DTO ni écrite dans
     * registry_field_values.
     */
    public const array COMMAND_MAPPED_CODES = [
        // Physical reports columns and standard/exported keys.
        'uuid', 'reference', 'type', 'objet', 'description', 'date_evenement',
        'heure_evenement', 'lieu', 'declarant_id', 'declarant_nom',
        'declarant_prenom', 'site_id', 'site_text', 'pole', 'service_affectation',
        'telephone_mobile', 'is_confidential', 'consent_syndicat', 'etat',
        'repondant_id', 'date_reponse', 'reponse', 'attachment_blob',
        'attachment_name', 'attachment_mime', 'created_at', 'updated_at',
        'pour_compte_de', 'pour_compte_nom', 'pour_compte_prenom',
        'nature_auteur', 'type_acte',
        // Form/export technical keys (including historical aliases).
        'report_uuid', 'site_code', 'site_nom', 'repondant_nom', 'repondant_prenom',
        'linked_emails', 'remove_attachment', 'attachment', 'attachment*',
        'action', 'csrf_token', 'registry_id', 'field_id', 'new_field_code',
        'new_field_label', 'new_field_type', 'new_field_options',
        'new_field_required', 'new_field_order', 'pour_compte',
    ];

    /** Codes kept as definitions for the legacy physical report columns. */
    public const array LEGACY_DEFINITION_CODES = [
        'pour_compte', 'pour_compte_nom', 'pour_compte_prenom',
        'nature_auteur', 'type_acte',
    ];

    public function __construct(
        private readonly RegistryFieldRepository $fields,
        private readonly RegistryRepository $registries,
    ) {}

    /**
     * Définitions des champs custom d'un registre (ordre sort_order).
     *
     * @return list<array{id: int, registry_id: int, field_code: string, label: string, field_type: string, options: ?string, is_required: int, sort_order: int, created_at: string}>
     */
    public function getDefinitions(string $registryCode): array
    {
        $registry = $this->registries->findByCode($registryCode);
        if ($registry === null) {
            return [];
        }
        return $this->fields->findByRegistry((int) $registry['id']);
    }

    /**
     * Extrait les valeurs dynamiques soumises (POST brut) pour le DTO.
     *
     * - Lit UNIQUEMENT les field_code définis (jamais un POST forgé).
     * - Exclut COMMAND_MAPPED_CODES (chemin dédié colonnes reports).
     * - Checkbox : '1' ou null (absent = décoché, null explicite).
     * - Texte : trim, '' → null (donnée absente explicite).
     * - Inclut TOUS les codes dynamiques même absents du POST (clé → null)
     *   pour que la validation et la suppression explicite fonctionnent.
     *
     * @param array<string, scalar|null> $post
     * @param list<array{id: int, registry_id: int, field_code: string, label: string, field_type: string, options: ?string, is_required: int, sort_order: int, created_at: string}> $defs
     * @return array<string, string|null>
     */
    public function extractSubmission(array $post, array $defs): array
    {
        $values = [];
        foreach ($defs as $def) {
            $code = (string) $def['field_code'];
            if (in_array($code, self::COMMAND_MAPPED_CODES, true)) {
                continue;
            }
            $raw = $post[$code] ?? null;
            $values[$code] = $this->normalize($def, $raw);
        }
        return $values;
    }

    /**
     * Validation serveur depuis le POST brut — TOUS les defs, y compris les
     * codes legacy (validés ici, persistés ailleurs).
     *
     * @param array<string, scalar|null> $post
     * @param list<array{id: int, registry_id: int, field_code: string, label: string, field_type: string, options: ?string, is_required: int, sort_order: int, created_at: string}> $defs
     * @return array<string, string> erreurs indexées par field_code
     */
    public function validateSubmission(array $post, array $defs): array
    {
        $errors = [];
        foreach ($defs as $def) {
            $code = (string) $def['field_code'];
            $normalized = $this->normalize($def, $post[$code] ?? null);
            $error = $this->errorFor($def, $normalized);
            if ($error !== null) {
                $errors[$code] = $error;
            }
        }
        return $errors;
    }

    /**
     * Validation serveur depuis la map du DTO (défense en profondeur pour
     * les appels non passés par le handler). N'évalue que les codes présents
     * dans $values (les codes legacy n'y figurent jamais).
     *
     * @param array<string, string|null> $values
     * @param list<array{id: int, registry_id: int, field_code: string, label: string, field_type: string, options: ?string, is_required: int, sort_order: int, created_at: string}> $defs
     * @return array<string, string> erreurs indexées par field_code
     */
    public function validateValues(array $values, array $defs): array
    {
        $defsByCode = [];
        foreach ($defs as $def) {
            $defsByCode[(string) $def['field_code']] = $def;
        }

        $errors = [];
        foreach ($defs as $def) {
            $code = (string) $def['field_code'];
            if (!in_array($code, self::COMMAND_MAPPED_CODES, true)
                && (int) $def['is_required'] === 1
                && !array_key_exists($code, $values)
            ) {
                $error = $this->errorFor($def, null);
                if ($error !== null) {
                    $errors[$code] = $error;
                }
            }
        }
        foreach ($values as $code => $value) {
            $def = $defsByCode[$code] ?? null;
            if ($def === null) {
                continue; // code inconnu — pas notre sujet (filtré à l'écriture)
            }
            $error = $this->errorFor($def, is_string($value) ? $value : null);
            if ($error !== null) {
                $errors[$code] = $error;
            }
        }
        return $errors;
    }

    /**
     * Filtre défensif avant écriture : retire les codes à chemin dédié et
     * les codes inconnus (jamais de double source de vérité, jamais de
     * clé hors définition — la FK composite rejetterait de toute façon).
     *
     * @param array<string, string|null> $values
     * @param list<array{id: int, registry_id: int, field_code: string, label: string, field_type: string, options: ?string, is_required: int, sort_order: int, created_at: string}> $defs
     * @return array<string, string|null>
     */
    public function filterPersistable(array $values, array $defs): array
    {
        $defsByCode = [];
        foreach ($defs as $def) {
            $defsByCode[(string) $def['field_code']] = $def;
        }

        $result = [];
        foreach ($values as $code => $value) {
            if (!isset($defsByCode[$code]) || in_array($code, self::COMMAND_MAPPED_CODES, true)) {
                continue;
            }
            $result[$code] = is_string($value) ? $value : null;
        }
        return $result;
    }

    /**
     * Formate une valeur pour l'affichage (report_card) et l'export CSV :
     * select → libellé d'option, checkbox → 'Oui'/''.
     */
    /** @param array{field_type?: string, options?: ?string, field_code?: string, label?: string} $fieldDef */
    public function formatFieldValue(array $fieldDef, ?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $fieldType = (string) ($fieldDef['field_type'] ?? 'text');
        if ($fieldType === 'checkbox') {
            return $value === '1' ? 'Oui' : '';
        }
        if ($fieldType === 'select') {
            $options = json_decode((string) ($fieldDef['options'] ?? ''), true);
            if (is_array($options) && array_key_exists($value, $options)) {
                return (string) $options[$value];
            }
        }
        return $value;
    }

    /**
     * Normalise une valeur brute : checkbox → '1'|null ; texte → trim|''→null.
     */
    /** @param array{field_type?: string} $fieldDef */
    private function normalize(array $fieldDef, mixed $raw): ?string
    {
        $fieldType = (string) ($fieldDef['field_type'] ?? 'text');
        if ($fieldType === 'checkbox') {
            return ($raw === '1' || $raw === 1 || $raw === true) ? '1' : null;
        }
        $value = trim((string) ($raw ?? ''));
        return $value !== '' ? $value : null;
    }

    /**
     * Règles de validation d'une valeur normalisée (null ou chaîne non vide).
     */
    /** @param array{field_type?: string, options?: ?string, field_code?: string, label?: string, is_required?: int} $fieldDef */
    private function errorFor(array $fieldDef, ?string $value): ?string
    {
        $label = (string) ($fieldDef['label'] ?? $fieldDef['field_code'] ?? '');
        $fieldType = (string) ($fieldDef['field_type'] ?? 'text');
        $isRequired = (int) ($fieldDef['is_required'] ?? 0) === 1;

        if ($value === null || $value === '') {
            return $isRequired ? 'Le champ « ' . $label . ' » est obligatoire.' : null;
        }

        if ($fieldType === 'select') {
            $options = json_decode((string) ($fieldDef['options'] ?? ''), true);
            if (is_array($options) && $options !== [] && !array_key_exists($value, $options)) {
                return 'La valeur du champ « ' . $label . ' » est invalide.';
            }
        }

        if ($fieldType === 'textarea' && mb_strlen($value) > self::TEXTAREA_MAX_LENGTH) {
            return 'Le champ « ' . $label . ' » ne doit pas dépasser ' . self::TEXTAREA_MAX_LENGTH . ' caractères.';
        }
        if ($fieldType !== 'textarea' && mb_strlen($value) > self::TEXT_MAX_LENGTH) {
            return 'Le champ « ' . $label . ' » ne doit pas dépasser ' . self::TEXT_MAX_LENGTH . ' caractères.';
        }

        return null;
    }
}
