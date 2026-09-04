<?php
/**
 * Settings Handlers Validation Test — Application SST DREETS BFC
 *
 * Fiabilisation (council) — les handlers de paramétrage appelaient
 * $pdo->rollBack() SANS transaction ouverte (settings_handler_app.php:40,60,68
 * et settings_handler_sites.php:33,41,67,74) : toute validation échouée
 * levait une PDOException "There is no active transaction" → fatal 500.
 *
 * De plus, la validation app_base_url / app_admin_email intervenait APRÈS
 * 11 écritures de configuration → persistance partielle.
 *
 * Attendu : validation complète AVANT toute écriture, réponse utilisateur
 * contrôlée (flash + redirect), aucune écriture partielle.
 */

use PHPUnit\Framework\TestCase;

class SettingsHandlersValidationTest extends TestCase
{
    private function runHandler(array $config): array
    {
        $configPath = tempnam(sys_get_temp_dir(), 'sst_cfg_') . '.json';
        file_put_contents($configPath, json_encode($config));

        $cmd = 'php ' . escapeshellarg(__DIR__ . '/../handler_runner.php') . ' ' . escapeshellarg($configPath);
        exec($cmd . ' 2>NUL', $output, $exitCode);

        unlink($configPath);

        $json = implode("\n", $output);
        $result = json_decode($json, true);
        $this->assertNotNull($result, "JSON invalide du handler runner (fatal probable) : $json");

        return $result;
    }

    private function superviseurSession(): array
    {
        return [
            'user' => [
                'id' => 2, 'nom' => 'Sup', 'prenom' => 'Visor',
                'username' => 'superviseur.test', 'role' => 'superviseur',
                'site_id' => 1, 'site_code' => 'UD21',
                'email' => 'superviseur.test@dreets-bfc.gouv.fr', 'is_active' => 1,
            ],
        ];
    }

    private function validAppPost(string $csrf, string $dpoContact, string $baseUrl = ''): array
    {
        return [
            'csrf_token' => $csrf,
            'tab' => 'app',
            'app_nom_organisation' => 'DREETS BFC',
            'app_nom_complet' => 'Direction régionale',
            'app_label_unite' => 'UR',
            'app_superviseur_usernames' => '',
            'app_brand_color' => '#1e40af',
            'app_hotline_number' => '',
            'app_dpo_contact' => $dpoContact,
            'app_report_preamble' => '',
            'app_rsst_description' => '',
            'app_report_create_label' => 'Signaler un événement',
            'app_linked_agents_label' => '',
            'app_base_url' => $baseUrl,
            'app_admin_email' => 'admin@dreets-bfc.gouv.fr',
            'app_role_label_agent' => 'Agent',
            'app_role_label_superviseur' => 'Superviseur',
            'app_role_label_chsct' => 'Membre FS/CSA',
            'app_report_visibility' => 'agent_choice',
            'app_chsct_report_scope' => 'consent_only',
        ];
    }

    // ─── Onglet app ──────────────────────────────────────────────────────

    public function testInvalidBaseUrlShowsErrorWithoutPartialPersistence(): void
    {
        $token = bin2hex(random_bytes(32));
        $session = array_merge($this->superviseurSession(), ['csrf_tokens' => [$token => time()]]);

        $result = $this->runHandler([
            'handler' => 'settings_handler.php',
            'session' => $session,
            'post' => $this->validAppPost($token, 'SENTINELLE-DPO-AVANT', 'pas-une-url'),
            'db_seed' => "UPDATE config_app SET valeur = 'SENTINELLE-DPO-AVANT' WHERE cle = 'app_dpo_contact';"
                . "INSERT OR IGNORE INTO config_app (cle, valeur, type, categorie, libelle, modifiable) VALUES ('app_dpo_contact', 'SENTINELLE-DPO-AVANT', '', '', '', 1);",
            'assertions' => [
                'dpo_value' => "SELECT valeur FROM config_app WHERE cle = 'app_dpo_contact'",
            ],
        ]);

        $this->assertNotNull($result['redirect'], 'Une validation échouée doit produire une redirection contrôlée, pas un fatal');
        $this->assertEquals('error', $result['flash']['type'] ?? null);
        $this->assertSame(
            'SENTINELLE-DPO-AVANT',
            $result['queries']['dpo_value'],
            'Aucune écriture ne doit précéder une validation échouée (pas de persistance partielle)'
        );
    }

    public function testInvalidAdminEmailShowsErrorWithoutPartialPersistence(): void
    {
        $token = bin2hex(random_bytes(32));
        $session = array_merge($this->superviseurSession(), ['csrf_tokens' => [$token => time()]]);

        $post = $this->validAppPost($token, 'SENTINELLE-DPO-B');
        $post['app_admin_email'] = 'pas-un-email';

        $result = $this->runHandler([
            'handler' => 'settings_handler.php',
            'session' => $session,
            'post' => $post,
            'db_seed' => "UPDATE config_app SET valeur = 'SENTINELLE-DPO-B' WHERE cle = 'app_dpo_contact';"
                . "INSERT OR IGNORE INTO config_app (cle, valeur, type, categorie, libelle, modifiable) VALUES ('app_dpo_contact', 'SENTINELLE-DPO-B', '', '', '', 1);",
            'assertions' => [
                'dpo_value' => "SELECT valeur FROM config_app WHERE cle = 'app_dpo_contact'",
            ],
        ]);

        $this->assertNotNull($result['redirect']);
        $this->assertEquals('error', $result['flash']['type'] ?? null);
        $this->assertSame('SENTINELLE-DPO-B', $result['queries']['dpo_value']);
    }

    public function testValidAppTabPersistsKeys(): void
    {
        $token = bin2hex(random_bytes(32));
        $session = array_merge($this->superviseurSession(), ['csrf_tokens' => [$token => time()]]);
        $sentinel = 'ORG-' . uniqid();

        $post = $this->validAppPost($token, 'dpo@dreets-bfc.gouv.fr', 'https://sst.example.gouv.fr');
        $post['app_nom_organisation'] = $sentinel;

        $result = $this->runHandler([
            'handler' => 'settings_handler.php',
            'session' => $session,
            'post' => $post,
            'assertions' => [
                'org_value' => "SELECT valeur FROM config_app WHERE cle = 'app_nom_organisation'",
                'base_url' => "SELECT valeur FROM config_app WHERE cle = 'app_base_url'",
            ],
        ]);

        $this->assertNotNull($result['redirect']);
        $this->assertEquals('success', $result['flash']['type'] ?? null);
        $this->assertSame($sentinel, $result['queries']['org_value']);
        $this->assertSame('https://sst.example.gouv.fr', $result['queries']['base_url']);
    }

    // ─── Onglets notifications (sites / global) ──────────────────────────

    public function testGlobalEmailsWithInvalidLineCancelsWholeSave(): void
    {
        $token = bin2hex(random_bytes(32));
        $session = array_merge($this->superviseurSession(), ['csrf_tokens' => [$token => time()]]);
        $sentinel = 'avant-' . uniqid();

        $result = $this->runHandler([
            'handler' => 'settings_handler.php',
            'session' => $session,
            'post' => [
                'csrf_token' => $token,
                'tab' => 'global',
                'global_emails' => 'valide@dreets-bfc.gouv.fr' . "\n" . 'pas-un-email',
            ],
            'db_seed' => "INSERT OR IGNORE INTO config_app (cle, valeur) VALUES ('__sentinel_global__', '" . $sentinel . "');",
            'assertions' => [
                'global_count' => "SELECT COUNT(*) FROM notification_settings WHERE type = 'global'",
            ],
        ]);

        $this->assertNotNull($result['redirect']);
        $this->assertEquals('error', $result['flash']['type'] ?? null);
        $this->assertEquals(0, $result['queries']['global_count'], 'Une adresse invalide doit annuler TOUT l\'enregistrement (pas de suppression partielle)');
    }

    public function testGlobalEmailsValidLinesAreSaved(): void
    {
        $token = bin2hex(random_bytes(32));
        $session = array_merge($this->superviseurSession(), ['csrf_tokens' => [$token => time()]]);

        $result = $this->runHandler([
            'handler' => 'settings_handler.php',
            'session' => $session,
            'post' => [
                'csrf_token' => $token,
                'tab' => 'global',
                'global_emails' => 'direction@dreets-bfc.gouv.fr' . "\n" . 'chsct@dreets-bfc.gouv.fr',
            ],
            'assertions' => [
                'global_count' => "SELECT COUNT(*) FROM notification_settings WHERE type = 'global'",
            ],
        ]);

        $this->assertNotNull($result['redirect']);
        $this->assertEquals('success', $result['flash']['type'] ?? null);
        $this->assertEquals(2, $result['queries']['global_count']);
    }

    // ─── Onglet notifications par site (oracle — non couvert) ────────────

    public function testSitesEmailsTabSavesValidEmails(): void
    {
        $token = bin2hex(random_bytes(32));
        $session = array_merge($this->superviseurSession(), ['csrf_tokens' => [$token => time()]]);

        $seed = "INSERT INTO sites (id, code, nom, is_active) VALUES (510, 'UDNOT', 'Site notifications', 1);";

        $result = $this->runHandler([
            'handler' => 'settings_handler.php',
            'session' => $session,
            'post' => [
                'csrf_token' => $token,
                'tab' => 'sites',
                'site_emails' => [
                    '510' => "site.a@dreets-bfc.gouv.fr\nsite.b@dreets-bfc.gouv.fr",
                ],
            ],
            'db_seed' => $seed,
            'assertions' => [
                'site_email_count' => "SELECT COUNT(*) FROM notification_settings WHERE type = 'site' AND site_id = 510",
            ],
        ]);

        $this->assertNotNull($result['redirect']);
        $this->assertEquals('success', $result['flash']['type'] ?? null);
        $this->assertEquals(2, $result['queries']['site_email_count']);
    }

    public function testSitesEmailsTabRejectsInvalidEmailWithoutPartialSave(): void
    {
        $token = bin2hex(random_bytes(32));
        $session = array_merge($this->superviseurSession(), ['csrf_tokens' => [$token => time()]]);

        $seed = "INSERT INTO sites (id, code, nom, is_active) VALUES (511, 'UDNOT2', 'Site notifications 2', 1);";

        $result = $this->runHandler([
            'handler' => 'settings_handler.php',
            'session' => $session,
            'post' => [
                'csrf_token' => $token,
                'tab' => 'sites',
                'site_emails' => [
                    '511' => "valide@dreets-bfc.gouv.fr\npas-un-email",
                ],
            ],
            'db_seed' => $seed,
            'assertions' => [
                'site_email_count' => "SELECT COUNT(*) FROM notification_settings WHERE type = 'site' AND site_id = 511",
            ],
        ]);

        $this->assertNotNull($result['redirect']);
        $this->assertEquals('error', $result['flash']['type'] ?? null);
        $this->assertEquals(0, $result['queries']['site_email_count'], 'Une adresse invalide annule tout l\'enregistrement (pas de sauvegarde partielle)');
    }

    // ─── Onglet manage_sites ─────────────────────────────────────────────

    public function testAddSiteWithEmptyCodeShowsErrorWithoutCrash(): void
    {
        $token = bin2hex(random_bytes(32));
        $session = array_merge($this->superviseurSession(), ['csrf_tokens' => [$token => time()]]);

        $result = $this->runHandler([
            'handler' => 'settings_handler.php',
            'session' => $session,
            'post' => [
                'csrf_token' => $token,
                'tab' => 'manage_sites',
                'action' => 'add_site',
                'new_site_code' => '',
                'new_site_nom' => 'Site sans code',
                'new_site_departement' => '21',
            ],
            'assertions' => [
                'site_count' => "SELECT COUNT(*) FROM sites WHERE code = ''",
            ],
        ]);

        $this->assertNotNull($result['redirect'], 'Validation échouée → redirection contrôlée (pas de fatal rollBack)');
        $this->assertEquals('error', $result['flash']['type'] ?? null);
        $this->assertEquals(0, $result['queries']['site_count']);
    }

    public function testDeleteSiteWithUsersShowsErrorWithoutCrash(): void
    {
        // Oracle R6 — exercer RÉELLEMENT la branche userCount>0 : un site_id
        // VALIDE est posté (l'ancienne version postait site_id='' → (int) 0 →
        // toute la branche delete_site était court-circuitée, test creux).
        $token = bin2hex(random_bytes(32));
        $session = array_merge($this->superviseurSession(), ['csrf_tokens' => [$token => time()]]);

        $seed = "INSERT INTO sites (id, code, nom, is_active) VALUES (501, 'UDDEL', 'Site a supprimer', 1);\n"
            . "INSERT INTO users (username, nom, prenom, role, site_id, is_active, email) VALUES ('agent.delete.me', 'Agent', 'Suppr', 'agent', 501, 1, 'agent.delete@dreets-bfc.gouv.fr');";

        $result = $this->runHandler([
            'handler' => 'settings_handler.php',
            'session' => $session,
            'post' => [
                'csrf_token' => $token,
                'tab' => 'manage_sites',
                'action' => 'delete_site',
                'site_id' => '501',
            ],
            'db_seed' => $seed,
            'assertions' => [
                'site_count' => "SELECT COUNT(*) FROM sites WHERE code = 'UDDEL'",
            ],
        ]);

        $this->assertNotNull($result['redirect'], 'Refus métier → redirection contrôlée (pas de fatal rollBack)');
        $this->assertEquals('error', $result['flash']['type'] ?? null, 'Le site contient des agents : refus explicite attendu (branche userCount>0)');
        $this->assertEquals(1, $result['queries']['site_count'], 'Un site contenant des agents ne doit pas être supprimé');
    }
}
