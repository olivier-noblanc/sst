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
        $this->pdo->exec('DELETE FROM sessions');
        $this->pdo->exec('DELETE FROM report_access_log');
        $this->pdo->exec('DELETE FROM report_agent_invites');
        $this->pdo->exec('DELETE FROM report_state_history');
        $this->pdo->exec('DELETE FROM report_responses');
        $this->pdo->exec('DELETE FROM report_agents');
        $this->pdo->exec('DELETE FROM reports');
        $this->pdo->exec('DELETE FROM users');
        $this->pdo->exec('DELETE FROM sites');
        $this->pdo->exec('DELETE FROM audit_log');
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

    // ═══════════════════════════════════════════════════════════════════════════════
    // Session GC tests
    // ═══════════════════════════════════════════════════════════════════════════════

    private function seedSession(string $id, int $lastAccessed): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO sessions (id, data, last_accessed)
            VALUES (:id, '', :last_accessed)
        ");
        $stmt->execute([':id' => $id, ':last_accessed' => $lastAccessed]);
    }

    public function testPurgesExpiredSessions(): void
    {
        $maxLifetime = 86400; // 24h, same as SessionService default
        ini_set('session.gc_maxlifetime', (string) $maxLifetime);

        $this->seedSession('purge-expired', time() - $maxLifetime - 100);
        $this->seedSession('purge-fresh', time() - 3600);

        lazyCronPurgeSessions($this->pdo);

        $remaining = $this->pdo->query('SELECT id FROM sessions')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertEquals(['purge-fresh'], $remaining);
    }

    public function testKeepsRecentSessions(): void
    {
        ini_set('session.gc_maxlifetime', '86400');

        $this->seedSession('keep-s1', time() - 3600);
        $this->seedSession('keep-s2', time() - 7200);

        lazyCronPurgeSessions($this->pdo);

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM sessions')->fetchColumn();
        $this->assertEquals(2, $count);
    }

    public function testSessionPurgeWritesAuditLog(): void
    {
        ini_set('session.gc_maxlifetime', '86400');
        $this->seedSession('audit-expired', time() - 86500);

        $before = (int) $this->pdo->query("SELECT COUNT(*) FROM audit_log WHERE action = 'gc_purge'")->fetchColumn();

        lazyCronPurgeSessions($this->pdo);

        $after = (int) $this->pdo->query("SELECT COUNT(*) FROM audit_log WHERE action = 'gc_purge'")->fetchColumn();
        $this->assertEquals($before + 1, $after);
    }

    public function testSessionPurgeSkipsAuditLogWhenNothingToDelete(): void
    {
        ini_set('session.gc_maxlifetime', '86400');
        $this->seedSession('noaudit-fresh', time() - 3600);

        $before = (int) $this->pdo->query("SELECT COUNT(*) FROM audit_log WHERE action = 'gc_purge'")->fetchColumn();

        lazyCronPurgeSessions($this->pdo);

        $after = (int) $this->pdo->query("SELECT COUNT(*) FROM audit_log WHERE action = 'gc_purge'")->fetchColumn();
        $this->assertEquals($before, $after);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Audit log purge tests
    // ═══════════════════════════════════════════════════════════════════════════════

    private function seedAuditLog(string $action, string $createdAt): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO audit_log (user_id, username, category, action, details, context, ip_address, created_at)
            VALUES (NULL, 'system', 'test', :action, 'test', '{}', '127.0.0.1', :created_at)
        ");
        $stmt->execute([':action' => $action, ':created_at' => $createdAt]);
    }

    public function testPurgesOldAuditLogEntries(): void
    {
        $this->seedAuditLog('old_action', gmdate('Y-m-d H:i:s', strtotime('-200 days')));
        $this->seedAuditLog('recent_action', gmdate('Y-m-d H:i:s', strtotime('-30 days')));

        lazyCronPurgeAuditLog($this->pdo);

        $remaining = $this->pdo->query("SELECT action FROM audit_log WHERE action != 'audit_purge'")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertEquals(['recent_action'], $remaining);
    }

    public function testAuditPurgeWritesItsOwnEntry(): void
    {
        $this->seedAuditLog('to_delete', gmdate('Y-m-d H:i:s', strtotime('-200 days')));

        lazyCronPurgeAuditLog($this->pdo);

        $purgeEntry = $this->pdo->query("SELECT details FROM audit_log WHERE action = 'audit_purge'")->fetch();
        $this->assertNotEmpty($purgeEntry);
        $this->assertStringContainsString('1 ligne', $purgeEntry['details']);
    }

    public function testAuditPurgeSkipsWhenNothingToDelete(): void
    {
        $this->seedAuditLog('recent', gmdate('Y-m-d H:i:s', strtotime('-10 days')));
        $before = (int) $this->pdo->query("SELECT COUNT(*) FROM audit_log WHERE action = 'audit_purge'")->fetchColumn();

        lazyCronPurgeAuditLog($this->pdo);

        $after = (int) $this->pdo->query("SELECT COUNT(*) FROM audit_log WHERE action = 'audit_purge'")->fetchColumn();
        $this->assertEquals($before, $after);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Access log purge tests
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testPurgesOldAccessLogEntries(): void
    {
        // Seed a report for FK
        $this->pdo->exec("INSERT INTO reports (uuid, reference, type, objet, description, date_evenement, declarant_id, declarant_nom, declarant_prenom, site_id, etat) VALUES ('r2', 'rsst-26-002', 'rsst', 'O', 'D', '2026-01-01', 1, 'M', 'J', 1, 'nouveau')");

        $stmt = $this->pdo->prepare("INSERT INTO report_access_log (report_uuid, user_id, role, accessed_at) VALUES ('r2', 1, 'superviseur', :accessed_at)");
        $stmt->execute([':accessed_at' => gmdate('Y-m-d H:i:s', strtotime('-800 days'))]);
        $stmt->execute([':accessed_at' => gmdate('Y-m-d H:i:s', strtotime('-30 days'))]);

        lazyCronPurgeAccessLog($this->pdo);

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM report_access_log')->fetchColumn();
        $this->assertEquals(1, $count);
    }

    public function testAccessPurgeSkipsWhenNothingToDelete(): void
    {
        $this->pdo->exec("INSERT INTO reports (uuid, reference, type, objet, description, date_evenement, declarant_id, declarant_nom, declarant_prenom, site_id, etat) VALUES ('r3', 'rsst-26-003', 'rsst', 'O', 'D', '2026-01-01', 1, 'M', 'J', 1, 'nouveau')");
        $stmt = $this->pdo->prepare("INSERT INTO report_access_log (report_uuid, user_id, role, accessed_at) VALUES ('r3', 1, 'superviseur', :accessed_at)");
        $stmt->execute([':accessed_at' => gmdate('Y-m-d H:i:s', strtotime('-10 days'))]);

        $before = (int) $this->pdo->query("SELECT COUNT(*) FROM audit_log WHERE action = 'access_purge'")->fetchColumn();

        lazyCronPurgeAccessLog($this->pdo);

        $after = (int) $this->pdo->query("SELECT COUNT(*) FROM audit_log WHERE action = 'access_purge'")->fetchColumn();
        $this->assertEquals($before, $after);
    }
}
