<?php
/**
 * Confidentiality Note Test — Application SST DREETS BFC
 *
 * Feature : la phrase explicative du niveau de confidentialité est
 * paramétrable PAR REGISTRE (clé config `app_confidentiality_note_<code>`,
 * stockée dans config_app via ConfigService/ConfigRepository).
 *
 * Couverture ciblée (rapide) :
 *  - ConfigService::getConfidentialityNote() : '' si non défini, valeur si défini
 *  - helper confidentialityNote() : fallback sur le défaut actuel quand vide
 *  - formulaire de dépôt (report_create) : défaut puis texte admin
 *  - fiche signalement (report_card via report_view) : défaut puis texte admin
 *  - handler settings (onglet registres) : persiste la clé par registre
 */

use PHPUnit\Framework\TestCase;
use App\DTO\SessionUser;
use App\Services\ConfigService;

class ConfidentialityNoteTest extends TestCase
{
    private const CONFIG_KEY_NOTE = 'app_confidentiality_note_rsst';
    private const CONFIG_KEY_VISIBILITY = 'app_report_visibility_rsst';
    private const CUSTOM_NOTE = 'NOTE-PERSO-CONFIDENTIALITE';

    private static bool $bootstrapped = false;
    private static int $siteId = 951;
    private static int $agentUserId = 951;
    private static string $reportUuid = 'cccccccc-0000-0000-0000-000000000951';

    private ?string $previousVisibility = null;

    public static function setUpBeforeClass(): void
    {
        if (self::$bootstrapped) {
            return;
        }
        self::$bootstrapped = true;

        // Layout helpers not loaded by the bootstrap's autoloader alone
        // (footer.php uses hasRole(), audit helpers used by the router).
        require_once __DIR__ . '/../../src/Middleware/require_role.php';
        require_once __DIR__ . '/../../src/audit.php';

        $pdo = getDB();
        $pdo->exec("INSERT OR IGNORE INTO sites (id, code, nom, is_active) VALUES (951, 'URCN', 'UR Note Test', 1)");
        $pdo->exec(
            "INSERT OR IGNORE INTO users (id, username, nom, prenom, role, site_id, is_active, email)
             VALUES (951, 'cn.test.agent', 'Note', 'Agent', 'agent', 951, 1, 'cn@dreets-bfc.gouv.fr')"
        );
        $pdo->exec(
            "INSERT OR IGNORE INTO reports
                (uuid, reference, type, objet, description, date_evenement, declarant_id,
                 declarant_nom, declarant_prenom, site_id, etat, is_confidential)
             VALUES ('" . self::$reportUuid . "', 'RSST-25-951', 'rsst', 'Test note', 'Description note',
                     '2025-01-01', 951, 'Note', 'Agent', 951, 'nouveau', 1)"
        );
    }

    protected function setUp(): void
    {
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->previousVisibility = getConfigService()->get(self::CONFIG_KEY_VISIBILITY, '');
        $this->deleteConfigKey(self::CONFIG_KEY_NOTE);
        $this->deleteConfigKey(self::CONFIG_KEY_VISIBILITY);
    }

    protected function tearDown(): void
    {
        $this->deleteConfigKey(self::CONFIG_KEY_NOTE);
        if ($this->previousVisibility !== null && $this->previousVisibility !== '') {
            getConfigService()->set(self::CONFIG_KEY_VISIBILITY, $this->previousVisibility);
        } else {
            $this->deleteConfigKey(self::CONFIG_KEY_VISIBILITY);
        }
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
            'username' => 'cn.test.agent',
            'nom' => 'Note',
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
    // ConfigService::getConfidentialityNote()
    // ═══════════════════════════════════════════════════════════════════════════

    public function testGetConfidentialityNoteReturnsEmptyWhenUnset(): void
    {
        $service = new ConfigService();
        $this->assertSame('', $service->getConfidentialityNote('rsst'));
    }

    public function testGetConfidentialityNoteReturnsStoredValue(): void
    {
        getConfigService()->set(self::CONFIG_KEY_NOTE, self::CUSTOM_NOTE);
        $service = new ConfigService();
        $this->assertSame(self::CUSTOM_NOTE, $service->getConfidentialityNote('rsst'));
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Helper confidentialityNote() — fallback sur le défaut actuel
    // ═══════════════════════════════════════════════════════════════════════════

    public function testHelperReturnsDefaultWhenUnset(): void
    {
        $this->assertSame('DEFAULT-TEXT', confidentialityNote('rsst', 'DEFAULT-TEXT'));
    }

    public function testHelperReturnsStoredValueOverDefault(): void
    {
        getConfigService()->set(self::CONFIG_KEY_NOTE, self::CUSTOM_NOTE);
        $this->assertSame(self::CUSTOM_NOTE, confidentialityNote('rsst', 'DEFAULT-TEXT'));
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Formulaire de dépôt (report_create)
    // ═══════════════════════════════════════════════════════════════════════════

    public function testReportFormShowsDefaultConfidentialNoteWhenUnset(): void
    {
        getConfigService()->set(self::CONFIG_KEY_VISIBILITY, 'confidential');
        $output = $this->renderReportCreate();

        $this->assertStringContainsString(
            'Le mode de visibilité est « Confidentiel »',
            $output,
            'Sans personnalisation, le formulaire affiche la phrase par défaut actuelle.'
        );
    }

    public function testReportFormShowsCustomNoteAndHidesDefaultWhenSet(): void
    {
        getConfigService()->set(self::CONFIG_KEY_VISIBILITY, 'confidential');
        getConfigService()->set(self::CONFIG_KEY_NOTE, self::CUSTOM_NOTE);

        $output = $this->renderReportCreate();

        $this->assertStringContainsString(self::CUSTOM_NOTE, $output, 'Le texte admin doit remplacer la phrase par défaut.');
        $this->assertStringNotContainsString(
            'Le mode de visibilité est « Confidentiel »',
            $output,
            'La phrase par défaut ne doit plus apparaître quand un texte admin est défini.'
        );
    }

    public function testReportFormAgentChoiceShowsDefaultNoteWhenUnset(): void
    {
        getConfigService()->set(self::CONFIG_KEY_VISIBILITY, 'agent_choice');
        $output = $this->renderReportCreate();

        $this->assertStringContainsString(
            'Si coché, ce signalement ne sera visible que par vous',
            $output,
            'En mode « Choix de l\'agent », la phrase par défaut actuelle est affichée près de la case.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Fiche signalement (report_card via report_view)
    // ═══════════════════════════════════════════════════════════════════════════

    public function testReportCardShowsDefaultNoteWhenUnset(): void
    {
        $output = $this->renderReportView();

        $this->assertStringContainsString(
            '(Visible uniquement par le déclarant, les superviseurs',
            $output,
            'Sans personnalisation, la fiche affiche la phrase de confidentialité par défaut.'
        );
    }

    public function testReportCardShowsCustomNoteWhenSet(): void
    {
        getConfigService()->set(self::CONFIG_KEY_NOTE, self::CUSTOM_NOTE);
        $output = $this->renderReportView();

        $this->assertStringContainsString(self::CUSTOM_NOTE, $output, 'La fiche affiche le texte admin pour le registre.');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Handler settings — persistance par registre
    // ═══════════════════════════════════════════════════════════════════════════

    public function testSettingsRegistresHandlerPersistsConfidentialityNote(): void
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
                    '600' => [
                        'label' => 'Registre test note',
                        'short_label' => 'TCN',
                        'description' => '',
                        'sort_order' => 50,
                        'default_visibility' => 'agent_choice',
                        'legal_note' => '',
                        'confidentiality_note' => self::CUSTOM_NOTE,
                    ],
                ],
            ],
            'db_seed' => "INSERT INTO registries (id, code, label, short_label, description, icon, color_theme, is_enabled, is_system, sort_order, default_visibility, notify_chsct) "
                . "VALUES (600, 'cn_registre', 'Registre test note', 'TCN', '', '📋', 'vert', 1, 0, 50, 'agent_choice', 0);",
            'assertions' => [
                'note_value' => "SELECT valeur FROM config_app WHERE cle = 'app_confidentiality_note_cn_registre'",
            ],
        ]);

        $this->assertNotNull($result['redirect'], 'Le handler doit rediriger (pas de fatal).');
        $this->assertSame(self::CUSTOM_NOTE, $result['queries']['note_value'], 'Le texte admin doit être persisté par registre.');
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