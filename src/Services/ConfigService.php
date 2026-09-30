<?php

/** ConfigService — Configuration read/write, cache management, version detection. */

namespace App\Services;

use App\Enum\ReportField;
use App\Enum\UserRole;
use App\Enum\VisibilityMode;
use App\Repository\ConfigRepository;
use App\Repository\RegistryRepository;
use App\Repository\SiteRepository;

class ConfigService
{
    /** @var array<string, string> */
    private array $cache = [];
    private bool $cacheCleared = false;

    /**
     * Get a configuration value from the config_app table.
     */
    public function get(string $cle, string $default = ''): string
    {
        if ($this->cacheCleared) {
            $this->cache = [];
            $this->cacheCleared = false;
        }
        if (isset($this->cache[$cle])) {
            return $this->cache[$cle];
        }
        $value = ConfigRepository::instance()->get($cle);
        $value = ($value !== null && $value !== '') ? $value : $default;
        $this->cache[$cle] = $value;
        return $value;
    }

    /**
     * Update (or insert) a configuration value in the config_app table.
     */
    public function set(string $cle, string $valeur): void
    {
        ConfigRepository::instance()->set($cle, $valeur);
        $this->clearCache();
    }

    /**
     * Écrit plusieurs clés dans UNE transaction (tout ou rien), puis
     * invalide le cache.
     *
     * Fiabilisation (council) — remplace les séries de set() des onglets de
     * paramétrage pour empêcher toute persistance partielle.
     *
     * @param array<string, string> $values
     * @param list<string>          $deletions Clés à supprimer dans la même transaction
     */
    public function setMany(array $values, array $deletions = []): void
    {
        ConfigRepository::instance()->setMany($values, $deletions);
        $this->clearCache();
    }

    /**
     * Clear the config cache.
     */
    public function clearCache(): void
    {
        $this->cacheCleared = true;
        $GLOBALS['_config_cache_cleared'] = true;
    }

    /**
     * Check if a registry type is enabled.
     * Reads from the registries table instead of hardcoded constants.
     */
    public function isRegistryEnabled(string $type): bool
    {
        $reg = RegistryRepository::instance()->findByCode($type);
        return $reg !== null && (int) $reg['is_enabled'] === 1;
    }

    /**
     * Get the list of enabled registry codes.
     * @return list<string>
     */
    public function getEnabledRegistries(): array
    {
        return array_column(RegistryRepository::instance()->findEnabled(), 'code');
    }

    /**
     * Get the customizable label for a role.
     */
    public function getRoleLabel(string $role): string
    {
        $dbKey = 'app_role_label_' . $role;
        $dbValue = $this->get($dbKey, '');
        if ($dbValue !== '') {
            return $dbValue;
        }
        return UserRole::tryFrom($role)?->defaultLabel() ?? ucfirst($role);
    }

    /**
     * Get all role labels (customized or default).
     * @return array<string, string>
     */
    public function getRoleLabels(): array
    {
        return array_combine(
            array_map(fn($c) => $c->value, UserRole::cases()),
            array_map(fn($c) => $this->getRoleLabel($c->value), UserRole::cases())
        );
    }

    /**
     * Get the short/customary name for a role (without "Membre" prefix).
     */
    public function getRoleLabelShort(string $role): string
    {
        $label = $this->getRoleLabel($role);
        $prefixes = ['Membre ', 'membre '];
        foreach ($prefixes as $prefix) {
            if (stripos($label, $prefix) === 0) {
                return substr($label, strlen($prefix));
            }
        }
        return $label;
    }

    /**
     * Clé de configuration du texte explicatif d'un niveau de visibilité pour
     * un registre donné : `app_confidentiality_note_<niveau>_<code>`.
     *
     * Le niveau est la valeur de l'enum VisibilityMode (public, agent_choice,
     * confidential) : les textes sont donc paramétrables PAR REGISTRE **et**
     * PAR NIVEAU RÉEL (et non plus rattachés arbitrairement au seul niveau
     * Confidentiel).
     */
    public function confidentialityNoteKey(string $registryCode, VisibilityMode $mode): string
    {
        return 'app_confidentiality_note_' . $mode->value . '_' . $registryCode;
    }

    /**
     * Ancienne clé générique `app_confidentiality_note_<code>` (un seul texte
     * pour tous les niveaux). Conservée en LECTURE comme repli rétrocompatible ;
     * elle est migrée puis supprimée lors de l'enregistrement de l'onglet
     * Registres.
     */
    public function legacyConfidentialityNoteKey(string $registryCode): string
    {
        return 'app_confidentiality_note_' . $registryCode;
    }

    /**
     * Texte personnalisé ('' si aucun défini) : spécifique au niveau s'il
     * existe, sinon ancienne clé générique (rétrocompatibilité). Aucun défaut
     * ici — le défaut est contextuel (cf. getConfidentialityNote()).
     */
    public function getConfidentialityNoteCustom(string $registryCode, VisibilityMode $mode): string
    {
        $override = $this->get($this->confidentialityNoteKey($registryCode, $mode), '');
        if ($override !== '') {
            return $override;
        }
        return $this->get($this->legacyConfidentialityNoteKey($registryCode), '');
    }

    /**
     * Texte explicatif effectif d'un niveau de visibilité pour un registre.
     *
     * Ordre de résolution : texte personnalisé (niveau puis ancienne clé) →
     * défaut contextuel fourni par l'appelant → défaut du niveau. Le défaut
     * dépend de libellés configurables (rôle, unité) : il est construit ici
     * pour ne jamais être figé en base ni dupliqué entre le formulaire et
     * l'écran d'administration.
     */
    public function getConfidentialityNote(string $registryCode, VisibilityMode $mode, ?string $default = null): string
    {
        $custom = $this->getConfidentialityNoteCustom($registryCode, $mode);
        if ($custom !== '') {
            return $custom;
        }
        return $default ?? $this->confidentialityNoteDefault($mode);
    }

    /**
     * Texte par défaut (rétrocompatible ou approprié) d'un niveau de visibilité.
     */
    public function confidentialityNoteDefault(VisibilityMode $mode): string
    {
        $roleLabel = $this->getRoleLabelShort(UserRole::Chsct->value);
        $unitLabel = $this->get('app_label_unite', 'UR');

        return match ($mode) {
            VisibilityMode::Confidential => 'Le mode de visibilité est « Confidentiel » : votre signalement n\'est visible '
                . 'que par vous, les superviseurs et les membres du rôle « ' . $roleLabel . ' ». '
                . 'L\'accès des membres du rôle ne dépend jamais du consentement syndical.',
            VisibilityMode::AgentChoice => 'Si coché, ce signalement ne sera visible que par vous, les superviseurs '
                . 'et les membres du rôle « ' . $roleLabel . ' ». L\'accès des membres du rôle '
                . 'ne dépend jamais du consentement syndical. Décochez pour le rendre visible '
                . 'par tous les agents de votre ' . $unitLabel . '.',
            VisibilityMode::Public => 'Ce signalement est visible par tous les agents de votre ' . $unitLabel . '.',
        };
    }

    /**
     * Libellé invariable de la ligne/colonne « Transmission » du signalement.
     *
     * Forme figée « Transmission — {libellé du rôle Chsct} » : aucun
     * « s » de pluriel concaténé (le libellé de rôle est configurable et
     * peut ne pas être un nom commun) et aucun « CHSCT » en dur.
     */
    public function transmissionLabel(): string
    {
        return 'Transmission — ' . $this->getRoleLabel(UserRole::Chsct->value);
    }

    /**
     * Indique si la case de consentement de transmission syndicale
     * (« J'accepte que mon signalement soit transmis aux organisations
     * syndicales représentatives ») doit être affichée dans l'application.
     *
     * Sans argument → interrupteur GLOBAL `app_consent_syndicat_enabled`
     * (onglet Application) : '1' (défaut, rétrocompatible) ou '0'.
     *
     * Avec un code de registre → réglage PAR REGISTRE
     * `app_consent_syndicat_enabled_<code>` (onglet Registres) ; s'il n'est pas
     * défini, repli sur l'interrupteur global (rétrocompatibilité : les
     * registres existants gardent le comportement global tant qu'ils ne sont
     * pas personnalisés).
     *
     * Lorsqu'elle est désactivée, la case du formulaire de dépôt, la ligne de
     * transmission de la fiche (et du PDF) ainsi que la colonne correspondante
     * des exports CSV disparaissent. La valeur déjà enregistrée en base reste
     * conservée (elle n'est jamais réinitialisée).
     */
    public function isConsentSyndicatEnabled(?string $registryCode = null): bool
    {
        if ($registryCode !== null && $registryCode !== '') {
            $perRegistry = $this->get('app_consent_syndicat_enabled_' . $registryCode, '');
            if ($perRegistry !== '') {
                return $perRegistry === '1';
            }
        }
        return $this->get('app_consent_syndicat_enabled', '1') === '1';
    }

    /**
     * Activation d'un champ métier du formulaire, configurable PAR REGISTRE.
     *
     * Clé `app_field_<field>_enabled_<code>` : '1' (défaut, rétrocompatible)
     * ou '0'. Lorsqu'un champ est désactivé, il est masqué dans le dépôt,
     * l'édition, la lecture et l'export. Les valeurs déjà enregistrées restent
     * conservées en base (elles ne sont jamais réinitialisées).
     */
    public function isReportFieldEnabled(string $registryCode, ReportField $field): bool
    {
        return $this->get('app_field_' . $field->value . '_enabled_' . $registryCode, '1') === '1';
    }

    /**
     * Valeur brute du libellé personnalisé d'un champ métier ('' si non défini).
     *
     * Clé `app_field_<field>_label_<code>` — l'écran d'admin affiche cette
     * valeur brute (avec le défaut en placeholder) pour qu'un libellé vide
     * reste vide au lieu d'être figé sur le défaut.
     */
    public function reportFieldLabelOverride(string $registryCode, ReportField $field): string
    {
        return $this->get('app_field_' . $field->value . '_label_' . $registryCode, '');
    }

    /**
     * Libellé effectif d'un champ métier : personnalisé par registre, sinon
     * libellé par défaut actuel (rétrocompatible — « Pôle », « Service
     * d'affectation », « Objet »).
     */
    public function reportFieldLabel(string $registryCode, ReportField $field): string
    {
        $override = $this->reportFieldLabelOverride($registryCode, $field);
        return $override !== '' ? $override : $field->defaultLabel();
    }

    /**
     * Check if there are any active sites in the system.
     */
    public function hasActiveSites(): bool
    {
        return SiteRepository::instance()->countActiveSites() > 0;
    }

    /**
     * Check if the application is in "no-site" mode (zero active sites).
     */
    public function isNoSiteMode(): bool
    {
        static $cache = null;
        if ($cache === null || !empty($GLOBALS['_config_cache_cleared'])) {
            $GLOBALS['_config_cache_cleared'] = false;
            $cache = SiteRepository::instance()->countActiveSites() === 0;
        }
        return $cache;
    }

    /**
     * Get the count of active sites.
     */
    public function countActiveSites(): int
    {
        return SiteRepository::instance()->countActiveSites();
    }

    /**
     * Get the application version from CHANGELOG.md.
     */
    public function getAppVersion(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $candidatePaths = [];

        if (defined('CHANGELOG_PATH')) {
            $candidatePaths[] = CHANGELOG_PATH;
        }
        $candidatePaths[] = dirname(__DIR__, 2) . '/CHANGELOG.md';
        $candidatePaths[] = dirname(__DIR__) . '/CHANGELOG.md';
        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            /** @var string */
            $docRoot = $_SERVER['DOCUMENT_ROOT'];
            $candidatePaths[] = rtrim($docRoot, '/\\') . '/../CHANGELOG.md';
        }
        if (!empty($_SERVER['SCRIPT_FILENAME'])) {
            /** @var string */
            $scriptFilename = $_SERVER['SCRIPT_FILENAME'];
            $candidatePaths[] = dirname($scriptFilename, 2) . '/CHANGELOG.md';
        }

        foreach ($candidatePaths as $path) {
            $resolved = realpath($path);
            $path = $resolved !== false ? $resolved : $path;
            if (is_readable($path)) {
                $content = file_get_contents($path);
                if (($content !== false && $content !== '') && preg_match('/^##\s*\[(\d+\.\d+\.\d+)\]/m', $content, $m) === 1) {
                    $cached = $m[1];
                    return $cached;
                }
            }
        }

        $cached = defined('APP_VERSION') ? APP_VERSION : '0.0.0';
        return $cached;
    }
}
