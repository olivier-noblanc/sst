<?php

/** ConfigService — Configuration read/write, cache management, version detection. */

namespace App\Services;

use App\Enum\ReportField;
use App\Enum\UserRole;
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
     */
    public function setMany(array $values): void
    {
        ConfigRepository::instance()->setMany($values);
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
     * Texte explicatif du niveau de confidentialité, personnalisable PAR REGISTRE.
     *
     * Clé : `app_confidentiality_note_<code>` (code du registre, ex. `rsst`,
     * `ami`…). La méthode renvoie '' quand rien n'est défini : c'est l'appelant
     * qui fournit alors le texte par défaut actuel (fallback), afin de ne jamais
     * figer le défaut ici (il dépend du mode de visibilité et des libellés
     * configurables de rôle / d'unité).
     */
    public function getConfidentialityNote(string $registryCode): string
    {
        return $this->get('app_confidentiality_note_' . $registryCode, '');
    }

    /**
     * Texte par défaut actuel — mode « Choix de l'agent » (case à cocher).
     *
     * Libellé de rôle et libellé d'unité configurables : construits ici pour
     * ne pas dupliquer la phrase entre le formulaire et l'écran d'admin.
     */
    public function confidentialityNoteDefaultAgentChoice(): string
    {
        $roleLabel = $this->getRoleLabelShort(UserRole::Chsct->value);
        $unitLabel = $this->get('app_label_unite', 'UR');

        return 'Si coché, ce signalement ne sera visible que par vous, les superviseurs '
            . 'et les membres du rôle « ' . $roleLabel . ' ». L\'accès des membres du rôle '
            . 'ne dépend jamais du consentement syndical. Décochez pour le rendre visible '
            . 'par tous les agents de votre ' . $unitLabel . '.';
    }

    /**
     * Texte par défaut actuel — mode « Confidentiel » (niveau imposé).
     */
    public function confidentialityNoteDefaultConfidential(): string
    {
        $roleLabel = $this->getRoleLabelShort(UserRole::Chsct->value);

        return 'Le mode de visibilité est « Confidentiel » : votre signalement n\'est visible '
            . 'que par vous, les superviseurs et les membres du rôle « ' . $roleLabel . ' ». '
            . 'L\'accès des membres du rôle ne dépend jamais du consentement syndical.';
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
     * Clé `app_consent_syndicat_enabled` : '1' (défaut, rétrocompatible) ou
     * '0'. Lorsqu'elle est désactivée, la case du formulaire de dépôt, la
     * ligne de transmission de la fiche (et du PDF) ainsi que la colonne
     * correspondante des exports CSV disparaissent. La valeur déjà
     * enregistrée en base reste conservée (elle n'est jamais réinitialisée).
     */
    public function isConsentSyndicatEnabled(): bool
    {
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
