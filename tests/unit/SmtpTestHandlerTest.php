<?php
/**
 * SMTP test handlers — verdict direct (sans repli mail()).
 *
 * Décision Oracle SMTP : les boutons « tester la configuration SMTP »
 * (smtp_test_handler, onglet smtp de settings_handler) doivent refléter le
 * verdict SMTP réel. Le seam mailer est injecté dans le subprocess de test
 * pour rendre le verdict déterministe sans socket.
 */

use PHPUnit\Framework\TestCase;

class SmtpTestHandlerTest extends TestCase
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

    private function superviseurCsrfSession(): array
    {
        $token = bin2hex(random_bytes(32));
        return [
            'user' => [
                'id' => 2, 'nom' => 'Sup', 'prenom' => 'Visor',
                'username' => 'superviseur.test', 'role' => 'superviseur',
                'site_id' => 1, 'site_code' => 'UD21',
                'email' => 'superviseur.test@dreets-bfc.gouv.fr', 'is_active' => 1,
            ],
            'csrf_tokens' => [$token => time()],
            '_csrf_token_for_test' => $token,
        ];
    }

    private function smtpSeed(): string
    {
        return "INSERT OR REPLACE INTO config_app (cle, valeur) VALUES ('smtp_host', 'smtp.test.invalid');\n"
            . "INSERT OR REPLACE INTO config_app (cle, valeur) VALUES ('smtp_port', '25');\n"
            . "INSERT OR REPLACE INTO config_app (cle, valeur) VALUES ('smtp_from', 'noreply@dreets-bfc.gouv.fr');\n"
            . "INSERT OR REPLACE INTO config_app (cle, valeur) VALUES ('smtp_encryption', 'none');";
    }

    public function testSmtpTestHandlerShowsSuccessOnSmtpVerdictTrue(): void
    {
        $session = $this->superviseurCsrfSession();
        $token = $session['_csrf_token_for_test'];

        $result = $this->runHandler([
            'handler' => 'smtp_test_handler.php',
            'session' => $session,
            'post' => [
                'csrf_token' => $token,
                'smtp_test_to' => 'dest@dreets-bfc.gouv.fr',
            ],
            'mailer_seam' => 'ok',
            'db_seed' => $this->smtpSeed(),
        ]);

        $this->assertNotNull($result['redirect']);
        $this->assertEquals('success', $result['flash']['type'] ?? null);
    }

    public function testSmtpTestHandlerShowsErrorOnSmtpVerdictFalse(): void
    {
        $session = $this->superviseurCsrfSession();
        $token = $session['_csrf_token_for_test'];

        $result = $this->runHandler([
            'handler' => 'smtp_test_handler.php',
            'session' => $session,
            'post' => [
                'csrf_token' => $token,
                'smtp_test_to' => 'dest@dreets-bfc.gouv.fr',
            ],
            'mailer_seam' => 'fail',
            'db_seed' => $this->smtpSeed(),
        ]);

        $this->assertNotNull($result['redirect']);
        $this->assertEquals(
            'error',
            $result['flash']['type'] ?? null,
            'Un échec SMTP doit produire une erreur, pas un succès de repli mail()'
        );
    }

    public function testSettingsSmtpTabShowsWarningOnSmtpVerdictFalse(): void
    {
        $session = $this->superviseurCsrfSession();
        $token = $session['_csrf_token_for_test'];

        $result = $this->runHandler([
            'handler' => 'settings_handler.php',
            'session' => $session,
            'post' => [
                'csrf_token' => $token,
                'tab' => 'smtp',
                'smtp_host' => 'smtp.test.invalid',
                'smtp_port' => '25',
                'smtp_user' => '',
                'smtp_pass' => '',
                'smtp_from' => 'noreply@dreets-bfc.gouv.fr',
                'smtp_encryption' => 'none',
            ],
            'mailer_seam' => 'fail',
        ]);

        $this->assertNotNull($result['redirect']);
        $this->assertEquals('warning', $result['flash']['type'] ?? null);
    }
}