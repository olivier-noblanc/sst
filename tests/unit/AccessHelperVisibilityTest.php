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
        $pdo->exec('DELETE FROM reports');
        $pdo->exec('DELETE FROM users');
        $pdo->exec('DELETE FROM sites');
        $pdo->exec("INSERT INTO sites (id, code, nom, is_active) VALUES (1, 'UR21', 'UR Test', 1)");
        $pdo->exec("INSERT INTO users (id, username, nom, prenom, role, site_id, is_active) VALUES (5, 'sup.test', 'Sup', 'Test', 'superviseur', 1, 1)");
        $pdo->exec("INSERT INTO users (id, username, nom, prenom, role, site_id, is_active) VALUES (99, 'declarant.test', 'Declarant', 'Test', 'agent', 1, 1)");
        $pdo->exec("INSERT INTO reports (uuid, reference, type, objet, description, date_evenement, declarant_id, declarant_nom, declarant_prenom, site_id, is_confidential, etat) VALUES ('test-uuid-123', 'rsst-26-001', 'rsst', 'Objet', 'Desc', '2026-01-01', 99, 'Declarant', 'Test', 1, 1, 'nouveau')");

        $report = \App\Repository\ReportRepository::instance()->findById('test-uuid-123');
        $this->assertNotNull($report, 'setup failed: report not found after insert');
        $user = ['id' => 5, 'role' => 'superviseur'];
        logConfidentialReportAccess($pdo, $report, $user);

        $row = $pdo->query('SELECT report_uuid, user_id, role FROM report_access_log')->fetch();
        $this->assertIsArray($row, 'logConfidentialReportAccess() did not insert a row — check for a FOREIGN KEY violation being silently swallowed.');
        $this->assertEquals('test-uuid-123', $row['report_uuid']);
        $this->assertEquals(5, (int) $row['user_id']);
        $this->assertEquals('superviseur', $row['role']);
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
