<?php

/**
 * notifyRoleChange() — enqueue durable (Option A outbox).
 *
 * Le changement de rôle est notifié par user_edit_handler.php SEUL (aucun
 * listener) ; la notification est désormais MISE EN FILE (durable) au lieu
 * d'être envoyée en direct. Le bool retourné signale la mise en file, plus un
 * verdict SMTP — le transport appartient au worker outbox.
 */

use App\Enum\OutboxEvent;
use App\Services\NotificationService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../src/mail.php';

class NotifyRoleChangeVerdictTest extends TestCase
{
    private PDO $pdo;
    private int $userId = 9301;
    private const EMAIL = 'rolechg@dreets-bfc.gouv.fr';

    protected function setUp(): void
    {
        setMailerSeam(null);
        $this->pdo = getDB();
        cleanupAllForTest($this->pdo);
        $this->pdo->exec('DELETE FROM email_outbox');
        $this->pdo->exec("INSERT INTO users (id, username, nom, prenom, role, site_id, is_active, email)
            VALUES ({$this->userId}, 'test.rolechg', 'Nom', 'Pre', 'agent', NULL, 1, '" . self::EMAIL . "')");
    }

    protected function tearDown(): void
    {
        setMailerSeam(null);
        $this->pdo->exec('DELETE FROM email_outbox');
        cleanupAllForTest($this->pdo);
    }

    private function outboxCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM email_outbox')->fetchColumn();
    }

    public function testGlobalNotifyRoleChangeEnqueuesWithoutDirectSend(): void
    {
        $sent = [];
        setMailerSeam(static function (string $to, string $subject, string $body, string $from = '') use (&$sent): bool {
            $sent[] = $to;
            return false;
        });

        $result = notifyRoleChange($this->pdo, $this->userId, 'agent', 'superviseur', 'event-key-1');

        $this->assertTrue($result, 'true = message mis en file (durable), indépendamment du transport');
        $this->assertSame([], $sent, 'Aucun envoi direct : le worker outbox transportera le message');
        $this->assertSame(1, $this->outboxCount());

        $key = OutboxEvent::RoleChanged->value . ':' . $this->userId . ':event-key-1:' . strtolower(self::EMAIL);
        $stmt = $this->pdo->prepare('SELECT subject, body FROM email_outbox WHERE dedup_key = :k');
        $stmt->execute([':k' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($row, 'La clé de dédup encode utilisateur + eventKey (occurrence) + destinataire');
        $this->assertStringContainsString('Changement', (string) $row['subject']);
        $this->assertStringContainsString('superviseur', strtolower((string) $row['body']));
    }

    public function testUnknownUserReturnsFalseWithoutEnqueue(): void
    {
        $sent = false;
        setMailerSeam(static function (string $to, string $subject, string $body, string $from = '') use (&$sent): bool {
            $sent = true;
            return true;
        });

        $this->assertFalse(notifyRoleChange($this->pdo, 999999, 'agent', 'superviseur', 'unknown-key'));
        $this->assertFalse($sent, 'Aucun envoi pour un utilisateur introuvable');
        $this->assertSame(0, $this->outboxCount());
    }

    public function testRoleChangeEnqueueIsDeduplicated(): void
    {
        notifyRoleChange($this->pdo, $this->userId, 'agent', 'superviseur', 'same-key');
        notifyRoleChange($this->pdo, $this->userId, 'agent', 'superviseur', 'same-key');

        $this->assertSame(1, $this->outboxCount(), 'Une même transition (même eventKey) ne produit qu\'un message');
    }

    public function testServiceDelegatesEnqueue(): void
    {
        $service = new NotificationService($this->pdo);

        $this->assertTrue($service->notifyRoleChange($this->userId, 'agent', 'superviseur', 'svc-key'));
        $this->assertSame(1, $this->outboxCount());
    }
}
