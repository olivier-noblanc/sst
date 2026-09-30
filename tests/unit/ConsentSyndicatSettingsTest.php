<?php
/**
 * Consent Syndicat Settings Test — Application SST DREETS BFC
 *
 * Feature : l'affichage de la case de consentement de transmission syndicale
 * est paramétrable PAR REGISTRE (clé config `app_consent_syndicat_enabled_<code>`,
 * checkbox « Afficher la case de consentement de transmission aux organisations
 * syndicales » de l'onglet Registres), avec repli sur l'interrupteur global
 * `app_consent_syndicat_enabled` (onglet Application) pour la rétrocompatibilité.
 *
 * Les deux cases ne doivent pas être confondues :
 *  - réglage admin  : `registres[<id>][consent_syndicat_enabled]` (onglet Registres) ;
 *  - case du dépôt  : `consent_syndicat` (templates/report_form.php), affichée
 *    uniquement si l'affichage est activé, sinon champ caché préservant la valeur.
 *
 * Couverture ciblée (rapide) : ConfigService + rendu formulaire + rendu admin +
 * persistance handler.
 */

use PHPUnit\Framework\TestCase;
use App\DTO\SessionUser;

class ConsentSyndicatSettingsTest extends TestCase
{
    private const GLOBAL_KEY = 'app_consent_syndicat_enabled';
    private const RSST_KEY   = 'app_consent_syndicat_enabled_rsst';

    private static bool $bootstrapped = false;
    private static int $siteId = 953;
    private static int $agentUserId = 953;

    private ?string $previousGlobal = null;
    private ?string $previousRsst = null;

    public static function setUpBeforeClass(): void
    {
        if (self::$bootstrapped) {
            return;
        }
        self::$bootstrapped = true;

        require_once __DIR__ . '/../../src/Middleware/require_role.php';
        require_once __DIR__ . '/../../src/audit.php';

        $pdo = getDB();
        $pdo->exec("INSERT OR IGNORE INTO sites (id, code, nom, is_active) VALUES (953, 'URCS', 'UR Consent Test', 1)");
        $pdo->exec(
            "INSERT OR IGNORE INTO users (id, username, nom, prenom, role, site_id, is_active, email)
             VALUES (953, 'cs.test.agent', 'Consent', 'Agent', 'agent', 953, 1, 'cs@dreets-bfc.gouv.fr')"
        );
    }

    protected function setUp(): void
    {
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->previousGlobal = getConfigService()->get(self::GLOBAL_KEY, '');
        $this->previousRsst = getConfigService()->get(self::RSST_KEY, '');
        $this->deleteConfigKey(self::GLOBAL_KEY);
        $this->deleteConfigKey(self::RSST_KEY);
    }

    protected function tearDown(): void
    {
        $this->restoreConfigKey(self::GLOBAL_KEY, $this->previousGlobal);
        $this->restoreConfigKey(self::RSST_KEY, $this->previousRsst);
        $_SESSION = [];
        $_GET = [];
    }

    private function deleteConfigKey(string $key): void
    {
        getDB()->exec("DELETE FROM config_app WHERE cle = " . getDB()->quote($key));
        getConfigService()->clearCache();
    }

    private function restoreConfigKey(string $key, ?string $value): void
    {
        if ($value !== null && $value !== '') {
            getConfigService()->set($key, $value);
        } else {
            $this->deleteConfigKey($key);
        }
    }

    private function loginAsAgent(): void
    {
        setUserSession(SessionUser::fromArray([
            'id' => self::$agentUserId,
            'username' => 'cs.test.agent',
            'nom' => 'Consent',
            'prenom' => 'Agent',
            'role' => 'agent',
            'site_id' => self::$siteId,
            'is_active' => 1,
        ]));
    }

    private function renderReportCreate(string $type = 'rsst'): string
    {
        $this->loginAsAgent();
        $_GET['page'] = 'report_create';
        $_GET['type'] = $type;
        ob_start();
        renderPageWithLayout(getRouter(), 'report_create', 'test-csrf-token');
        return (string) ob_get_clean();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // ConfigService — global puis surcharge par registre
    // ═══════════════════════════════════════════════════════════════════════════

    public function testEnabledByDefaultAndGlobalOff(): void
    {
        $service = new \App\Services\ConfigService();
        $this->assertTrue($service->isConsentSyndicatEnabled(), 'Défaut rétrocompatible : activé.');

        getConfigService()->set(self::GLOBAL_KEY, '0');
        $service = new \App\Services\ConfigService();
        $this->assertFalse($service->isConsentSyndicatEnabled());
        $this->assertFalse($service->isConsentSyndicatEnabled('rsst'), 'Sans clé par registre : repli sur le global.');
    }

    public function testPerRegistryOverrideIsIndependent(): void
    {
        getConfigService()->set(self::RSST_KEY, '0');
        $service = new \App\Services\ConfigService();

        $this->assertFalse($service->isConsentSyndicatEnabled('rsst'), 'Le registre rsst est désactivé.');
        $this->assertTrue($service->isConsentSyndicatEnabled('dgi'), 'Les autres registres ne sont pas affectés.');
        $this->assertTrue($service->isConsentSyndicatEnabled(), 'Le global reste activé.');
    }

    public function testPerRegistryCanEnableOverGlobalOff(): void
    {
        getConfigService()->set(self::GLOBAL_KEY, '0');
        getConfigService()->set(self::RSST_KEY, '1');
        $service = new \App\Services\ConfigService();

        $this->assertTrue($service->isConsentSyndicatEnabled('rsst'));
        $this->assertFalse($service->isConsentSyndicatEnabled('dgi'));
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Formulaire de dépôt — case utilisateur `consent_syndicat`
    // ═══════════════════════════════════════════════════════════════════════════

    public function testFormShowsUserCheckboxByDefault(): void
    {
        $output = $this->renderReportCreate();
        $this->assertStringContainsString('id="consent_syndicat"', $output, 'La case de dépôt est affichée par défaut.');
        $this->assertStringContainsString('name="consent_syndicat"', $output);
    }

    public function testGlobalOffHidesUserCheckboxButKeepsHiddenValue(): void
    {
        getConfigService()->set(self::GLOBAL_KEY, '0');
        $output = $this->renderReportCreate();

        $this->assertStringNotContainsString('id="consent_syndicat"', $output);
        $this->assertStringContainsString('name="consent_syndicat"', $output, 'Un champ caché préserve la valeur.');
    }

    public function testPerRegistryOffHidesUserCheckboxOnlyForThatRegistry(): void
    {
        getConfigService()->set(self::RSST_KEY, '0');
        $output = $this->renderReportCreate('rsst');

        $this->assertStringNotContainsString('id="consent_syndicat"', $output, 'Le registre rsst masque la case.');
        $this->assertStringContainsString('name="consent_syndicat"', $output, 'Le champ caché préserve la valeur.');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Onglet Registres — checkbox de configuration (à ne pas confondre)
    // ═══════════════════════════════════════════════════════════════════════════

    public function testAdminTabExposesPerRegistryConsentCheckbox(): void
    {
        $id = (int) getDB()->query("SELECT id FROM registries WHERE code = 'rsst'")->fetchColumn();
        $this->assertGreaterThan(0, $id, 'Le registre rsst doit exister dans la base de test.');

        $csrfToken = 'test-csrf-token';
        ob_start();
        require __DIR__ . '/../../pages/settings/tab_registres.php';
        $html = (string) ob_get_clean();

        $this->assertStringContainsString(
            'name="registres[' . $id . '][consent_syndicat_enabled]"',
            $html,
            'L\'onglet Registres doit exposer la checkbox de configuration par registre.'
        );
        $this->assertStringContainsString(
            'Afficher la case de consentement de transmission aux organisations syndicales',
            $html,
            'Le libellé explicite doit être présent.'
        );
        // Ne pas confondre avec la case du dépôt.
        $this->assertStringNotContainsString('name="consent_syndicat"', $html);
    }

    public function testAdminTabExposesThreeConfidentialityNoteZones(): void
    {
        $id = (int) getDB()->query("SELECT id FROM registries WHERE code = 'rsst'")->fetchColumn();
        $csrfToken = 'test-csrf-token';
        ob_start();
        require __DIR__ . '/../../pages/settings/tab_registres.php';
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('name="registres[' . $id . '][confidentiality_note_confidential]"', $html);
        $this->assertStringContainsString('name="registres[' . $id . '][confidentiality_note_agent_choice]"', $html);
        $this->assertStringContainsString('name="registres[' . $id . '][confidentiality_note_public]"', $html);
        $this->assertStringNotContainsString(
            'affiché près du niveau « Confidentiel »',
            $html,
            'L\'ancien libellé générique illogique doit être supprimé.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Handler — persistance par registre
    // ═══════════════════════════════════════════════════════════════════════════

    public function testSettingsRegistresHandlerPersistsPerRegistryConsent(): void
    {
        $token = bin2hex(random_bytes(32));
        $session = [
            'user' => [
                'id' => 2, 'nom' => 'Sup', 'prenom' => 'Visor',
                'username' => 'superviseur.test', 'role' => 'superviseur',
                'site_id' => 1, 'site_code' => 'UD21',
                'email' => 'superviseur.test@dreets-bfc.gouv.fr', 'is_active' => 1,
            ],
            'csrf_tokens' => [$token => time()],
        ];

        $result = $this->runHandler([
            'handler' => 'settings_handler.php',
            'session' => $session,
            'post' => [
                'csrf_token' => $token,
                'tab' => 'registres',
                'registres' => [
                    '602' => [
                        'label' => 'Registre consent ON',
                        'short_label' => 'RCO',
                        'description' => '',
                        'sort_order' => 52,
                        'default_visibility' => 'agent_choice',
                        'legal_note' => '',
                        'consent_syndicat_enabled' => '1',
                    ],
                    '603' => [
                        'label' => 'Registre consent OFF',
                        'short_label' => 'RCX',
                        'description' => '',
                        'sort_order' => 53,
                        'default_visibility' => 'agent_choice',
                        'legal_note' => '',
                        // Case décochée = absente du POST.
                    ],
                ],
            ],
            'db_seed' => "INSERT INTO registries (id, code, label, short_label, description, icon, color_theme, is_enabled, is_system, sort_order, default_visibility, notify_chsct) "
                . "VALUES (602, 'cs_on_registre', 'Registre consent ON', 'RCO', '', '📋', 'vert', 1, 0, 52, 'agent_choice', 0);"
                . "\nINSERT INTO registries (id, code, label, short_label, description, icon, color_theme, is_enabled, is_system, sort_order, default_visibility, notify_chsct) "
                . "VALUES (603, 'cs_off_registre', 'Registre consent OFF', 'RCX', '', '📋', 'vert', 1, 0, 53, 'agent_choice', 0);",
            'assertions' => [
                'on_value' => "SELECT valeur FROM config_app WHERE cle = 'app_consent_syndicat_enabled_cs_on_registre'",
                'off_value' => "SELECT valeur FROM config_app WHERE cle = 'app_consent_syndicat_enabled_cs_off_registre'",
            ],
        ]);

        $this->assertNotNull($result['redirect'], 'Le handler doit rediriger (pas de fatal).');
        $this->assertSame('1', $result['queries']['on_value'], 'Case cochée → activé.');
        $this->assertSame('0', $result['queries']['off_value'], 'Case décochée absente → désactivé.');
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function runHandler(array $config): array
    {
        $configPath = tempnam(sys_get_temp_dir(), 'sst_cfg_') . '.json';
        file_put_contents($configPath, json_encode($config));

        $cmd = 'php ' . escapeshellarg(__DIR__ . '/../handler_runner.php') . ' ' . escapeshellarg($configPath);
        exec($cmd . ' 2>NUL', $output, $exitCode);

        unlink($configPath);

        $json = implode("\n", $output);
        $parsed = json_decode($json, true);
        $this->assertNotNull($parsed, "JSON invalide du handler runner (fatal probable) : $json");

        return $parsed;
    }
}