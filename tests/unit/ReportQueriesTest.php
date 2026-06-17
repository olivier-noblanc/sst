<?php
/**
 * Report Queries Unit Tests — Application SST DREETS BFC
 *
 * Tests report query functions with an in-memory SQLite database.
 * Covers reportSelectWithSite(), createReport(), getReportByUuid(),
 * getReportsByRegistry(), updateReport(), abandonReport(), and
 * respondToReport().
 */

use PHPUnit\Framework\TestCase;

class ReportQueriesTest extends TestCase
{
    private static PDO $pdo;
    private static int $siteId;
    private static int $userId;

    public static function setUpBeforeClass(): void
    {
        self::$pdo = getDB();

        // Clean tables to avoid UNIQUE constraint conflicts with shared in-memory DB
        self::$pdo->exec('PRAGMA foreign_keys = OFF');
        self::$pdo->exec('DELETE FROM report_access_log');
        self::$pdo->exec('DELETE FROM report_state_history');
        self::$pdo->exec('DELETE FROM report_responses');
        self::$pdo->exec('DELETE FROM reports');
        self::$pdo->exec('DELETE FROM users');
        self::$pdo->exec('DELETE FROM sites');
        self::$pdo->exec('DELETE FROM config_app');
        self::$pdo->exec('PRAGMA foreign_keys = ON');

        // Seed: one site
        self::$pdo->exec("INSERT INTO sites (code, nom, is_active) VALUES ('UR21', 'UR Côte-d''Or', 1)");
        self::$siteId = (int) self::$pdo->lastInsertId();

        // Seed: one user (agent)
        self::$pdo->exec("INSERT INTO users (nom, prenom, username, role, site_id, is_active) VALUES ('Martin', 'Jean', 'jean.martin', 'agent', " . self::$siteId . ", 1)");
        self::$userId = (int) self::$pdo->lastInsertId();
    }

    // ─── reportSelectWithSite() ────────────────────────────────────────────

    public function testReportSelectWithSiteReturnsSql(): void
    {
        $sql = reportSelectWithSite();
        // After v3.19.0: explicit column selection instead of r.* (BLOB excluded from list queries)
        $this->assertStringContainsString('r.uuid', $sql);
        $this->assertStringContainsString('s.code as site_code', $sql);
        $this->assertStringContainsString('s.nom as site_nom', $sql);
        $this->assertStringContainsString('LEFT JOIN sites s', $sql);
        // Ensure BLOB is NOT included in list queries
        $this->assertStringNotContainsString('r.attachment_blob', $sql);
    }

    // ─── createReport() ────────────────────────────────────────────────────

    public function testCreateReportReturnsUuid(): void
    {
        $data = [
            'type'              => 'rsst',
            'objet'             => 'Test signalement RSST',
            'description'       => 'Description du signalement de test',
            'date_evenement'    => '2025-03-15',
            'heure_evenement'   => '14:30',
            'lieu'              => 'Bureau 201',
            'declarant_id'      => self::$userId,
            'declarant_nom'     => 'Martin',
            'declarant_prenom'  => 'Jean',
            'site_id'           => self::$siteId,
            'is_confidential'   => 0,
            'attachment_blob'   => null,
            'attachment_name'   => null,
            'attachment_mime'   => null,
        ];

        $uuid = createReport(self::$pdo, $data);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid);
    }

    public function testCreateReportGeneratesReference(): void
    {
        $data = [
            'type'              => 'rami',
            'objet'             => 'Test RAMI',
            'description'       => 'Description RAMI test',
            'date_evenement'    => '2025-06-01',
            'declarant_id'      => self::$userId,
            'declarant_nom'     => 'Martin',
            'declarant_prenom'  => 'Jean',
            'site_id'           => self::$siteId,
        ];

        $uuid = createReport(self::$pdo, $data);
        $report = getReportByUuid(self::$pdo, $uuid);
        $this->assertNotNull($report);
        $this->assertMatchesRegularExpression('/^rami-\d{2}-\d{3}$/', $report['reference']);
        $this->assertEquals('rami', $report['type']);
        $this->assertEquals('nouveau', $report['etat']);
    }

    // ─── getReportByUuid() ─────────────────────────────────────────────────

    public function testGetReportByUuidReturnsNullForInvalidUuid(): void
    {
        $this->assertNull(getReportByUuid(self::$pdo, 'not-a-uuid'));
    }

    public function testGetReportByUuidReturnsNullForNonexistentUuid(): void
    {
        $this->assertNull(getReportByUuid(self::$pdo, '00000000-0000-0000-0000-000000000000'));
    }

    // ─── updateReport() ────────────────────────────────────────────────────

    public function testUpdateReportModifiesFields(): void
    {
        $data = [
            'type'              => 'dgi',
            'objet'             => 'DGI original',
            'description'       => 'Description originale',
            'date_evenement'    => '2025-01-10',
            'declarant_id'      => self::$userId,
            'declarant_nom'     => 'Martin',
            'declarant_prenom'  => 'Jean',
            'site_id'           => self::$siteId,
        ];
        $uuid = createReport(self::$pdo, $data);

        $updateData = [
            'objet'           => 'DGI modifié',
            'description'     => 'Description modifiée',
            'date_evenement'  => '2025-01-11',
            'heure_evenement' => null,
            'lieu'            => null,
            'pour_compte_nom' => null,
            'pour_compte_prenom' => null,
            'nature_auteur'   => null,
            'type_acte'       => null,
            'is_confidential' => 1,
        ];

        $result = updateReport(self::$pdo, $uuid, $updateData, self::$userId);
        $this->assertTrue($result);

        $report = getReportByUuid(self::$pdo, $uuid);
        $this->assertEquals('DGI modifié', $report['objet']);
        $this->assertEquals('Description modifiée', $report['description']);
        $this->assertEquals(1, (int) $report['is_confidential']);
    }

    // ─── abandonReport() ──────────────────────────────────────────────────

    public function testAbandonReportChangesEtat(): void
    {
        $data = [
            'type'              => 'rsst',
            'objet'             => 'To abandon',
            'description'       => 'Will be abandoned',
            'date_evenement'    => '2025-02-01',
            'declarant_id'      => self::$userId,
            'declarant_nom'     => 'Martin',
            'declarant_prenom'  => 'Jean',
            'site_id'           => self::$siteId,
        ];
        $uuid = createReport(self::$pdo, $data);

        $result = abandonReport(self::$pdo, $uuid, self::$userId);
        $this->assertTrue($result);

        $report = getReportByUuid(self::$pdo, $uuid);
        $this->assertEquals('abandonne', $report['etat']);
    }

    // ─── respondToReport() ────────────────────────────────────────────────

    public function testRespondToReportChangesEtat(): void
    {
        $data = [
            'type'              => 'rsst',
            'objet'             => 'To respond',
            'description'       => 'Will get response',
            'date_evenement'    => '2025-04-01',
            'declarant_id'      => self::$userId,
            'declarant_nom'     => 'Martin',
            'declarant_prenom'  => 'Jean',
            'site_id'           => self::$siteId,
        ];
        $uuid = createReport(self::$pdo, $data);

        // Add a superviseur user for responding
        self::$pdo->exec("INSERT INTO users (nom, prenom, username, role, site_id, is_active) VALUES ('Sup', 'Anne', 'anne.sup', 'superviseur', " . self::$siteId . ", 1)");
        $supId = (int) self::$pdo->lastInsertId();

        $result = respondToReport(self::$pdo, $uuid, $supId, 'Prise en charge du signalement.', 'en_cours');
        $this->assertEquals('true', $result['status']);

        $report = getReportByUuid(self::$pdo, $uuid);
        $this->assertEquals('en_cours', $report['etat']);
        $this->assertEquals('Prise en charge du signalement.', $report['reponse']);
    }

    // ─── isValidUuid() ────────────────────────────────────────────────────

    public function testIsValidUuid(): void
    {
        $this->assertTrue(isValidUuid('550e8400-e29b-41d4-a716-446655440000'));
        $this->assertFalse(isValidUuid('not-a-uuid'));
        $this->assertFalse(isValidUuid(''));
    }

    // ─── countReportsByState() ────────────────────────────────────────────

    public function testCountReportsByState(): void
    {
        $counts = countReportsByState(self::$pdo, 'rsst', self::$siteId, true);
        $this->assertArrayHasKey('nouveau', $counts);
        $this->assertArrayHasKey('en_cours', $counts);
        $this->assertArrayHasKey('traite', $counts);
        $this->assertArrayHasKey('total', $counts);
    }
}
