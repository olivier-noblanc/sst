<?php
/**
 * Confidentiality Note Test — Application SST DREETS BFC
 *
 * Feature : la phrase explicative d'un niveau de confidentialité est
 * paramétrable PAR REGISTRE **ET PAR NIVEAU DE VISIBILITÉ** (clés config
 * `app_confidentiality_note_<niveau>_<code>`, stockées dans config_app via
 * ConfigService/ConfigRepository). Les niveaux sont les valeurs de l'enum
 * App\Enum\VisibilityMode : public, agent_choice, confidential.
 *
 * Rétrocompatibilité : l'ancienne clé générique `app_confidentiality_note_<code>`
 * reste lue en repli (fallback) tant qu'aucun texte spécifique au niveau n'est
 * défini ; elle est supprimée lors de l'enregistrement depuis l'onglet Registres
 * (migration propre, atomique).
 *
 * Couverture ciblée (rapide) :
 *  - ConfigService : défauts par niveau, surcharge par niveau, fallback legacy ;
 *  - helper confidentialityNote() : défaut contextuel puis texte admin ;
 *  - formulaire de dépôt : texte du niveau réellement sélectionné ;
 *  - fiche signalement (report_card) : texte du niveau applicable ;
 *  - handler settings (onglet registres) : persiste les 3 clés + migre legacy.
 */

use PHPUnit\Framework\TestCase;
use App\DTO\SessionUser;
use App\Enum\VisibilityMode;
use App\Services\ConfigService;

class ConfidentialityNoteTest extends TestCase
{
    private const LEGACY_KEY          = 'app_confidentiality_note_rsst';
    private const KEY_CONFIDENTIAL    = 'app_confidentiality_note_confidential_rsst';
    private const KEY_AGENT_CHOICE    = 'app_confidentiality_note_agent_choice_rsst';
    private const KEY_PUBLIC          = 'app_confidentiality_note_public_rsst';
    private const CONFIG_KEY_VISIBILITY = 'app_report_visibility_rsst';

    private const CUSTOM_CONFIDENTIAL = 'TEXTE-PERSO-CONFIDENTIEL';
    private const CUSTOM_AGENT        = 'TEXTE-PERSO-CHOIX-AGENT';
    private const CUSTOM_PUBLIC       = 'TEXTE-PERSO-PUBLIC';
    private const LEGACY_TEXT         = 'ANCIEN-TEXTE-GENERIQUE';

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
        $this->deleteConfigKey(self::LEGACY_KEY);
        $this->deleteConfigKey(self::KEY_CONFIDENTIAL);
        $this->deleteConfigKey(self::KEY_AGENT_CHOICE);
        $this->deleteConfigKey(self::KEY_PUBLIC);
        $this->deleteConfigKey(self::CONFIG_KEY_VISIBILITY);
    }

    protected function tearDown(): void
    {
        $this->deleteConfigKey(self::LEGACY_KEY);
        $this->deleteConfigKey(self::KEY_CONFIDENTIAL);
        $this->deleteConfigKey(self::KEY_AGENT_CHOICE);
        $this->deleteConfigKey(self::KEY_PUBLIC);
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
    // ConfigService — clés, défauts, surcharge par niveau, fallback legacy
    // ═══════════════════════════════════════════════════════════════════════════

    public function testNoteKeyIsScopedByLevelAndRegistry(): void
    {
        $service = new ConfigService();
        $this->assertSame(
            'app_confidentiality_note_confidential_rsst',
            $service->confidentialityNoteKey('rsst', VisibilityMode::Confidential)
        );
        $this->assertSame(
            'app_confidentiality_note_agent_choice_ami',
            $service->confidentialityNoteKey('ami', VisibilityMode::AgentChoice)
        );
        $this->assertSame(
            'app_confidentiality_note_public_rsst',
            $service->confidentialityNoteKey('rsst', VisibilityMode::Public)
        );
        $this->assertSame('app_confidentiality_note_rsst', $service->legacyConfidentialityNoteKey('rsst'));
    }

    public function testDefaultsAreCurrentOrAppropriateTexts(): void
    {
        $service = new ConfigService();

        $this->assertStringContainsString(
            'Le mode de visibilité est « Confidentiel »',
            $service->confidentialityNoteDefault(VisibilityMode::Confidential)
        );
        $this->assertStringContainsString(
            'Si coché, ce signalement ne sera visible que par vous',
            $service->confidentialityNoteDefault(VisibilityMode::AgentChoice)
        );
        $this->assertStringContainsString(
            'visible par tous les agents',
            $service->confidentialityNoteDefault(VisibilityMode::Public)
        );
    }

    public function testNoCustomNoteReturnsContextualDefault(): void
    {
        $service = new ConfigService();
        $this->assertSame(
            $service->confidentialityNoteDefault(VisibilityMode::Confidential),
            $service->getConfidentialityNote('rsst', VisibilityMode::Confidential)
        );
        $this->assertSame(
            'DEFAULT-CTX',
            $service->getConfidentialityNote('rsst', VisibilityMode::AgentChoice, 'DEFAULT-CTX')
        );
    }

    public function testPerLevelOverrideIsIndependent(): void
    {
        getConfigService()->set(self::KEY_CONFIDENTIAL, self::CUSTOM_CONFIDENTIAL);
        getConfigService()->set(self::KEY_AGENT_CHOICE, self::CUSTOM_AGENT);

        $service = new ConfigService();
        $this->assertSame(self::CUSTOM_CONFIDENTIAL, $service->getConfidentialityNote('rsst', VisibilityMode::Confidential));
        $this->assertSame(self::CUSTOM_AGENT, $service->getConfidentialityNote('rsst', VisibilityMode::AgentChoice));
        // Niveau non personnalisé → défaut, pas de contamination inter-niveaux.
        $this->assertSame(
            $service->confidentialityNoteDefault(VisibilityMode::Public),
            $service->getConfidentialityNote('rsst', VisibilityMode::Public)
        );
    }

    public function testLegacyKeyIsReadAsFallbackForEveryLevel(): void
    {
        getConfigService()->set(self::LEGACY_KEY, self::LEGACY_TEXT);
        $service = new ConfigService();

        $this->assertSame(self::LEGACY_TEXT, $service->getConfidentialityNote('rsst', VisibilityMode::Confidential));
        $this->assertSame(self::LEGACY_TEXT, $service->getConfidentialityNote('rsst', VisibilityMode::AgentChoice));
        $this->assertSame(self::LEGACY_TEXT, $service->getConfidentialityNote('rsst', VisibilityMode::Public));
        $this->assertSame(self::LEGACY_TEXT, $service->getConfidentialityNoteCustom('rsst', VisibilityMode::Confidential));
    }

    public function testLevelOverrideShadowsLegacyKey(): void
    {
        getConfigService()->set(self::LEGACY_KEY, self::LEGACY_TEXT);
        getConfigService()->set(self::KEY_CONFIDENTIAL, self::CUSTOM_CONFIDENTIAL);
        $service = new ConfigService();

        $this->assertSame(self::CUSTOM_CONFIDENTIAL, $service->getConfidentialityNote('rsst', VisibilityMode::Confidential));
        // Les autres niveaux retombent sur le legacy (rétrocompatibilité).
        $this->assertSame(self::LEGACY_TEXT, $service->getConfidentialityNote('rsst', VisibilityMode::AgentChoice));
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Helper confidentialityNote() — défaut contextuel puis texte admin
    // ═══════════════════════════════════════════════════════════════════════════

    public function testHelperUsesDefaultWhenUnset(): void
    {
        $this->assertSame('DEFAULT-TEXT', confidentialityNote('rsst', VisibilityMode::Confidential, 'DEFAULT-TEXT'));
    }

    public function testHelperUsesStoredLevelValue(): void
    {
        getConfigService()->set(self::KEY_CONFIDENTIAL, self::CUSTOM_CONFIDENTIAL);
        $this->assertSame(self::CUSTOM_CONFIDENTIAL, confidentialityNote('rsst', VisibilityMode::Confidential, 'DEFAULT-TEXT'));
    }

    public function testHelperUsesLegacyValueAsFallback(): void
    {
        getConfigService()->set(self::LEGACY_KEY, self::LEGACY_TEXT);
        $this->assertSame(self::LEGACY_TEXT, confidentialityNote('rsst', VisibilityMode::AgentChoice, 'DEFAULT-TEXT'));
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Formulaire de dépôt — texte du niveau réellement sélectionné
    // ═══════════════════════════════════════════════════════════════════════════

    public function testConfidentialModeShowsItsDefaultAndCustomText(): void
    {
        getConfigService()->set(self::CONFIG_KEY_VISIBILITY, VisibilityMode::Confidential->value);
        $this->assertStringContainsString('Le mode de visibilité est « Confidentiel »', $this->renderReportCreate());

        getConfigService()->set(self::KEY_CONFIDENTIAL, self::CUSTOM_CONFIDENTIAL);
        $output = $this->renderReportCreate();
        $this->assertStringContainsString(self::CUSTOM_CONFIDENTIAL, $output);
        $this->assertStringNotContainsString('Le mode de visibilité est « Confidentiel »', $output);
    }

    public function testAgentChoiceModeShowsItsOwnTextOnly(): void
    {
        getConfigService()->set(self::CONFIG_KEY_VISIBILITY, VisibilityMode::AgentChoice->value);
        getConfigService()->set(self::KEY_AGENT_CHOICE, self::CUSTOM_AGENT);
        getConfigService()->set(self::KEY_CONFIDENTIAL, self::CUSTOM_CONFIDENTIAL);

        $output = $this->renderReportCreate();
        $this->assertStringContainsString(self::CUSTOM_AGENT, $output, 'Le mode Choix de l\'agent affiche son propre texte.');
        $this->assertStringNotContainsString(self::CUSTOM_CONFIDENTIAL, $output, 'Le texte Confidentiel ne doit pas fuiter en mode Choix de l\'agent.');
    }

    public function testPublicModeShowsItsOwnText(): void
    {
        getConfigService()->set(self::CONFIG_KEY_VISIBILITY, VisibilityMode::Public->value);
        $this->assertStringContainsString('visible par tous les agents', $this->renderReportCreate());

        getConfigService()->set(self::KEY_PUBLIC, self::CUSTOM_PUBLIC);
        $output = $this->renderReportCreate();
        $this->assertStringContainsString(self::CUSTOM_PUBLIC, $output);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Fiche signalement (report_card via report_view) — niveau applicable
    // ═══════════════════════════════════════════════════════════════════════════

    public function testReportCardShowsDefaultConfidentialNoteWhenUnset(): void
    {
        $output = $this->renderReportView();
        $this->assertStringContainsString('(Visible uniquement par le déclarant, les superviseurs', $output);
    }

    public function testReportCardUsesConfidentialLevelText(): void
    {
        getConfigService()->set(self::KEY_CONFIDENTIAL, self::CUSTOM_CONFIDENTIAL);
        getConfigService()->set(self::KEY_AGENT_CHOICE, self::CUSTOM_AGENT);

        $output = $this->renderReportView();
        $this->assertStringContainsString(self::CUSTOM_CONFIDENTIAL, $output, 'La fiche affiche le texte du niveau Confidentiel.');
        $this->assertStringNotContainsString(self::CUSTOM_AGENT, $output, 'Le texte du niveau Choix de l\'agent ne doit pas apparaître sur la fiche.');
        $this->assertStringNotContainsString('(Visible uniquement par le déclarant', $output);
    }

    public function testReportCardFallsBackToLegacyText(): void
    {
        getConfigService()->set(self::LEGACY_KEY, self::LEGACY_TEXT);
        $output = $this->renderReportView();
        $this->assertStringContainsString(self::LEGACY_TEXT, $output);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Handler settings — persistance par niveau + migration de l'ancienne clé
    // ═══════════════════════════════════════════════════════════════════════════

    public function testSettingsRegistresHandlerPersistsNotesAndMigratesLegacy(): void
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
                        'confidentiality_note_confidential' => self::CUSTOM_CONFIDENTIAL,
                        'confidentiality_note_agent_choice' => self::CUSTOM_AGENT,
                        'confidentiality_note_public' => self::CUSTOM_PUBLIC,
                    ],
                ],
            ],
            'db_seed' => "INSERT INTO registries (id, code, label, short_label, description, icon, color_theme, is_enabled, is_system, sort_order, default_visibility, notify_chsct) "
                . "VALUES (600, 'cn_registre', 'Registre test note', 'TCN', '', '📋', 'vert', 1, 0, 50, 'agent_choice', 0);"
                . "\nINSERT INTO config_app (cle, valeur, type, categorie, libelle, modifiable) "
                . "VALUES ('app_confidentiality_note_cn_registre', 'ANCIEN', '', '', '', 1);",
            'assertions' => [
                'confidential' => "SELECT valeur FROM config_app WHERE cle = 'app_confidentiality_note_confidential_cn_registre'",
                'agent_choice' => "SELECT valeur FROM config_app WHERE cle = 'app_confidentiality_note_agent_choice_cn_registre'",
                'public' => "SELECT valeur FROM config_app WHERE cle = 'app_confidentiality_note_public_cn_registre'",
                'legacy_count' => "SELECT COUNT(*) FROM config_app WHERE cle = 'app_confidentiality_note_cn_registre'",
            ],
        ]);

        $this->assertNotNull($result['redirect'], 'Le handler doit rediriger (pas de fatal).');
        $this->assertSame(self::CUSTOM_CONFIDENTIAL, $result['queries']['confidential']);
        $this->assertSame(self::CUSTOM_AGENT, $result['queries']['agent_choice']);
        $this->assertSame(self::CUSTOM_PUBLIC, $result['queries']['public']);
        $this->assertSame('0', (string) $result['queries']['legacy_count'], 'L\'ancienne clé générique doit être migrée puis supprimée.');
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