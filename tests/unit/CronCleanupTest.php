<?php
/**
 * Lazy Cron — Cleanup Task Tests — Application SST DREETS BFC
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/cron_cleanup.php';

class CronCleanupTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        $this->pdo->exec('DELETE FROM report_agent_invites');
        $this->pdo->exec('DELETE FROM reports');
        $this->pdo->exec('DELETE FROM users');
        $this->pdo->exec('DELETE FROM sites');
        $this->pdo->exec("INSERT INTO sites (id, code, nom, is_active) VALUES (1, 'UR21', 'Test', 1)");
        $this->pdo->exec("INSERT INTO users (id, username, nom, prenom, role, site_id, is_active) VALUES (1, 'jean', 'M', 'J', 'agent', 1, 1)");
        $this->pdo->exec("INSERT INTO reports (uuid, reference, type, objet, description, date_evenement, declarant_id, declarant_nom, declarant_prenom, site_id, etat) VALUES ('r1', 'rsst-26-001', 'rsst', 'O', 'D', '2026-01-01', 1, 'M', 'J', 1, 'nouveau')");
    }

    private function seedInvite(string $token, bool $confirmed, string $createdAt, ?string $confirmedAt = null): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO report_agent_invites (report_uuid, email, token, confirmed, created_at, confirmed_at)
            VALUES ('r1', 'agent@example.com', :token, :confirmed, :created_at, :confirmed_at)
        ");
        $stmt->execute([
            ':token' => $token,
            ':confirmed' => $confirmed ? 1 : 0,
            ':created_at' => $createdAt,
            ':confirmed_at' => $confirmedAt,
        ]);
    }

    public function testPurgesOldConfirmedInvites(): void
    {
        $this->seedInvite('old-confirmed', true, gmdate('Y-m-d H:i:s', strtotime('-100 days')), gmdate('Y-m-d H:i:s', strtotime('-91 days')));
        $this->seedInvite('recent-confirmed', true, gmdate('Y-m-d H:i:s', strtotime('-10 days')), gmdate('Y-m-d H:i:s', strtotime('-5 days')));

        lazyCronCleanup($this->pdo);

        $remaining = $this->pdo->query('SELECT token FROM report_agent_invites')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertEquals(['recent-confirmed'], $remaining);
    }

    public function testPurgesOldUnconfirmedInvites(): void
    {
        $this->seedInvite('old-unconfirmed', false, gmdate('Y-m-d H:i:s', strtotime('-40 days')));
        $this->seedInvite('recent-unconfirmed', false, gmdate('Y-m-d H:i:s', strtotime('-5 days')));

        lazyCronCleanup($this->pdo);

        $remaining = $this->pdo->query('SELECT token FROM report_agent_invites')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertEquals(['recent-unconfirmed'], $remaining);
    }

    public function testKeepsRecentInvitesOfBothKinds(): void
    {
        $this->seedInvite('recent-confirmed', true, gmdate('Y-m-d H:i:s', strtotime('-2 days')), gmdate('Y-m-d H:i:s', strtotime('-1 days')));
        $this->seedInvite('recent-unconfirmed', false, gmdate('Y-m-d H:i:s', strtotime('-1 days')));

        lazyCronCleanup($this->pdo);

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM report_agent_invites')->fetchColumn();
        $this->assertEquals(2, $count);
    }

    public function testDoesNotWriteAuditLogWhenNothingToDelete(): void
    {
        $this->seedInvite('recent', false, gmdate('Y-m-d H:i:s', strtotime('-1 days')));
        $before = (int) $this->pdo->query("SELECT COUNT(*) FROM audit_log WHERE action = 'cleanup'")->fetchColumn();

        lazyCronCleanup($this->pdo);

        $after = (int) $this->pdo->query("SELECT COUNT(*) FROM audit_log WHERE action = 'cleanup'")->fetchColumn();
        $this->assertEquals($before, $after);
    }
}
