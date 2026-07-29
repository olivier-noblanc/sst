<?php
/**
 * Report Queries Unit Tests — Application SST DREETS BFC
 *
 * Tests report query functions with an in-memory SQLite database.
 * Covers getReportByUuid() and isValidUuid() (still live, in
 * src/queries/report_queries.php).
 *
 * Write operations (create, update, abandon, respond) and
 * countByState() go through App\Repository\ReportRepository directly:
 * the procedural wrappers (createReport(), getReportsByRegistry() in
 * report_queries.php; updateReport(), abandonReport(),
 * respondToReport() in report_response_queries.php, now deleted;
 * countReportsByState() in report_count_queries.php) had no callers
 * outside this test file — createReport()/updateReport() even
 * diverged from ReportRepository (no transaction wrapping).
 */

use App\DTO\CreateReportCommand;
use App\DTO\UpdateReportCommand;
use App\Enum\ReportType;
use App\Repository\ReportRepository;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

class ReportQueriesTest extends TestCase
{
    private static PDO $pdo;
    private static int $siteId;
    private static int $userId;
    private static ReportRepository $reports;

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

        self::$reports = ReportRepository::instance();
    }

    /** @param array<string, mixed> $overrides */
    private static function makeCreateCommand(array $overrides = []): CreateReportCommand
    {
        $defaults = [
            'type' => ReportType::Rsst->value, 'objet' => 'Test', 'description' => 'Desc',
            'dateEvenement' => '2025-03-15', 'heureEvenement' => null, 'lieu' => null,
            'declarantId' => self::$userId, 'declarantNom' => 'Martin', 'declarantPrenom' => 'Jean',
            'siteId' => self::$siteId, 'siteText' => null, 'pole' => null,
            'serviceAffectation' => null, 'telephoneMobile' => null,
            'isConfidential' => false, 'consentSyndicat' => false,
            'natureAuteur' => null, 'typeActe' => null,
            'pourCompteNom' => null, 'pourComptePrenom' => null,
            'attachmentBlob' => null, 'attachmentName' => null, 'attachmentMime' => null,
        ];
        $data = array_merge($defaults, $overrides);
        return new CreateReportCommand(...$data);
    }

    // ─── create() ──────────────────────────────────────────────────────────

    public function testCreateReportReturnsUuid(): void
    {
        $uuid = self::$reports->create(self::makeCreateCommand([
            'objet' => 'Test signalement RSST', 'description' => 'Description du signalement de test',
            'heureEvenement' => '14:30', 'lieu' => 'Bureau 201',
        ]));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid);
    }

    public function testCreateReportGeneratesReference(): void
    {
        $uuid = self::$reports->create(self::makeCreateCommand([
            'type' => 'rami', 'objet' => 'Test RAMI', 'description' => 'Description RAMI test',
            'dateEvenement' => '2025-06-01',
        ]));
        $report = self::$reports->findById($uuid);
        $this->assertNotNull($report);
        $this->assertMatchesRegularExpression('/^rami-\d{2}-\d{3}$/', $report->reference);
        $this->assertEquals('rami', $report->type);
        $this->assertEquals('nouveau', $report->etat);
    }

    // ─── getReportByUuid() ─────────────────────────────────────────────────

    public function testGetReportByUuidReturnsNullForInvalidUuid(): void
    {
        $this->assertNull(self::$reports->findById('not-a-uuid'));
    }

    public function testGetReportByUuidReturnsNullForNonexistentUuid(): void
    {
        $this->assertNull(self::$reports->findById('00000000-0000-0000-0000-000000000000'));
    }

    // ─── update() ──────────────────────────────────────────────────────────

    public function testUpdateReportModifiesFields(): void
    {
        $uuid = self::$reports->create(self::makeCreateCommand([
            'type' => 'dgi', 'objet' => 'DGI original', 'description' => 'Description originale',
            'dateEvenement' => '2025-01-10',
        ]));

        $updateCmd = new UpdateReportCommand(
            objet: 'DGI modifié', description: 'Description modifiée',
            dateEvenement: '2025-01-11', heureEvenement: null, lieu: null,
            siteText: null, pole: null, serviceAffectation: null, telephoneMobile: null,
            isConfidential: 1, consentSyndicat: 0,
        );
        $result = self::$reports->update($uuid, $updateCmd, self::$userId);
        $this->assertTrue($result);

        $report = self::$reports->findById($uuid);
        $this->assertEquals('DGI modifié', $report->objet);
        $this->assertEquals('Description modifiée', $report->description);
        $this->assertEquals(1, (int) $report->isConfidential);
    }

    // ─── abandon() ─────────────────────────────────────────────────────────

    public function testAbandonReportChangesEtat(): void
    {
        $uuid = self::$reports->create(self::makeCreateCommand([
            'objet' => 'To abandon', 'description' => 'Will be abandoned', 'dateEvenement' => '2025-02-01',
        ]));

        $result = self::$reports->abandon($uuid, self::$userId);
        $this->assertTrue($result);

        $report = self::$reports->findById($uuid);
        $this->assertEquals('abandonne', $report->etat);
    }

    // ─── respondToReport() ─────────────────────────────────────────────────

    public function testRespondToReportChangesEtat(): void
    {
        $uuid = self::$reports->create(self::makeCreateCommand([
            'objet' => 'To respond', 'description' => 'Will get response', 'dateEvenement' => '2025-04-01',
        ]));

        // Add a superviseur user for responding
        self::$pdo->exec("INSERT INTO users (nom, prenom, username, role, site_id, is_active) VALUES ('Sup', 'Anne', 'anne.sup', 'superviseur', " . self::$siteId . ", 1)");
        $supId = (int) self::$pdo->lastInsertId();

        $result = self::$reports->respondToReport($uuid, $supId, 'Prise en charge du signalement.', 'en_cours');
        $this->assertEquals(\App\Enum\RespondStatus::Ok, $result['status']);

        $report = self::$reports->findById($uuid);
        $this->assertEquals('en_cours', $report->etat);
        $this->assertEquals('Prise en charge du signalement.', $report->reponse);
    }

    // ─── isValidUuid() ─────────────────────────────────────────────────────

    public function testIsValidUuid(): void
    {
        $this->assertTrue(isValidUuid('550e8400-e29b-41d4-a716-446655440000'));
        $this->assertFalse(isValidUuid('not-a-uuid'));
        $this->assertFalse(isValidUuid(''));
    }

    // ─── countByState() ────────────────────────────────────────────────────

    public function testCountReportsByState(): void
    {
        $counts = self::$reports->countByState('rsst', self::$siteId, true);
        $this->assertSame(0, $counts->nouveau);
        $this->assertSame(0, $counts->enCours);
        $this->assertSame(0, $counts->traite);
        $this->assertSame(0, $counts->total);
    }
}
