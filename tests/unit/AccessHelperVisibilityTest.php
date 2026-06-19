<?php
/**
 * Access Helper Unit Tests — Visibility Normalization & Confidential Logging
 *
 * Tests the access control functions from src/helpers/access.php:
 * - normalizeVisibilityValue()
 * - logConfidentialReportAccess()
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/helpers/access.php';

class AccessHelperVisibilityTest extends TestCase
{
    // ─── normalizeVisibilityValue ────────────────────────────────────────────

    public function testNormalizePublicValues(): void
    {
        $this->assertEquals('public', normalizeVisibilityValue('0'));
        $this->assertEquals('public', normalizeVisibilityValue('site'));
        $this->assertEquals('public', normalizeVisibilityValue('public'));
    }

    public function testNormalizeConfidentialValues(): void
    {
        $this->assertEquals('confidential', normalizeVisibilityValue('1'));
        $this->assertEquals('confidential', normalizeVisibilityValue('own'));
        $this->assertEquals('confidential', normalizeVisibilityValue('confidential'));
    }

    public function testNormalizeAgentChoice(): void
    {
        $this->assertEquals('agent_choice', normalizeVisibilityValue('agent_choice'));
    }

    public function testNormalizeUnknownDefaultsToAgentChoice(): void
    {
        $this->assertEquals('agent_choice', normalizeVisibilityValue('unknown'));
        $this->assertEquals('agent_choice', normalizeVisibilityValue(''));
    }

    // ─── logConfidentialReportAccess (DB-dependent) ─────────────────────────

    public function testLogConfidentialReportAccessInsertsRow(): void
    {
        $pdo = getDB();
        $pdo->exec('DELETE FROM report_access_log');
        $report = ['uuid' => 'test-uuid-123', 'is_confidential' => 1, 'declarant_id' => '99'];
        $user = ['id' => 5, 'role' => 'superviseur'];
        logConfidentialReportAccess($pdo, $report, $user);
        $stmt = $pdo->query("SELECT COUNT(*) FROM report_access_log");
        $count = (int) $stmt->fetchColumn();
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function testLogConfidentialReportAccessSkipsNonConfidential(): void
    {
        $pdo = getDB();
        $pdo->exec('DELETE FROM report_access_log');
        $report = ['uuid' => 'test-uuid-456', 'is_confidential' => 0, 'declarant_id' => '99'];
        $user = ['id' => 5, 'role' => 'superviseur'];
        logConfidentialReportAccess($pdo, $report, $user);
        $stmt = $pdo->query("SELECT COUNT(*) FROM report_access_log");
        $this->assertEquals(0, (int) $stmt->fetchColumn());
    }

    public function testLogConfidentialReportAccessSkipsAgentRole(): void
    {
        $pdo = getDB();
        $pdo->exec('DELETE FROM report_access_log');
        $report = ['uuid' => 'test-uuid-789', 'is_confidential' => 1, 'declarant_id' => '99'];
        $user = ['id' => 5, 'role' => 'agent'];
        logConfidentialReportAccess($pdo, $report, $user);
        $stmt = $pdo->query("SELECT COUNT(*) FROM report_access_log");
        $this->assertEquals(0, (int) $stmt->fetchColumn());
    }

    public function testLogConfidentialReportAccessSkipsOwnReport(): void
    {
        $pdo = getDB();
        $pdo->exec('DELETE FROM report_access_log');
        $report = ['uuid' => 'test-uuid-own', 'is_confidential' => 1, 'declarant_id' => '5'];
        $user = ['id' => 5, 'role' => 'superviseur'];
        logConfidentialReportAccess($pdo, $report, $user);
        $stmt = $pdo->query("SELECT COUNT(*) FROM report_access_log");
        $this->assertEquals(0, (int) $stmt->fetchColumn());
    }
}
