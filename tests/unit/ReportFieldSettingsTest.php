<?php
/**
 * Report Field Settings Test — Application SST DREETS BFC
 *
 * Feature : activation + libellé des champs métier « Pôle », « Service
 * d'affectation » et « Objet », configurables PAR REGISTRE via des clés
 * config `app_field_<field>_{enabled,label}_<code>` (pattern settings).
 *
 * Couverture ciblée (rapide) :
 *  - ConfigService : défaut rétrocompatible (activé + libellés actuels) puis
 *    surcharge par registre ;
 *  - helper reportFieldLabel() : fallback sur le libellé actuel ;
 *  - formulaire de dépôt : champs affichés/masqués + libellés personnalisés ;
 *  - fiche signalement : lignes affichées/masquées + libellés personnalisés ;
 *  - handler settings (onglet registres) : persiste les clés par registre ;
 *  - export CSV : colonnes Pôle/Service retirées quand désactivées, en-tête
 *    « Objet » personnalisé, alignement en-têtes/valeurs.
 */

use PHPUnit\Framework\TestCase;
use App\DTO\SessionUser;
use App\Enum\ReportField;
use App\Services\ConfigService;
use App\Services\ExportService;

class ReportFieldSettingsTest extends TestCase
{
    private const CUSTOM_POLE_LABEL = 'Pole perso';
    private const CUSTOM_OBJET_LABEL = 'Objet du signalement';

    private static bool $bootstrapped = false;
    private static int $siteId = 952;
    private static int $agentUserId = 952;
    private static string $reportUuid = 'cccccccc-0000-0000-0000-000000000952';

    public static function setUpBeforeClass(): void
    {
        if (self::$bootstrapped) {
            return;
        }
        self::$bootstrapped = true;

        require_once __DIR__ . '/../../src/Middleware/require_role.php';
        require_once __DIR__ . '/../../src/audit.php';

        $pdo = getDB();
        $pdo->exec("INSERT OR IGNORE INTO sites (id, code, nom, is_active) VALUES (952, 'URFD', 'UR Field Test', 1)");
        $pdo->exec(
            "INSERT OR IGNORE INTO users (id, username, nom, prenom, role, site_id, is_active, email)
             VALUES (952, 'rf.test.agent', 'Field', 'Agent', 'agent', 952, 1, 'rf@dreets-bfc.gouv.fr')"
        );
        $pdo->exec(
            "INSERT OR IGNORE INTO reports
                (uuid, reference, type, objet, description, date_evenement, declarant_id,
                 declarant_nom, declarant_prenom, site_id, pole, service_affectation, etat, is_confidential)
             VALUES ('" . self::$reportUuid . "', 'RSST-25-952', 'rsst', 'Objet test', 'Description test',
                     '2025-01-01', 952, 'Field', 'Agent', 952, 'Pole test', 'Service test', 'nouveau', 0)"
        );
    }

    protected function setUp(): void
    {
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->deleteConfigKey('app_field_pole_enabled_rsst');
        $this->deleteConfigKey('app_field_pole_label_rsst');
        $this->deleteConfigKey('app_field_service_affectation_enabled_rsst');
        $this->deleteConfigKey('app_field_service_affectation_label_rsst');
        $this->deleteConfigKey('app_field_objet_label_rsst');
    }

    protected function tearDown(): void
    {
        $this->deleteConfigKey('app_field_pole_enabled_rsst');
        $this->deleteConfigKey('app_field_pole_label_rsst');
        $this->deleteConfigKey('app_field_service_affectation_enabled_rsst');
        $this->deleteConfigKey('app_field_service_affectation_label_rsst');
        $this->deleteConfigKey('app_field_objet_label_rsst');
        $_SESSION = [];
        $_GET = [];
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════════════════════

    private function deleteConfigKey(string $key): void
    {
        getDB()->exec("DELETE FROM config_app WHERE cle = " . getDB()->quote($key));
        getConfigService()->clearCache();
    }

    private function loginAsAgent(): void
    {
        setUserSession(SessionUser::fromArray([
            'id' => self::$agentUserId,
            'username' => 'rf.test.agent',
            'nom' => 'Field',
            'prenom' => 'Agent',
            'role' => 'agent',
            'site_id' => self::$siteId,
            'is_active' => 1,
        ]));
    }

    private function renderReportCreate(): string
    {
        $this->loginAsAgent();
        $_GET['page'] = 'report_create';
        $_GET['type'] = 'rsst';
        ob_start();
        renderPageWithLayout(getRouter(), 'report_create', 'test-csrf-token');
        return (string) ob_get_clean();
    }

    private function renderReportView(): string
    {
        $this->loginAsAgent();
        $_GET['page'] = 'report_view';
        $_GET['uuid'] = self::$reportUuid;
        ob_start();
        renderPageWithLayout(getRouter(), 'report_view', 'test-csrf-token');
        return (string) ob_get_clean();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // ConfigService — défauts rétrocompatibles
    // ═══════════════════════════════════════════════════════════════════════════

    public function testFieldsAreEnabledByDefault(): void
    {
        $service = new ConfigService();
        $this->assertTrue($service->isReportFieldEnabled('rsst', ReportField::Pole));
        $this->assertTrue($service->isReportFieldEnabled('rsst', ReportField::ServiceAffectation));
    }

    public function testDefaultLabelsAreCurrentOnes(): void
    {
        $service = new ConfigService();
        $this->assertSame('Pôle', $service->reportFieldLabel('rsst', ReportField::Pole));
        $this->assertSame('Service d\'affectation', $service->reportFieldLabel('rsst', ReportField::ServiceAffectation));
        $this->assertSame('Objet', $service->reportFieldLabel('rsst', ReportField::Objet));
    }

    public function testPerRegistryOverrides(): void
    {
        getConfigService()->set('app_field_pole_enabled_rsst', '0');
        getConfigService()->set('app_field_pole_label_rsst', self::CUSTOM_POLE_LABEL);
        getConfigService()->set('app_field_objet_label_rsst', self::CUSTOM_OBJET_LABEL);

        $service = new ConfigService();
        $this->assertFalse($service->isReportFieldEnabled('rsst', ReportField::Pole));
        $this->assertSame(self::CUSTOM_POLE_LABEL, $service->reportFieldLabel('rsst', ReportField::Pole));
        $this->assertSame(self::CUSTOM_OBJET_LABEL, $service->reportFieldLabel('rsst', ReportField::Objet));

        // Autre registre : non affecté (config par registre, pas globale).
        $this->assertTrue($service->isReportFieldEnabled('dgi', ReportField::Pole));
        $this->assertSame('Pôle', $service->reportFieldLabel('dgi', ReportField::Pole));
    }

    public function testHelperFallsBackToDefault(): void
    {
        $this->assertSame('Objet', reportFieldLabel('rsst', ReportField::Objet));
        getConfigService()->set('app_field_objet_label_rsst', self::CUSTOM_OBJET_LABEL);
        $this->assertSame(self::CUSTOM_OBJET_LABEL, reportFieldLabel('rsst', ReportField::Objet));
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Formulaire de dépôt (report_create)
    // ═══════════════════════════════════════════════════════════════════════════

    public function testFormShowsFieldsAndCurrentLabelsByDefault(): void
    {
        $output = $this->renderReportCreate();
        $this->assertStringContainsString('id="pole"', $output);
        $this->assertStringContainsString('id="service_affectation"', $output);
        $this->assertStringContainsString('Pôle', $output);
        $this->assertStringContainsString('Objet', $output);
    }

    public function testFormHidesDisabledFieldsButPreservesHiddenInputs(): void
    {
        getConfigService()->set('app_field_pole_enabled_rsst', '0');
        getConfigService()->set('app_field_service_affectation_enabled_rsst', '0');

        $output = $this->renderReportCreate();

        $this->assertStringNotContainsString('id="pole"', $output, 'Champ Pôle désactivé → masqué.');
        $this->assertStringNotContainsString('id="service_affectation"', $output, 'Champ Service désactivé → masqué.');
        // Les valeurs existantes restent préservées via des champs cachés (POST/DTO intacts).
        $this->assertStringContainsString('name="pole"', $output);
        $this->assertStringContainsString('name="service_affectation"', $output);
    }

    public function testFormUsesCustomLabels(): void
    {
        getConfigService()->set('app_field_pole_label_rsst', self::CUSTOM_POLE_LABEL);
        getConfigService()->set('app_field_objet_label_rsst', self::CUSTOM_OBJET_LABEL);

        $output = $this->renderReportCreate();
        $this->assertStringContainsString(self::CUSTOM_POLE_LABEL, $output);
        $this->assertStringContainsString(self::CUSTOM_OBJET_LABEL, $output);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Fiche signalement (report_card via report_view)
    // ═══════════════════════════════════════════════════════════════════════════

    public function testCardShowsFieldsAndCurrentLabelsByDefault(): void
    {
        $serviceEscaped = htmlspecialchars('Service d\'affectation', ENT_QUOTES, 'UTF-8');
        $output = $this->renderReportView();
        $this->assertStringContainsString('<th>Pôle</th>', $output);
        $this->assertStringContainsString('<th>' . $serviceEscaped . '</th>', $output);
        $this->assertStringContainsString('<th>Objet</th>', $output);
    }

    public function testCardHidesDisabledFieldsAndUsesCustomLabels(): void
    {
        $serviceEscaped = htmlspecialchars('Service d\'affectation', ENT_QUOTES, 'UTF-8');
        getConfigService()->set('app_field_pole_enabled_rsst', '0');
        getConfigService()->set('app_field_service_affectation_enabled_rsst', '0');
        getConfigService()->set('app_field_objet_label_rsst', self::CUSTOM_OBJET_LABEL);

        $output = $this->renderReportView();
        $this->assertStringNotContainsString('<th>Pôle</th>', $output);
        $this->assertStringNotContainsString('<th>' . $serviceEscaped . '</th>', $output);
        $this->assertStringContainsString(self::CUSTOM_OBJET_LABEL, $output);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Export CSV — alignement en-têtes/valeurs + masquage + libellés
    // ═══════════════════════════════════════════════════════════════════════════

    public function testExportDefaultsKeepCurrentColumns(): void
    {
        $service = new ExportService(getContainer()->get(ConfigService::class));
        $headers = $service->buildHeaders(false, 'rsst');
        $this->assertContains('Pôle', $headers);
        $this->assertContains('Service d\'affectation', $headers);
        $this->assertContains('Objet', $headers);
    }

    public function testExportDropsDisabledColumnsAndUsesCustomLabel(): void
    {
        getConfigService()->set('app_field_pole_enabled_rsst', '0');
        getConfigService()->set('app_field_service_affectation_enabled_rsst', '0');
        getConfigService()->set('app_field_objet_label_rsst', self::CUSTOM_OBJET_LABEL);

        $service = new ExportService(getContainer()->get(ConfigService::class));
        $headers = $service->buildHeaders(false, 'rsst');
        $row = $service->buildCsvRow([], [], false, 'rsst');

        $this->assertNotContains('Pôle', $headers);
        $this->assertNotContains('Service d\'affectation', $headers);
        $this->assertContains(self::CUSTOM_OBJET_LABEL, $headers);
        $this->assertSame(count($headers), count($row), 'En-têtes et valeurs restent alignés.');
    }

    public function testExportAllRegistriesKeepsDefaultColumns(): void
    {
        // Config par registre ne doit pas altérer un export multi-registres.
        getConfigService()->set('app_field_pole_enabled_rsst', '0');
        $service = new ExportService(getContainer()->get(ConfigService::class));
        $headers = $service->buildHeaders(false, null);
        $this->assertContains('Pôle', $headers);
        $this->assertContains('Objet', $headers);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Handler settings — persistance par registre
    // ═══════════════════════════════════════════════════════════════════════════

    public function testSettingsRegistresHandlerPersistsFieldSettings(): void
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
                    '601' => [
                        'label' => 'Registre test champs',
                        'short_label' => 'TCF',
                        'description' => '',
                        'sort_order' => 51,
                        'default_visibility' => 'agent_choice',
                        'legal_note' => '',
                        // Pôle désactivé (case décochée = absente), libellé perso ;
                        // service activé, libellé vide (revient au défaut) ;
                        // objet renommé.
                        'field_pole_label' => self::CUSTOM_POLE_LABEL,
                        'field_service_affectation_enabled' => '1',
                        'field_objet_label' => self::CUSTOM_OBJET_LABEL,
                    ],
                ],
            ],
            'db_seed' => "INSERT INTO registries (id, code, label, short_label, description, icon, color_theme, is_enabled, is_system, sort_order, default_visibility, notify_chsct) "
                . "VALUES (601, 'rf_registre', 'Registre test champs', 'TCF', '', '📋', 'vert', 1, 0, 51, 'agent_choice', 0);",
            'assertions' => [
                'pole_enabled' => "SELECT valeur FROM config_app WHERE cle = 'app_field_pole_enabled_rf_registre'",
                'pole_label' => "SELECT valeur FROM config_app WHERE cle = 'app_field_pole_label_rf_registre'",
                'service_enabled' => "SELECT valeur FROM config_app WHERE cle = 'app_field_service_affectation_enabled_rf_registre'",
                'service_label' => "SELECT valeur FROM config_app WHERE cle = 'app_field_service_affectation_label_rf_registre'",
                'objet_label' => "SELECT valeur FROM config_app WHERE cle = 'app_field_objet_label_rf_registre'",
            ],
        ]);

        $this->assertNotNull($result['redirect'], 'Le handler doit rediriger (pas de fatal).');
        $this->assertSame('0', $result['queries']['pole_enabled'], 'Case décochée absente du POST → Pôle désactivé.');
        $this->assertSame(self::CUSTOM_POLE_LABEL, $result['queries']['pole_label']);
        $this->assertSame('1', $result['queries']['service_enabled']);
        $this->assertSame('', $result['queries']['service_label'], 'Libellé vide → fallback défaut (non figé).');
        $this->assertSame(self::CUSTOM_OBJET_LABEL, $result['queries']['objet_label']);
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