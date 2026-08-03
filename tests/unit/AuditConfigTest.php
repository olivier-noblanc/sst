<?php
/**
 * Audit & Config Unit Tests — Audit Log & Config Functions
 *
 * Tests from src/audit.php and src/helpers/config.php:
 * - auditLog(), getAuditLog(), getAuditLogForTarget()
 * - getConfig(), updateConfig(), clearConfigCache()
 */

use PHPUnit\Framework\TestCase;
use App\DTO\SessionUser;

require_once __DIR__ . '/../../src/audit.php';

class AuditConfigTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        $this->pdo->exec('DELETE FROM audit_log');
        $this->pdo->exec('DELETE FROM config_app');
        setUserSession(SessionUser::fromArray(['id' => 1, 'username' => 'testuser']));
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

    // ─── buildExportAuditContext ─────────────────────────────────────────────
    // Issue #1 — Avant : export_handler.php faisait json_encode($filters) puis
    // AuditRepository::log() re-json_encode le context → double-encoding.
    // buildExportAuditContext() doit retourner des clés scalaires filter_*.

    public function testBuildExportAuditContextFlattensAllFiltersToScalarKeys(): void
    {
        $filters = [
            'type' => 'rsst',
            'site_id' => 5,
            'declarant_id' => 12,
            'date_from' => '2025-01-01',
            'date_to' => '2025-12-31',
            'etats' => ['nouveau', 'en_cours'],
        ];
        $context = buildExportAuditContext($filters, 42);

        // Tous les valeurs doivent être scalaires — pas de nested array
        foreach ($context as $key => $value) {
            $this->assertTrue(
                is_string($value) || is_int($value) || is_bool($value) || is_null($value),
                "La clé '$key' doit être scalaire, reçu " . gettype($value)
            );
        }

        $this->assertSame(42, $context['count']);
        $this->assertSame('rsst', $context['filter_type']);
        $this->assertSame(5, $context['filter_site_id']);
        $this->assertSame(12, $context['filter_declarant_id']);
        $this->assertSame('2025-01-01', $context['filter_date_from']);
        $this->assertSame('2025-12-31', $context['filter_date_to']);
        // etats array aplati en string comma-separated
        $this->assertSame('nouveau,en_cours', $context['filter_etats']);
    }

    public function testBuildExportAuditContextWithEmptyFilters(): void
    {
        $context = buildExportAuditContext([], 0);

        $this->assertSame(0, $context['count']);
        $this->assertArrayNotHasKey('filter_type', $context);
        $this->assertArrayNotHasKey('filter_etats', $context);
    }

    public function testBuildExportAuditContextHandlesSingleEtatString(): void
    {
        // $_POST['etats'] peut être un string seul (un seul checkbox coché)
        $context = buildExportAuditContext(['etats' => 'nouveau'], 1);

        $this->assertSame('nouveau', $context['filter_etats']);
    }

    public function testBuildExportAuditContextProducesNoDoubleEncoding(): void
    {
        // Simule le round-trip : buildExportAuditContext() → AuditRepository::log()
        // qui fait json_encode($context). Le resultat doit être du JSON propre,
        // pas du double-encoded.
        $filters = ['type' => 'rsst', 'etats' => ['nouveau', 'en_cours']];
        $context = buildExportAuditContext($filters, 3);

        $encoded = json_encode($context, JSON_UNESCAPED_UNICODE);
        $decoded = json_decode($encoded, true);

        // filter_etats doit rester un string après un seul encode
        $this->assertIsString($decoded['filter_etats']);
        $this->assertSame('nouveau,en_cours', $decoded['filter_etats']);
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
        $this->assertEquals('original', getConfig('cache_test', ''));
        $this->pdo->prepare("UPDATE config_app SET valeur = 'modified' WHERE cle = 'cache_test'")->execute();
        $this->assertEquals('original', getConfig('cache_test', ''));
        clearConfigCache();
        $this->assertEquals('modified', getConfig('cache_test', ''));
    }
}
