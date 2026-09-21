<?php

/**
 * PurgeReportsHandlerTest — Application SST DREETS BFC
 *
 * TDD : le handler POST `purge_reports` (superviseur) déclenche la purge
 * applicative FK-safe UNIQUEMENT si :
 *   - la confirmation est fournie (confirm_purge=1) ;
 *   - le fichier sentinelle `erase.txt` est présent à la racine au moment du POST.
 *
 * En cas d'absence de sentinelle : refus explicite (flash erreur, aucune
 * suppression, sentinelle non recréée). En cas de succès : signalements +
 * données liées + outbox + sessions purgés, users/sites/config conservés, le
 * fichier erase.txt est supprimé.
 *
 * Le handler est exécuté dans un sous-processus (tests/handler_runner.php),
 * comme OutboxRetryHandlerTest. L'emplacement de erase.txt y est injecté via
 * la config `erase_marker_path` (couture de test) pour ne jamais toucher un
 * éventuel fichier réel à la racine du dépôt.
 */

use PHPUnit\Framework\TestCase;

class PurgeReportsHandlerTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $this->tempFiles = [];
    }

    private function tempMarkerPath(): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sst_erase_' . bin2hex(random_bytes(8)) . '.txt';
        $this->tempFiles[] = $path;
        return $path;
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
        $result = json_decode($json, true);
        $this->assertIsArray($result, "JSON invalide du handler runner (fatal probable) : $json");

        return $result;
    }

    /** @return array<string, mixed> */
    private function superviseurCsrfSession(): array
    {
        $token = bin2hex(random_bytes(32));

        return [
            'user' => [
                'id' => 10, 'nom' => 'Sup', 'prenom' => 'Visor',
                'username' => 'superviseur.purge', 'role' => 'superviseur',
                'site_id' => null, 'site_code' => null,
                'email' => 'superviseur.purge@dreets-bfc.gouv.fr', 'is_active' => 1,
            ],
            'csrf_tokens' => [$token => time()],
            '_csrf_token_for_test' => $token,
        ];
    }

    private function seed(): string
    {
        return "INSERT INTO users (id, username, nom, prenom, role, site_id, is_active, email) VALUES (10, 'superviseur.purge', 'Sup', 'Visor', 'superviseur', NULL, 1, 'sup@dreets-bfc.gouv.fr');\n"
            . "INSERT INTO sites (id, code, nom, is_active) VALUES (10, 'PURGE', 'UR Purge', 1);\n"
            . "INSERT OR IGNORE INTO config_app (cle, valeur) VALUES ('purge_test_marker', '1');\n"
            . "INSERT INTO reports (uuid, reference, type, objet, description, date_evenement, declarant_id, declarant_nom, declarant_prenom, site_id, etat, is_confidential) VALUES ('purge-u1', 'RSST-25-900', 'rsst', 'Objet', 'Desc', '2025-01-01', 10, 'Sup', 'Visor', NULL, 'nouveau', 0);\n"
            . "INSERT INTO report_responses (report_uuid, user_id, reponse) VALUES ('purge-u1', 10, 'Réponse');\n"
            . "INSERT INTO email_outbox (dedup_key, recipient, subject, body, status) VALUES ('purge-mail', 'agent@dreets-bfc.gouv.fr', 'Sujet', 'Corps', 'pending');\n"
            . "INSERT INTO sessions (id, data, last_accessed) VALUES ('purge-sess', 'x', 1);";
    }

    private function fixedAssertions(): array
    {
        return [
            'reports'         => 'SELECT COUNT(*) FROM reports',
            'outbox'          => 'SELECT COUNT(*) FROM email_outbox',
            'sessions'        => 'SELECT COUNT(*) FROM sessions',
            'users'           => 'SELECT COUNT(*) FROM users',
            'sites'           => 'SELECT COUNT(*) FROM sites',
            'config'          => "SELECT COUNT(*) FROM config_app WHERE cle = 'purge_test_marker'",
            'registries'      => 'SELECT COUNT(*) FROM registries',
            'audit_count'     => "SELECT COUNT(*) FROM audit_log WHERE action = 'purge_reports'",
            'audit_category'  => "SELECT category FROM audit_log WHERE action = 'purge_reports' LIMIT 1",
        ];
    }

    public function testRefusesPurgeWhenMarkerAbsent(): void
    {
        $session = $this->superviseurCsrfSession();
        $token = $session['_csrf_token_for_test'];
        $marker = $this->tempMarkerPath(); // jamais créé

        $result = $this->runHandler([
            'handler'           => 'purge_reports_handler.php',
            'session'           => $session,
            'post'              => ['csrf_token' => $token, 'confirm_purge' => '1'],
            'erase_marker_path' => $marker,
            'db_seed'           => $this->seed(),
            'assertions'        => $this->fixedAssertions(),
            'file_assertions'   => [$marker => true],
        ]);

        $this->assertNotNull($result['redirect'] ?? null, 'Le handler redirige après refus');
        $this->assertSame('error', $result['flash']['type'] ?? null, 'Sentinelle absente → erreur, pas succès');
        $this->assertStringContainsString('erase.txt', (string) ($result['flash']['message'] ?? ''), 'Le message nomme le fichier sentinelle absent');
        $this->assertSame('1', (string) ($result['queries']['reports'] ?? ''), 'Aucune suppression sans sentinelle');
        $this->assertSame('1', (string) ($result['queries']['outbox'] ?? ''), 'Outbox intacte sans sentinelle');
        $this->assertSame('0', (string) ($result['queries']['audit_count'] ?? ''), 'Aucune action réelle → pas d\'audit');
        $this->assertFalse($result['files'][$marker] ?? true, 'La sentinelle ne doit pas être recréée');
    }

    public function testRequiresConfirmationBeforePurge(): void
    {
        $session = $this->superviseurCsrfSession();
        $token = $session['_csrf_token_for_test'];
        $marker = $this->tempMarkerPath();
        file_put_contents($marker, 'armed');

        $result = $this->runHandler([
            'handler'           => 'purge_reports_handler.php',
            'session'           => $session,
            'post'              => ['csrf_token' => $token], // pas de confirm_purge
            'erase_marker_path' => $marker,
            'db_seed'           => $this->seed(),
            'assertions'        => $this->fixedAssertions(),
            'file_assertions'   => [$marker => true],
        ]);

        $this->assertNotNull($result['redirect'] ?? null, 'Le handler redirige');
        $this->assertSame('error', $result['flash']['type'] ?? null, 'Sans confirmation → erreur');
        $this->assertSame('1', (string) ($result['queries']['reports'] ?? ''), 'Aucune suppression sans confirmation');
        $this->assertTrue($result['files'][$marker] ?? false, 'Sentinelle conservée si la purge n\'a pas eu lieu');
    }

    public function testPurgesAndDeletesMarkerOnSuccess(): void
    {
        $session = $this->superviseurCsrfSession();
        $token = $session['_csrf_token_for_test'];
        $marker = $this->tempMarkerPath();
        file_put_contents($marker, 'armed');

        $result = $this->runHandler([
            'handler'           => 'purge_reports_handler.php',
            'session'           => $session,
            'post'              => ['csrf_token' => $token, 'confirm_purge' => '1'],
            'erase_marker_path' => $marker,
            'db_seed'           => $this->seed(),
            'assertions'        => $this->fixedAssertions(),
            'file_assertions'   => [$marker => true],
        ]);

        $this->assertNotNull($result['redirect'] ?? null, 'Le handler redirige après succès');
        $this->assertSame('success', $result['flash']['type'] ?? null, 'Purge réussie → flash succès');

        $this->assertSame('0', (string) ($result['queries']['reports'] ?? ''), 'Signalements purgés');
        $this->assertSame('0', (string) ($result['queries']['outbox'] ?? ''), 'Outbox purgée');
        $this->assertSame('0', (string) ($result['queries']['sessions'] ?? ''), 'Sessions purgées');
        $this->assertSame('1', (string) ($result['queries']['users'] ?? ''), 'users conservée');
        $this->assertSame('1', (string) ($result['queries']['sites'] ?? ''), 'sites conservée');
        $this->assertSame('1', (string) ($result['queries']['config'] ?? ''), 'config_app conservée');
        $this->assertGreaterThanOrEqual(3, (int) ($result['queries']['registries'] ?? 0), 'registries conservée');

        $this->assertSame('1', (string) ($result['queries']['audit_count'] ?? ''), 'L\'action de purge est journalisée');
        $this->assertSame('maintenance', $result['queries']['audit_category'] ?? null, 'Catégorie audit maintenance');

        $this->assertFalse($result['files'][$marker] ?? true, 'erase.txt supprimé après succès complet');
    }
}