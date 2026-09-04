<?php

/**
 * ExportService — Service déclaratif pour l'export CSV.
 *
 * Encapsule la logique de génération CSV :
 * - Construction des en-têtes
 * - Transformation des données
 * - Échappement CSV (injection de formules)
 * - Streaming vers la sortie
 */

namespace App\Services;

use App\Enum\ReportType;
use App\Repository\RegistryFieldRepository;
use App\Repository\RegistryRepository;
use App\Repository\StatsRepository;

class ExportService
{
    /**
     * Colonnes CSV de base (toujours présentes)
     */
    private const array BASE_COLUMNS = [
        'Référence',
        'Registre',
        'Date événement',
        'Heure dépôt',
        'Lieu',
        'Pôle',
        'Service d\'affectation',
        'Téléphone mobile',
        'Site (texte)',
        'Objet',
        'Description',
        'Déclarant (nom)',
        'Déclarant (prénom)',
    ];

    /**
     * Colonnes CSV conditionnelles (selon mode site)
     */
    private const array SITE_COLUMNS = [
        'Site (code)',
        'Site (nom)',
    ];

    /**
     * Colonnes CSV de fin (toujours présentes)
     */
    private const array FOOTER_COLUMNS = [
        'État',
        'Confidentiel',
        'Transmission FS/CSA',
        'Date création',
        'Déclaré pour le compte de',
        'Nature de l\'auteur (RAMI)',
        'Type d\'acte (RAMI)',
        'Nb réponses',
        'Dernière réponse',
        'Dernier répondant',
        'Date dernière réponse',
        'Historique réponses',
    ];

    public function __construct(
        private readonly ConfigService $config
    ) {}

    /**
     * Clés de ligne déjà émises par les colonnes standard (BASE + site +
     * FOOTER, y compris les clés agrégées : pour_compte_* → "Déclaré pour
     * le compte de", nature_auteur/type_acte → colonnes RAMI dédiées).
     * Un registry_field dont field_code figure ici ne doit PAS être dupliqué
     * en colonne dynamique (même règle que StatsQueryRepository::getExportData
     * pour le SELECT — pas de doublon en-tête/valeur).
     */
    private const array EMITTED_KEYS = [
        'reference', 'type', 'date_evenement', 'heure_evenement', 'lieu',
        'pole', 'service_affectation', 'telephone_mobile', 'site_text',
        'objet', 'description', 'declarant_nom', 'declarant_prenom',
        'site_code', 'site_nom', 'etat', 'is_confidential', 'consent_syndicat',
        'created_at', 'pour_compte_de', 'pour_compte_nom', 'pour_compte_prenom',
        'nature_auteur', 'type_acte', 'reponse', 'repondant_prenom',
        'repondant_nom', 'date_reponse',
    ];

    /**
     * Résout le code du registre qui pilote les colonnes dynamiques de
     * l'export, à partir du VRAI POST du formulaire pages/export.php.
     *
     * Fiabilisation (audit A2) : l'handler lisait $_POST['registry'], champ
     * qui n'existait dans aucun formulaire → registryCode toujours null →
     * les champs custom des registres custom n'étaient jamais exportés.
     *
     * @param array<string, string> $post Données du formulaire ($_POST)
     */
    public function resolveRegistryCodeFromPost(array $post): ?string
    {
        // Champ legacy explicite — compatibilité ascendante, prioritaire
        if (!empty($post['registry'])) {
            return (string) $post['registry'];
        }
        // Formulaire réel : un registre unique sélectionné (case
        // all_registries décochée + select type) pilote les colonnes dynamiques
        if (empty($post['all_registries']) && !empty($post['type'])) {
            return (string) $post['type'];
        }
        return null;
    }

    /**
     * Champs custom du registre à exporter en colonnes dynamiques, dans
     * l'ordre de registry_fields — EXCLUS :
     * (a) les codes déjà émis par les colonnes standard (pas de doublon),
     * (b) les codes sans colonne physique dans la table reports (oracle R1 —
     *     même filtre PRAGMA que StatsQueryRepository::getExportData() :
     *     une colonne annoncée est toujours réellement sélectionnée, jamais
     *     une colonne vide sans données),
     * avec la même sanitization de clé que getExportData() (les clés de
     * lignes correspondent exactement).
     *
     * Oracle R3 — résultat mis en cache PAR INSTANCE et PAR registre :
     * export_handler appelle cette méthode pour les en-têtes puis pour
     * CHAQUE ligne CSV — un seul calcul par registre et par requête (le
     * container réinstancie le service à chaque requête, pas de cache
     * inter-requêtes périmé).
     *
     * @return list<array{code: string, label: string}>
     */
    public function getDynamicExportFields(?string $registryCode): array
    {
        if ($registryCode === null || $registryCode === '') {
            return [];
        }
        if (isset($this->dynamicExportFieldsCache[$registryCode])) {
            return $this->dynamicExportFieldsCache[$registryCode];
        }

        $fields = $this->computeDynamicExportFields($registryCode);
        return $this->dynamicExportFieldsCache[$registryCode] = $fields;
    }

    /** @var array<string, list<array{code: string, label: string}>> */
    private array $dynamicExportFieldsCache = [];

    /**
     * @return list<array{code: string, label: string}>
     */
    private function computeDynamicExportFields(string $registryCode): array
    {
        $registry = RegistryRepository::instance()->findByCode($registryCode);
        if ($registry === null) {
            return [];
        }
        $fields = RegistryFieldRepository::instance()->findByRegistry((int) $registry['id']);
        $physicalColumns = StatsRepository::instance()->getReportPhysicalColumns();

        $dynamic = [];
        foreach ($fields as $field) {
            // Même sanitization que getExportData() — la clé de la ligne CSV
            // correspond exactement à la clé sélectionnée en SQL
            $code = (string) preg_replace('/[^a-zA-Z_]/', '', (string) $field['field_code']);
            if ($code === '' || in_array($code, self::EMITTED_KEYS, true)) {
                continue;
            }
            if (!in_array($code, $physicalColumns, true)) {
                continue;
            }
            $dynamic[] = ['code' => $code, 'label' => (string) $field['label']];
        }
        return $dynamic;
    }

    /**
     * Construit les filtres d'export depuis les données POST du formulaire d'export.
     *
     * Extrait de export_handler.php (testabilité + règle logique-métier-hors-handlers).
     *
     * `etats` est normalisé en list<string> via array_values() : le contrat de
     * StatsRepository::getExportData() et buildExportAuditContext() exige une
     * list, or un POST forgé peut contenir des clés non séquentielles
     * (ex: etats[5]=nouveau) et array_map préserve les clés d'origine.
     *
     * @param array<string, string> $post Données du formulaire ($_POST) —
     *        convention du codebase (cf. CreateReportCommand) : les valeurs
     *        multi (etats[]) sont couvertes au runtime par le cast (array).
     * @return array{type?: string, site_id?: int, declarant_id?: int, date_from?: string, date_to?: string, etats?: list<string>}
     */
    public function buildFiltersFromPost(array $post): array
    {
        $filters = [];

        // Registry type
        if (empty($post['all_registries']) && !empty($post['type'])) {
            $filters['type'] = (string) $post['type'];
        }

        // Site
        if (empty($post['all_sites']) && !empty($post['site_id'])) {
            $filters['site_id'] = (int) $post['site_id'];
        }

        // Agent (declarant)
        if (empty($post['all_agents']) && !empty($post['declarant_id'])) {
            $filters['declarant_id'] = (int) $post['declarant_id'];
        }

        // Date range
        if (!empty($post['date_from'])) {
            $filters['date_from'] = (string) $post['date_from'];
        }
        if (!empty($post['date_to'])) {
            $filters['date_to'] = (string) $post['date_to'];
        }

        // States — construit en list<string> (clés 0..n garanties par la
        // construction $etats[]) : le contrat de getExportData() et
        // buildExportAuditContext() exige une list. Un POST réel peut contenir
        // des clés non séquentielles (ex: etats[5]=nouveau) qu'un array_map
        // préserverait — l'ajout séquentiel ici les élimine.
        if (!empty($post['etats'])) {
            $etats = [];
            foreach ((array) $post['etats'] as $etatValue) {
                $etats[] = (string) $etatValue;
            }
            $filters['etats'] = $etats;
        }

        return $filters;
    }

    /**
     * Génère les en-têtes CSV.
     *
     * @param bool $noSiteMode true si mode sans site activé
     * @param string|null $registryCode Code du registre — ajoute en fin
     *        d'en-têtes les labels des champs custom non déjà émis
     * @return list<string>
     */
    public function buildHeaders(bool $noSiteMode, ?string $registryCode = null): array
    {
        $headers = self::BASE_COLUMNS;

        if (!$noSiteMode) {
            $labelUnite = $this->config->get('app_label_unite', 'UR');
            $headers[] = $labelUnite;
            $headers[] = 'Nom ' . $labelUnite;
        } else {
            // Mode sans site : on n'ajoute pas les colonnes site
            // mais on garde la constante pour référence future
            /** @phpstan-ignore-next-line */
            $siteColumns = self::SITE_COLUMNS;
        }

        $headers = array_merge($headers, self::FOOTER_COLUMNS);

        // Colonnes dynamiques du registre (champs custom, même liste et
        // même ordre que buildCsvRow → alignement en-têtes/valeurs garanti)
        foreach ($this->getDynamicExportFields($registryCode) as $field) {
            $headers[] = $field['label'];
        }

        return $headers;
    }

    /**
     * Transforme une ligne de données en ligne CSV.
     *
     * @param array<string, string> $row Données brutes depuis la BDD
     * @param list<array{id: int, report_uuid: string, user_id: int|null, reponse: string|null, nouvel_etat: string|null, attachment_blob: string|null, attachment_name: string|null, attachment_mime: string|null, created_at: string, nom: string|null, prenom: string|null}> $responses Historique des réponses pour ce signalement
     * @param bool $noSiteMode true si mode sans site activé
     * @param string|null $registryCode Code du registre — ajoute en fin de
     *        ligne les valeurs des champs custom (mêmes champs, même ordre
     *        que buildHeaders)
     * @return list<string>
     */
    public function buildCsvRow(array $row, array $responses, bool $noSiteMode, ?string $registryCode = null): array
    {
        // Construction du champ "Pour le compte de"
        $pourCompte = '';
        if (!empty($row['pour_compte_nom'])) {
            $pourCompte = trim(($row['pour_compte_prenom'] ?? '') . ' ' . $row['pour_compte_nom']);
        }

        // Labels RAMI
        $natureAuteurLabel = $this->getRegistryFieldLabel(ReportType::Rami->value, 'nature_auteur', $row['nature_auteur'] ?? '');
        $typeActeLabel = $this->getRegistryFieldLabel(ReportType::Rami->value, 'type_acte', $row['type_acte'] ?? '');

        // Historique des réponses
        $historyText = $this->buildResponseHistory($responses);

        // Ligne CSV de base
        $csvRow = [
            $this->escapeCsvField($row['reference'] ?? ''),
            $this->escapeCsvField(strtoupper($row['type'] ?? '')),
            $this->escapeCsvField($row['date_evenement'] ?? ''),
            $this->escapeCsvField($row['heure_evenement'] ?? ''),
            $this->escapeCsvField($row['lieu'] ?? ''),
            $this->escapeCsvField($row['pole'] ?? ''),
            $this->escapeCsvField($row['service_affectation'] ?? ''),
            $this->escapeCsvField($row['telephone_mobile'] ?? ''),
            $this->escapeCsvField($row['site_text'] ?? ''),
            $this->escapeCsvField($row['objet'] ?? ''),
            $this->escapeCsvField($row['description'] ?? ''),
            $this->escapeCsvField($row['declarant_nom'] ?? ''),
            $this->escapeCsvField($row['declarant_prenom'] ?? ''),
        ];

        // Colonnes conditionnelles (mode avec site)
        if (!$noSiteMode) {
            $csvRow[] = $this->escapeCsvField($row['site_code'] ?? '');
            $csvRow[] = $this->escapeCsvField($row['site_nom'] ?? '');
        }

        // Colonnes de fin
        $csvRow = array_merge($csvRow, [
            $this->escapeCsvField($this->getEtatLabel($row['etat'] ?? '')),
            !empty($row['is_confidential']) ? 'Oui' : 'Non',
            !empty($row['consent_syndicat']) ? 'Acceptée' : 'Refusée',
            $this->escapeCsvField($row['created_at'] ?? ''),
            $this->escapeCsvField($pourCompte),
            $this->escapeCsvField($natureAuteurLabel),
            $this->escapeCsvField($typeActeLabel),
            (string) count($responses),
            $this->escapeCsvField($row['reponse'] ?? ''),
            $this->escapeCsvField(trim(($row['repondant_prenom'] ?? '') . ' ' . ($row['repondant_nom'] ?? ''))),
            $this->escapeCsvField($row['date_reponse'] ?? ''),
            $this->escapeCsvField($historyText),
        ]);

        // Valeurs des champs custom du registre (mêmes champs, même ordre que
        // buildHeaders → alignement en-têtes/valeurs garanti). Les champs de
        // type select sont traduits via leurs options (même règle que les
        // colonnes RAMI standard ci-dessus).
        foreach ($this->getDynamicExportFields($registryCode) as $field) {
            $value = (string) ($row[$field['code']] ?? '');
            if ($value !== '') {
                $value = $this->getRegistryFieldLabel($registryCode ?? '', $field['code'], $value);
            }
            $csvRow[] = $this->escapeCsvField($value);
        }

        return $csvRow;
    }

    /**
     * Échappe un champ CSV pour prévenir l'injection de formules.
     *
     * - Préfixe avec ' si commence par = + @ -
     * - Remplace tabulations, retours chariot, sauts de ligne par des espaces
     */
    public function escapeCsvField(mixed $value): string
    {
        $safe = (string) $value;

        // Prévention injection de formules Excel/CSV
        if (preg_match('/^[=+\-@]/', $safe) > 0) {
            $safe = "'" . $safe;
        }

        // Nettoyage des caractères de contrôle
        $safe = str_replace(["\t", "\r", "\n"], [' ', ' ', ' '], $safe);

        return $safe;
    }

    /**
     * Construit l'historique des réponses au format texte structuré.
     *
     * Format : [Date] Répondant (État) : Réponse | [Date] ...
     *
     * @param list<array{id: int, report_uuid: string, user_id: int|null, reponse: string|null, nouvel_etat: string|null, attachment_blob: string|null, attachment_name: string|null, attachment_mime: string|null, created_at: string, nom: string|null, prenom: string|null}> $responses
     */
    public function buildResponseHistory(array $responses): string
    {
        $historyParts = [];

        foreach ($responses as $resp) {
            $date = $resp['created_at'];
            $respondent = trim(($resp['prenom'] ?? '') . ' ' . ($resp['nom'] ?? ''));
            $etat = $this->getEtatLabel($resp['nouvel_etat'] ?? '');
            $text = $resp['reponse'] ?? '';

            $historyParts[] = "[$date] $respondent ($etat) : $text";
        }

        return implode(' | ', $historyParts);
    }

    /**
     * Récupère le libellé d'un état de signalement.
     */
    private function getEtatLabel(string $etat): string
    {
        // Utilise la constante globale ETAT_LABELS si disponible
        /** @phpstan-ignore-next-line */
        return (defined('ETAT_LABELS') && isset(ETAT_LABELS[$etat])) ? ETAT_LABELS[$etat] : $etat;
    }

    /**
     * Récupère le libellé d'une option de registre.
     *
     * @param string $typeCode Code du registre (ex: 'rami')
     * @param string $fieldName Nom du champ (ex: 'nature_auteur')
     * @param string $value Valeur brute à traduire
     */
    private function getRegistryFieldLabel(string $typeCode, string $fieldName, string $value): string
    {
        if ($value === '') {
            return '';
        }

        // Utilise la fonction helper getRegistryFieldOptions si disponible
        /** @phpstan-ignore-next-line */
        $options = function_exists('getRegistryFieldOptions') ? getRegistryFieldOptions($typeCode, $fieldName) : [];

        return $options[$value] ?? $value;
    }
}
