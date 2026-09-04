<?php
/**
 * Access Helper Unit Tests — Visibility Normalization & Confidential Logging
 *
 * Tests the access control functions from src/helpers/access.php:
 * - normalizeVisibilityValue()
 * - logConfidentialReportAccess()
 */

use PHPUnit\Framework\TestCase;
use App\DTO\SessionUser;

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
    //
    // Audit #85 — la version précédente utilisait des id/code hardcodés
    // (site_id=1, user id=5/99, code 'UR21') répétés dans les 4 méthodes de
    // ce fichier, avec un `DELETE FROM sites/users/reports` non scopé en
    // tête de chaque test. Sous ordre aléatoire (Infection), avec d'autres
    // classes de test dans le même process PHPUnit partagé, ces DELETE
    // pouvaient échouer silencieusement sur une contrainte FK (une table
    // que ce fichier ne vide pas — report_state_history, notification_settings,
    // etc. — référençant encore la ligne), laissant l'INSERT suivant entrer
    // en collision de clé primaire. Remplacé par des identifiants garantis
    // uniques (uniqid(), id auto-incrémentés) : plus aucun DELETE partagé,
    // plus aucun risque de collision quel que soit ce que font les autres
    // tests en parallèle du même process.

    private function makeSiteAndUser(string $role): int
    {
        $pdo = getDB();
        $code = 'T' . substr(str_replace('.', '', uniqid('', true)), -8);
        $pdo->exec("INSERT INTO sites (code, nom, is_active) VALUES (" . $pdo->quote($code) . ", 'Site Test', 1)");
        $siteId = (int) $pdo->lastInsertId();
        $username = 'u' . str_replace('.', '', uniqid('', true));
        $pdo->exec("INSERT INTO users (username, nom, prenom, role, site_id, is_active, email) VALUES ("
            . $pdo->quote($username) . ", 'Test', 'User', " . $pdo->quote($role) . ", $siteId, 1, 'fixture@dreets-bfc.gouv.fr')");
        return (int) $pdo->lastInsertId();
    }

    private function makeReport(int $declarantId, bool $isConfidential): \App\DTO\ReportData
    {
        $pdo = getDB();
        $declarant = $pdo->query("SELECT site_id FROM users WHERE id = $declarantId")->fetch();
        $siteId = (int) $declarant['site_id'];
        $uuid = generateUuid();
        $ref = 'rsst-test-' . substr(uniqid('', true), -8);
        $pdo->exec("INSERT INTO reports (uuid, reference, type, objet, description, date_evenement, declarant_id, declarant_nom, declarant_prenom, site_id, is_confidential, etat) VALUES ("
            . $pdo->quote($uuid) . ", " . $pdo->quote($ref) . ", 'rsst', 'Objet', 'Desc', '2026-01-01', "
            . "$declarantId, 'Test', 'User', $siteId, " . ($isConfidential ? 1 : 0) . ", 'nouveau')");
        $report = \App\Repository\ReportRepository::instance()->findById($uuid);
        $this->assertNotNull($report, 'setup failed: report not found after insert');
        return $report;
    }

    private function accessLogCountFor(string $reportUuid): int
    {
        $stmt = getDB()->prepare('SELECT COUNT(*) FROM report_access_log WHERE report_uuid = ?');
        $stmt->execute([$reportUuid]);
        return (int) $stmt->fetchColumn();
    }

    public function testLogConfidentialReportAccessInsertsRow(): void
    {
        $declarantId = $this->makeSiteAndUser('agent');
        $accessorId = $this->makeSiteAndUser('superviseur');
        $report = $this->makeReport($declarantId, true);

        logConfidentialReportAccess(getDB(), $report, SessionUser::fromArray(['id' => $accessorId, 'role' => 'superviseur']));

        $stmt = getDB()->prepare('SELECT report_uuid, user_id, role FROM report_access_log WHERE report_uuid = ?');
        $stmt->execute([$report->uuid]);
        $row = $stmt->fetch();
        $this->assertIsArray($row, 'logConfidentialReportAccess() did not insert a row — check for a FOREIGN KEY violation being silently swallowed.');
        $this->assertEquals($report->uuid, $row['report_uuid']);
        $this->assertEquals($accessorId, (int) $row['user_id']);
        $this->assertEquals('superviseur', $row['role']);
    }

    public function testLogConfidentialReportAccessSkipsNonConfidential(): void
    {
        $declarantId = $this->makeSiteAndUser('agent');
        $accessorId = $this->makeSiteAndUser('superviseur');
        $report = $this->makeReport($declarantId, false);

        logConfidentialReportAccess(getDB(), $report, SessionUser::fromArray(['id' => $accessorId, 'role' => 'superviseur']));

        $this->assertEquals(0, $this->accessLogCountFor($report->uuid));
    }

    public function testLogConfidentialReportAccessSkipsAgentRole(): void
    {
        $declarantId = $this->makeSiteAndUser('agent');
        $accessorId = $this->makeSiteAndUser('agent');
        $report = $this->makeReport($declarantId, true);

        logConfidentialReportAccess(getDB(), $report, SessionUser::fromArray(['id' => $accessorId, 'role' => 'agent']));

        $this->assertEquals(0, $this->accessLogCountFor($report->uuid));
    }

    public function testLogConfidentialReportAccessSkipsOwnReport(): void
    {
        $declarantId = $this->makeSiteAndUser('superviseur');
        $report = $this->makeReport($declarantId, true);

        logConfidentialReportAccess(getDB(), $report, SessionUser::fromArray(['id' => $declarantId, 'role' => 'superviseur']));

        $this->assertEquals(0, $this->accessLogCountFor($report->uuid));
    }
}
