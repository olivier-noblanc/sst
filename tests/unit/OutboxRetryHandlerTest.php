<?php

/**
 * OutboxRetryHandlerTest — Application SST DREETS BFC
 *
 * TDD : le handler POST outbox_retry (superviseur) requalifie les messages
 * outbox en échec définitif (failed → pending) via EmailOutboxRepository::
 * requeueFailed(), journalise l'action en audit et affiche un résultat local
 * (flash). Aucune ligne n'est supprimée ; un lot vide produit un message
 * informatif sans erreur.
 *
 * Le handler est exécuté dans un sous-processus (tests/handler_runner.php),
 * comme SmtpTestHandlerTest, pour exercer le vrai flux POST + redirect.
 */

use PHPUnit\Framework\TestCase;

class OutboxRetryHandlerTest extends TestCase
{
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
                'id' => 2, 'nom' => 'Sup', 'prenom' => 'Visor',
                'username' => 'superviseur.test', 'role' => 'superviseur',
                'site_id' => 1, 'site_code' => 'UD21',
                'email' => 'superviseur.test@dreets-bfc.gouv.fr', 'is_active' => 1,
            ],
            'csrf_tokens' => [$token => time()],
            '_csrf_token_for_test' => $token,
        ];
    }

    private function failedSeed(): string
    {
        return "INSERT INTO email_outbox (dedup_key, recipient, subject, body, status, attempts, created_at, failed_at, last_error)\n"
            . "VALUES ('failed-seed', 'agent@dreets-bfc.gouv.fr', 'Sujet', 'Corps', 'failed', 5, '2026-01-01 00:00:00', '2026-01-01 00:00:00', 'SMTP timeout');";
    }

    private function fixedAssertions(): array
    {
        return [
            'status'         => "SELECT status FROM email_outbox WHERE dedup_key = 'failed-seed'",
            'attempts'       => "SELECT attempts FROM email_outbox WHERE dedup_key = 'failed-seed'",
            'total'          => 'SELECT COUNT(*) FROM email_outbox',
            'audit_count'    => "SELECT COUNT(*) FROM audit_log WHERE action = 'outbox_retry'",
            'audit_category' => "SELECT category FROM audit_log WHERE action = 'outbox_retry' LIMIT 1",
        ];
    }

    private function clearAudit(): string
    {
        return 'DELETE FROM audit_log;';
    }

    public function testHandlerRequeuesFailedAndSetsSuccessFlash(): void
    {
        $session = $this->superviseurCsrfSession();
        $token = $session['_csrf_token_for_test'];

        $result = $this->runHandler([
            'handler'    => 'outbox_retry_handler.php',
            'session'    => $session,
            'post'       => ['csrf_token' => $token],
            'db_seed'    => $this->clearAudit() . "\n" . $this->failedSeed(),
            'assertions' => $this->fixedAssertions(),
        ]);

        $this->assertNotNull($result['redirect'] ?? null, 'Le handler doit rediriger après traitement');
        $this->assertSame('success', $result['flash']['type'] ?? null, 'Requalification réussie → flash succès');
        $this->assertStringContainsString('reprogramm', (string) ($result['flash']['message'] ?? ''), 'Le message local décrit la reprogrammation');

        $this->assertSame('pending', $result['queries']['status'] ?? null, 'La ligne failed doit repasser en pending');
        $this->assertSame('0', (string) ($result['queries']['attempts'] ?? ''), 'attempts remis à 0');
        $this->assertSame('1', (string) ($result['queries']['total'] ?? ''), 'Aucune ligne supprimée');
        $this->assertSame('1', (string) ($result['queries']['audit_count'] ?? ''), 'L\'action est journalisée en audit');
        $this->assertSame('config', $result['queries']['audit_category'] ?? null, 'Audit catégorie config (comme les autres actions SMTP)');
    }

    public function testHandlerReportsNothingToRequeueWhenNoFailed(): void
    {
        $session = $this->superviseurCsrfSession();
        $token = $session['_csrf_token_for_test'];

        $result = $this->runHandler([
            'handler'    => 'outbox_retry_handler.php',
            'session'    => $session,
            'post'       => ['csrf_token' => $token],
            'db_seed'    => $this->clearAudit(),
            'assertions' => $this->fixedAssertions(),
        ]);

        $this->assertNotNull($result['redirect'] ?? null, 'Le handler redirige même sans échec');
        $this->assertSame('info', $result['flash']['type'] ?? null, 'Aucun échec → message informatif, pas une erreur');
        $this->assertStringContainsString('Aucun', (string) ($result['flash']['message'] ?? ''), 'Le message local indique qu\'il n\'y a rien à reprogrammer');
        $this->assertSame('0', (string) ($result['queries']['audit_count'] ?? ''), 'Aucune action réelle → aucune entrée d\'audit');
    }
}
