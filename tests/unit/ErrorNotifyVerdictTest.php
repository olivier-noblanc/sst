<?php
/**
 * error_notify — throttle conservé sur tentative, verdict loggué.
 *
 * Décision Oracle SMTP : le throttle est marqué AVANT la tentative d'envoi
 * (comportement conservé : une erreur répétée ne re-spamme pas, même si
 * l'envoi échoue), mais le log distingue désormais un envoi réussi ('sent')
 * d'un échec ('FAILED') selon le verdict bool de sendMail().
 *
 * Exécuté en subprocess (tests/error_notify_runner.php) : le cache statique de
 * sstGetAdminEmail() et la redirection de error_log ne sont fiables que dans
 * un process dédié.
 */

use PHPUnit\Framework\TestCase;

class ErrorNotifyVerdictTest extends TestCase
{
    private function runRunner(array $config): array
    {
        $configPath = tempnam(sys_get_temp_dir(), 'sst_errnotify_cfg_') . '.json';
        file_put_contents($configPath, json_encode($config));

        $cmd = 'php ' . escapeshellarg(__DIR__ . '/../error_notify_runner.php') . ' ' . escapeshellarg($configPath);
        exec($cmd . ' 2>NUL', $output, $exitCode);

        unlink($configPath);

        $json = implode("\n", $output);
        $result = json_decode($json, true);
        $this->assertNotNull($result, "JSON invalide du runner error_notify : $json");

        return $result;
    }

    public function testFailedDeliveryStillMarksThrottleAndLogsFailed(): void
    {
        $result = $this->runRunner([
            'mailer_seam' => 'fail',
            'level' => 'Fatal error',
            'message' => 'ERR-UNIQ-' . uniqid(),
            'line' => 42,
        ]);

        $this->assertTrue(
            $result['throttled'],
            'Le throttle est conservé sur tentative : l\'erreur répétée ne doit pas re-spammer même si l\'envoi a échoué'
        );
        $this->assertStringContainsString('FAILED', (string) $result['log'], 'Un échec doit être loggué comme FAILED');
    }

    public function testSuccessfulDeliveryLogsSent(): void
    {
        $result = $this->runRunner([
            'mailer_seam' => 'ok',
            'level' => 'Fatal error',
            'message' => 'ERR-UNIQ-OK-' . uniqid(),
            'line' => 43,
        ]);

        $log = (string) $result['log'];
        $this->assertStringContainsString('Notification sent to', $log, 'Un envoi réussi doit être loggué comme sent');
        $this->assertStringNotContainsString('FAILED', $log);
    }
}