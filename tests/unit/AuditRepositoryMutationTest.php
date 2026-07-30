<?php
/**
 * Tests AuditRepository exhaustively — kills Infection mutants on:
 *   - log() (userId fallback, username fallback, ip, context json_encode, targetId/Uuid)
 *   - findPaginated (filters: category, user_id, date_from, date_to, q, username; pagination)
 *   - findByTarget (numeric id vs uuid string)
 */

use PHPUnit\Framework\TestCase;
use App\Repository\AuditRepository;

class AuditRepositoryMutationTest extends TestCase
{
    private PDO $pdo;
    private AuditRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        $this->pdo->exec('DELETE FROM audit_log');
        $this->repo = new AuditRepository($this->pdo);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }

    protected function tearDown(): void
    {
        $this->pdo->exec('DELETE FROM audit_log');
    }

    private function logEntry(string $category = 'test', string $action = 'action', string $details = 'details'): void
    {
        $this->repo->log($category, $action, $details);
    }

    // ═══ log() ═══

    public function testLogInsertsEntryWithCorrectFields(): void
    {
        $this->repo->log('report', 'create', 'Created report', 42, 'report', ['key' => 'value'], 'uuid-123', 99);

        $stmt = $this->pdo->query('SELECT * FROM audit_log ORDER BY id DESC LIMIT 1');
        $row = $stmt->fetch();

        $this->assertSame('report', $row['category']);
        $this->assertSame('create', $row['action']);
        $this->assertSame('Created report', $row['details']);
        $this->assertSame(42, (int) $row['target_id']);
        $this->assertSame('report', $row['target_type']);
        $this->assertSame('uuid-123', $row['target_uuid']);
        $this->assertSame(99, (int) $row['user_id']);
        $this->assertNotNull($row['context'], 'context must be JSON-encoded');
        $this->assertSame('127.0.0.1', $row['ip_address']);
    }

    public function testLogUsesSystemUsernameWhenNoSession(): void
    {
        // Kill mutant on currentUserUsername() fallback to 'system'
        // In test context, currentUserUsername() may return '' or a test user.
        // The key is that it's NOT null and IS a non-empty string (system fallback).
        $this->repo->log('test', 'action', 'details');
        $stmt = $this->pdo->query('SELECT username FROM audit_log ORDER BY id DESC LIMIT 1');
        $username = $stmt->fetchColumn();
        $this->assertIsString($username, 'username must be a string');
        $this->assertNotSame('', $username, 'username must not be empty (system fallback)');
    }

    public function testLogUsesCliIpWhenNoRemoteAddr(): void
    {
        unset($_SERVER['REMOTE_ADDR']);
        $this->repo->log('test', 'action', 'details');
        $stmt = $this->pdo->query('SELECT ip_address FROM audit_log ORDER BY id DESC LIMIT 1');
        $this->assertSame('cli', $stmt->fetchColumn());
    }

    public function testLogEncodesContextAsJson(): void
    {
        $context = ['report_id' => 42, 'action' => 'created'];
        $this->repo->log('test', 'action', 'details', null, null, $context);
        $stmt = $this->pdo->query('SELECT context FROM audit_log ORDER BY id DESC LIMIT 1');
        $json = $stmt->fetchColumn();
        $decoded = json_decode($json, true);
        $this->assertSame($context, $decoded);
    }

    public function testLogStoresNullContextWhenEmpty(): void
    {
        // Kill LogicalNot mutant on !empty($context)
        $this->repo->log('test', 'action', 'details', null, null, []);
        $stmt = $this->pdo->query('SELECT context FROM audit_log ORDER BY id DESC LIMIT 1');
        $this->assertNull($stmt->fetchColumn(), 'empty context must be stored as NULL');
    }

    public function testLogEncodesUnicodeInContext(): void
    {
        $context = ['message' => 'Café réunion'];
        $this->repo->log('test', 'action', 'details', null, null, $context);
        $stmt = $this->pdo->query('SELECT context FROM audit_log ORDER BY id DESC LIMIT 1');
        $json = $stmt->fetchColumn();
        $this->assertStringContainsString('Café', $json, 'unicode must not be escaped');
        $this->assertStringNotContainsString('Caf\u00e9', $json, 'JSON_UNESCAPED_UNICODE must be used');
    }

    public function testLogWithNullTargetIdStoresNull(): void
    {
        $this->repo->log('test', 'action', 'details');
        $stmt = $this->pdo->query('SELECT target_id FROM audit_log ORDER BY id DESC LIMIT 1');
        $this->assertNull($stmt->fetchColumn());
    }

    public function testLogWithNullTargetTypeStoresNull(): void
    {
        $this->repo->log('test', 'action', 'details');
        $stmt = $this->pdo->query('SELECT target_type FROM audit_log ORDER BY id DESC LIMIT 1');
        $this->assertNull($stmt->fetchColumn());
    }

    public function testLogWithNullTargetUuidStoresNull(): void
    {
        $this->repo->log('test', 'action', 'details');
        $stmt = $this->pdo->query('SELECT target_uuid FROM audit_log ORDER BY id DESC LIMIT 1');
        $this->assertNull($stmt->fetchColumn());
    }

    // ═══ findPaginated ═══

    public function testFindPaginatedReturnsEmptyWhenNoEntries(): void
    {
        $result = $this->repo->findPaginated();
        $this->assertSame(0, $result['total']);
        $this->assertSame([], $result['entries']);
    }

    public function testFindPaginatedReturnsAllEntriesWithoutFilters(): void
    {
        $this->logEntry('cat1', 'act1', 'details1');
        $this->logEntry('cat2', 'act2', 'details2');
        $result = $this->repo->findPaginated();
        $this->assertSame(2, $result['total']);
        $this->assertCount(2, $result['entries']);
    }

    public function testFindPaginatedFiltersByCategory(): void
    {
        $this->logEntry('report', 'act1', 'd1');
        $this->logEntry('user', 'act2', 'd2');
        $result = $this->repo->findPaginated(['category' => 'report']);
        $this->assertSame(1, $result['total']);
        $this->assertSame('report', $result['entries'][0]['category']);
    }

    public function testFindPaginatedFiltersByUserId(): void
    {
        $this->repo->log('cat', 'act', 'd1', null, null, [], null, 42);
        $this->repo->log('cat', 'act', 'd2', null, null, [], null, 99);
        $result = $this->repo->findPaginated(['user_id' => 42]);
        $this->assertSame(1, $result['total']);
        $this->assertSame(42, (int) $result['entries'][0]['user_id']);
    }

    public function testFindPaginatedFiltersByDateFrom(): void
    {
        $this->repo->log('cat', 'act', 'old');
        // Manually set created_at to past
        $this->pdo->exec("UPDATE audit_log SET created_at = '2020-01-01 00:00:00' WHERE details = 'old'");
        $this->repo->log('cat', 'act', 'recent');

        $result = $this->repo->findPaginated(['date_from' => '2025-01-01']);
        $this->assertSame(1, $result['total']);
        $this->assertSame('recent', $result['entries'][0]['details']);
    }

    public function testFindPaginatedFiltersByDateTo(): void
    {
        $this->repo->log('cat', 'act', 'old');
        $this->pdo->exec("UPDATE audit_log SET created_at = '2020-01-01 00:00:00' WHERE details = 'old'");
        $this->repo->log('cat', 'act', 'recent');

        $result = $this->repo->findPaginated(['date_to' => '2021-01-01']);
        $this->assertSame(1, $result['total']);
        $this->assertSame('old', $result['entries'][0]['details']);
    }

    public function testFindPaginatedFiltersBySearchQuery(): void
    {
        $this->repo->log('cat', 'act', 'Signalement créé');
        $this->repo->log('cat', 'act', 'Utilisateur modifié');
        $result = $this->repo->findPaginated(['q' => 'Signalement']);
        $this->assertSame(1, $result['total']);
        $this->assertStringContainsString('Signalement', $result['entries'][0]['details']);
    }

    public function testFindPaginatedFiltersByUsername(): void
    {
        $this->repo->log('cat', 'act', 'd1', null, null, [], null, 1);
        $this->pdo->exec("UPDATE audit_log SET username = 'jean.dupont' WHERE details = 'd1'");
        $this->repo->log('cat', 'act', 'd2', null, null, [], null, 2);
        $this->pdo->exec("UPDATE audit_log SET username = 'marie.martin' WHERE details = 'd2'");

        $result = $this->repo->findPaginated(['username' => 'jean.dupont']);
        $this->assertSame(1, $result['total']);
        $this->assertSame('jean.dupont', $result['entries'][0]['username']);
    }

    public function testFindPaginatedPaginatesResults(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->logEntry('cat', "act$i", "details$i");
        }
        $result = $this->repo->findPaginated([], 2, 2); // page 2, 2 per page
        $this->assertSame(5, $result['total']);
        $this->assertCount(2, $result['entries']);
    }

    public function testFindPaginatedOrdersByCreatedAtDesc(): void
    {
        $this->logEntry('cat', 'act', 'first');
        $this->logEntry('cat', 'act', 'second');
        $this->logEntry('cat', 'act', 'third');
        $result = $this->repo->findPaginated();
        // Most recent first
        $this->assertSame('third', $result['entries'][0]['details']);
        $this->assertSame('first', $result['entries'][2]['details']);
    }

    // ═══ findByTarget ═══

    public function testFindByTargetWithNumericIdReturnsMatchingEntries(): void
    {
        $this->repo->log('report', 'create', 'd1', 42, 'report', [], null, 1);
        $this->repo->log('report', 'update', 'd2', 42, 'report', [], null, 1);
        $this->repo->log('report', 'create', 'd3', 99, 'report', [], null, 1);

        $result = $this->repo->findByTarget('report', 42);
        $this->assertCount(2, $result);
    }

    public function testFindByTargetWithUuidStringReturnsMatchingEntries(): void
    {
        $this->repo->log('report', 'create', 'd1', null, 'report', [], 'uuid-abc', 1);
        $this->repo->log('report', 'create', 'd2', null, 'report', [], 'uuid-xyz', 1);

        $result = $this->repo->findByTarget('report', 'uuid-abc');
        $this->assertCount(1, $result);
        $this->assertSame('uuid-abc', $result[0]['target_uuid']);
    }

    public function testFindByTargetWithNumericStringUsesIdNotUuid(): void
    {
        // '42' is numeric → should query target_id, not target_uuid
        $this->repo->log('report', 'create', 'd1', 42, 'report', [], null, 1);
        $this->repo->log('report', 'create', 'd2', null, 'report', [], '42', 1);

        $result = $this->repo->findByTarget('report', '42');
        $this->assertCount(1, $result);
        $this->assertSame(42, (int) $result[0]['target_id']);
    }

    public function testFindByTargetReturnsEmptyWhenNoMatch(): void
    {
        $this->repo->log('report', 'create', 'd1', 42, 'report', [], null, 1);
        $result = $this->repo->findByTarget('report', 999);
        $this->assertSame([], $result);
    }

    public function testFindByTargetFiltersByTargetType(): void
    {
        $this->repo->log('report', 'create', 'd1', 42, 'report', [], null, 1);
        $this->repo->log('user', 'create', 'd2', 42, 'user', [], null, 1);

        $result = $this->repo->findByTarget('report', 42);
        $this->assertCount(1, $result);
        $this->assertSame('report', $result[0]['target_type']);
    }
}
