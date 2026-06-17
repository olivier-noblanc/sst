<?php
/**
 * Audit & Config Unit Tests — Application SST DREETS BFC
 *
 * Tests from src/audit.php and src/helpers/config.php:
 * - auditLog(), getAuditLog(), getAuditLogForTarget()
 * - getConfig(), updateConfig(), clearConfigCache()
 * - buildDelayAlertEmail()
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/audit.php';
require_once __DIR__ . '/../../src/mail.php';

class AuditConfigTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        $this->pdo->exec('DELETE FROM audit_log');
        $this->pdo->exec('DELETE FROM config_app');

        // Set up a mock session user for audit log
        setUserSession(['id' => 1, 'username' => 'testuser']);
    }

    protected function tearDown(): void
    {
        clearConfigCache();
    }

    // ─── auditLog ───────────────────────────────────────────────────────────

    public function testAuditLogInsertsEntry(): void
    {
        auditLog($this->pdo, 'report', 'create', 'Signalement rsst-25-001 créé');

        $stmt = $this->pdo->query("SELECT COUNT(*) FROM audit_log");
        $this->assertEquals(1, (int) $stmt->fetchColumn());
    }

    public function testAuditLogStoresCorrectData(): void
    {
        auditLog($this->pdo, 'user', 'edit', 'Utilisateur modifié', 42, 'user', ['field' => 'role']);

        $stmt = $this->pdo->query("SELECT * FROM audit_log LIMIT 1");
        $row = $stmt->fetch();

        $this->assertEquals('user', $row['category']);
        $this->assertEquals('edit', $row['action']);
        $this->assertEquals('Utilisateur modifié', $row['details']);
        $this->assertEquals(42, (int) $row['target_id']);
        $this->assertEquals('user', $row['target_type']);
        $this->assertEquals(1, (int) $row['user_id']);
        $this->assertEquals('testuser', $row['username']);
        $this->assertNotNull($row['context']);
        $context = json_decode($row['context'], true);
        $this->assertEquals('role', $context['field']);
    }

    public function testAuditLogWithoutTarget(): void
    {
        auditLog($this->pdo, 'auth', 'login', 'Connexion réussie');

        $stmt = $this->pdo->query("SELECT * FROM audit_log LIMIT 1");
        $row = $stmt->fetch();

        $this->assertNull($row['target_id']);
        $this->assertNull($row['target_type']);
        $this->assertNull($row['context']);
    }

    // ─── getAuditLog ────────────────────────────────────────────────────────

    public function testGetAuditLogReturnsEntries(): void
    {
        auditLog($this->pdo, 'report', 'create', 'Test 1');
        auditLog($this->pdo, 'report', 'edit', 'Test 2');
        auditLog($this->pdo, 'user', 'create', 'Test 3');

        $result = getAuditLog($this->pdo);
        $this->assertEquals(3, $result['total']);
        $this->assertCount(3, $result['entries']);
    }

    public function testGetAuditLogFilterByCategory(): void
    {
        auditLog($this->pdo, 'report', 'create', 'Test 1');
        auditLog($this->pdo, 'user', 'create', 'Test 2');

        $result = getAuditLog($this->pdo, ['category' => 'user']);
        $this->assertEquals(1, $result['total']);
        $this->assertEquals('user', $result['entries'][0]['category']);
    }

    public function testGetAuditLogFilterBySearch(): void
    {
        auditLog($this->pdo, 'report', 'create', 'Signalement RSST créé');
        auditLog($this->pdo, 'report', 'edit', 'Signalement RAMI modifié');

        $result = getAuditLog($this->pdo, ['q' => 'RAMI']);
        $this->assertEquals(1, $result['total']);
    }

    public function testGetAuditLogPagination(): void
    {
        for ($i = 0; $i < 5; $i++) {
            auditLog($this->pdo, 'report', 'create', "Test entry $i");
        }

        $result = getAuditLog($this->pdo, [], 1, 3);
        $this->assertEquals(5, $result['total']);
        $this->assertCount(3, $result['entries']);

        $result2 = getAuditLog($this->pdo, [], 2, 3);
        $this->assertCount(2, $result2['entries']);
    }

    // ─── getAuditLogForTarget ───────────────────────────────────────────────

    public function testGetAuditLogForTarget(): void
    {
        auditLog($this->pdo, 'report', 'create', 'Créé', 10, 'report');
        auditLog($this->pdo, 'report', 'edit', 'Modifié', 10, 'report');
        auditLog($this->pdo, 'user', 'create', 'Autre', 20, 'user');

        $entries = getAuditLogForTarget($this->pdo, 'report', 10);
        $this->assertCount(2, $entries);
    }

    public function testGetAuditLogForTargetEmpty(): void
    {
        auditLog($this->pdo, 'report', 'create', 'Créé', 10, 'report');

        $entries = getAuditLogForTarget($this->pdo, 'user', 99);
        $this->assertCount(0, $entries);
    }

    // ─── getConfig / updateConfig ────────────────────────────────────────────

    public function testGetConfigReturnsDefaultWhenNotSet(): void
    {
        $this->assertEquals('default_val', getConfig('nonexistent_key', 'default_val'));
    }

    public function testUpdateAndGetConfig(): void
    {
        updateConfig($this->pdo, 'test_key', 'test_value');
        clearConfigCache();
        $this->assertEquals('test_value', getConfig('test_key', ''));
    }

    public function testUpdateConfigOverwritesExisting(): void
    {
        updateConfig($this->pdo, 'test_key', 'value1');
        clearConfigCache();
        $this->assertEquals('value1', getConfig('test_key', ''));

        updateConfig($this->pdo, 'test_key', 'value2');
        clearConfigCache();
        $this->assertEquals('value2', getConfig('test_key', ''));
    }

    public function testClearConfigCacheInvalidatesCache(): void
    {
        updateConfig($this->pdo, 'cache_test', 'original');
        clearConfigCache();

        // Read once (will be cached)
        $this->assertEquals('original', getConfig('cache_test', ''));

        // Update directly in DB (bypassing updateConfig)
        $this->pdo->prepare("UPDATE config_app SET valeur = 'modified' WHERE cle = 'cache_test'")->execute();

        // Without clearing cache, should still return cached value
        $this->assertEquals('original', getConfig('cache_test', ''));

        // After clearing cache, should return new value
        clearConfigCache();
        $this->assertEquals('modified', getConfig('cache_test', ''));
    }

    // ─── buildDelayAlertEmail ────────────────────────────────────────────────

    public function testBuildDelayAlertEmailContainsSiteInfo(): void
    {
        updateConfig($this->pdo, 'app_nom_organisation', 'DREETS BFC');
        clearConfigCache();

        $siteData = [
            'site_code' => 'UR21',
            'site_nom' => 'UR Côte-d\'Or',
            'reports' => [
                [
                    'reference' => 'rsst-25-001',
                    'type' => 'rsst',
                    'objet' => 'Chute dans les escaliers',
                    'declarant_prenom' => 'Jean',
                    'declarant_nom' => 'Martin',
                    'created_at' => '2025-06-01 09:00:00',
                ],
            ],
        ];

        $html = buildDelayAlertEmail($siteData, 7);

        $this->assertStringContainsString('UR21', $html);
        $this->assertStringContainsString('UR Côte-d', $html);
        $this->assertStringContainsString('7', $html);
        $this->assertStringContainsString('rsst-25-001', $html);
        $this->assertStringContainsString('Chute dans les escaliers', $html);
        $this->assertStringContainsString('Jean Martin', $html);
    }

    public function testBuildDelayAlertEmailWithMultipleReports(): void
    {
        updateConfig($this->pdo, 'app_nom_organisation', 'DREETS BFC');
        clearConfigCache();

        $siteData = [
            'site_code' => 'UR25',
            'site_nom' => 'UR Doubs',
            'reports' => [
                ['reference' => 'rsst-25-001', 'type' => 'rsst', 'objet' => 'Objet 1',
                 'declarant_prenom' => 'A', 'declarant_nom' => 'B', 'created_at' => '2025-06-01'],
                ['reference' => 'dgi-25-003', 'type' => 'dgi', 'objet' => 'Objet 2',
                 'declarant_prenom' => 'C', 'declarant_nom' => 'D', 'created_at' => '2025-06-02'],
            ],
        ];

        $html = buildDelayAlertEmail($siteData, 5);

        $this->assertStringContainsString('rsst-25-001', $html);
        $this->assertStringContainsString('dgi-25-003', $html);
        $this->assertStringContainsString('Objet 1', $html);
        $this->assertStringContainsString('Objet 2', $html);
    }

    public function testBuildDelayAlertEmailEscapesHtml(): void
    {
        updateConfig($this->pdo, 'app_nom_organisation', 'DREETS BFC');
        clearConfigCache();

        $siteData = [
            'site_code' => 'UR21',
            'site_nom' => '<script>alert(1)</script>',
            'reports' => [
                ['reference' => 'rsst-25-001', 'type' => 'rsst',
                 'objet' => '<img src=x onerror=alert(1)>',
                 'declarant_prenom' => 'Test', 'declarant_nom' => 'User',
                 'created_at' => '2025-06-01'],
            ],
        ];

        $html = buildDelayAlertEmail($siteData, 7);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
    }
}
