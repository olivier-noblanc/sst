<?php
/**
 * notifyRoleChange() verdict — bool remonté jusqu'au handler.
 *
 * Décision Oracle SMTP : la notification de changement de rôle retourne le
 * verdict réel de sendMail (bool) au lieu d'un void que l'appelant supposait
 * toujours vrai. NotificationService délègue ce bool ; user_edit_handler le
 * consomme pour son audit et son flash.
 */

use PHPUnit\Framework\TestCase;
use App\Services\NotificationService;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../src/mail.php';

class NotifyRoleChangeVerdictTest extends TestCase
{
    private PDO $pdo;
    private int $userId = 9301;

    protected function setUp(): void
    {
        setMailerSeam(null);
        $this->pdo = getDB();
        cleanupAllForTest($this->pdo);
        $this->pdo->exec("INSERT INTO users (id, username, nom, prenom, role, site_id, is_active, email)
            VALUES ({$this->userId}, 'test.rolechg', 'Nom', 'Pre', 'agent', NULL, 1, 'rolechg@dreets-bfc.gouv.fr')");
    }

    protected function tearDown(): void
    {
        setMailerSeam(null);
    }

    public function testGlobalNotifyRoleChangeReturnsFalseWhenSendFails(): void
    {
        setMailerSeam(static fn(string $to, string $subject, string $body, string $from = ''): bool => false);

        $this->assertFalse(notifyRoleChange($this->pdo, $this->userId, 'agent', 'superviseur'));
    }

    public function testGlobalNotifyRoleChangeReturnsTrueWhenSendSucceeds(): void
    {
        setMailerSeam(static fn(string $to, string $subject, string $body, string $from = ''): bool => true);

        $this->assertTrue(notifyRoleChange($this->pdo, $this->userId, 'agent', 'superviseur'));
    }

    public function testUnknownUserReturnsFalseWithoutSending(): void
    {
        $called = false;
        setMailerSeam(static function (string $to, string $subject, string $body, string $from = '') use (&$called): bool {
            $called = true;
            return true;
        });

        $this->assertFalse(notifyRoleChange($this->pdo, 999999, 'agent', 'superviseur'));
        $this->assertFalse($called, 'Aucun envoi pour un utilisateur introuvable');
    }

    public function testServiceDelegatesVerdict(): void
    {
        setMailerSeam(static fn(string $to, string $subject, string $body, string $from = ''): bool => false);
        $service = new NotificationService($this->pdo);

        $this->assertFalse($service->notifyRoleChange($this->userId, 'agent', 'superviseur'));

        setMailerSeam(static fn(string $to, string $subject, string $body, string $from = ''): bool => true);
        $this->assertTrue($service->notifyRoleChange($this->userId, 'agent', 'superviseur'));
    }
}