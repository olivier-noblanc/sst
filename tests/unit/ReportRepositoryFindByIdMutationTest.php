<?php
/**
 * Tests ReportRepository::findById() exhaustively — kills Infection mutants on:
 *   - Every CastString on (string) ($row['col'] ?? '') — lines 131-165
 *   - Every Coalesce on ?? '' / ?? null / ?? 0
 *   - CastInt on (int) $row['declarant_id'], (int) $row['is_confidential'], etc.
 *   - isValidUuid guard (line 104)
 *   - is_array check (line 127)
 *   - SiteId::fromDatabase (line 147)
 *
 * Strategy : insert a report with ALL fields populated, then assert every
 * single property of the returned ReportData object with assertSame (not
 * assertEquals) — this kills CastString mutants (which would change the type).
 */

use PHPUnit\Framework\TestCase;
use App\Repository\ReportRepository;
use App\DTO\ReportData;

class ReportRepositoryFindByIdMutationTest extends TestCase
{
    private PDO $pdo;
    private ReportRepository $repo;
    private int $siteId;
    private int $declarantId;
    private int $repondantId;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        cleanupAllForTest($this->pdo);
        $this->pdo->exec('DELETE FROM sites');

        $this->repo = new ReportRepository($this->pdo);

        // Seed site
        $this->pdo->prepare('INSERT INTO sites (code, nom, departement) VALUES (?, ?, ?)')
            ->execute(['UR21', 'UR Test', 'Doubs']);
        $this->siteId = (int) $this->pdo->lastInsertId();

        // Seed declarant
        $this->pdo->prepare('INSERT INTO users (username, nom, prenom, role, site_id, is_active, email) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute(['decl.user', 'Dupont', 'Jean', 'agent', $this->siteId, 1, 'fixture@dreets-bfc.gouv.fr']);
        $this->declarantId = (int) $this->pdo->lastInsertId();

        // Seed respondent (superviseur)
        $this->pdo->prepare('INSERT INTO users (username, nom, prenom, role, site_id, is_active, email) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute(['resp.user', 'Martin', 'Sophie', 'superviseur', $this->siteId, 1, 'fixture@dreets-bfc.gouv.fr']);
        $this->repondantId = (int) $this->pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        cleanupAllForTest($this->pdo);
        $this->pdo->exec('DELETE FROM sites');
    }

    private function seedFullReport(): string
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        // NOTE: pour_compte_de is a FK to users(id) — we omit it here to avoid
        // FK constraint violations. The test asserts pourCompteDe === '' (the
        // DTO default when the DB column is NULL).
        $this->pdo->prepare('
            INSERT INTO reports (
                uuid, reference, type, objet, description, date_evenement, heure_evenement,
                lieu, declarant_id, declarant_nom, declarant_prenom,
                pour_compte_nom, pour_compte_prenom,
                nature_auteur, type_acte,
                site_id, site_text, pole, service_affectation, telephone_mobile,
                is_confidential, consent_syndicat, etat,
                repondant_id, date_reponse, reponse,
                attachment_name, attachment_mime
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )
        ')->execute([
            $uuid, 'rsst-25-001', 'rsst', 'Test objet', 'Test description', '2026-01-15', '10:30',
            'Bureau 204', $this->declarantId, 'Dupont', 'Jean',
            'Durant', 'Pierre',
            'usager', 'verbal',
            $this->siteId, 'UR21', 'Pôle A', 'Service B', '0601020304',
            1, 1, 'traite',
            $this->repondantId, '2026-01-20 14:00:00', 'Pris en charge',
            'report.pdf', 'application/pdf',
        ]);
        return $uuid;
    }

    public function testFindByIdReturnsNullForInvalidUuid(): void
    {
        // Kill isValidUuid guard mutant
        $this->assertNull($this->repo->findById('not-a-uuid'));
        $this->assertNull($this->repo->findById(''));
        $this->assertNull($this->repo->findById('550e8400-not-valid'));
    }

    public function testFindByIdReturnsNullForMissingReport(): void
    {
        // Kill is_array check mutant
        $this->assertNull($this->repo->findById('00000000-0000-0000-0000-000000000000'));
    }

    public function testFindByIdReturnsAllStringFieldsWithExactValues(): void
    {
        $uuid = $this->seedFullReport();
        $report = $this->repo->findById($uuid);
        $this->assertNotNull($report);

        // Kill CastString mutants — each assertSame checks type AND value
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $report->uuid);
        $this->assertSame('rsst-25-001', $report->reference);
        $this->assertSame('rsst', $report->type);
        $this->assertSame('Test objet', $report->objet);
        $this->assertSame('Test description', $report->description);
        $this->assertSame('2026-01-15', $report->dateEvenement);
        $this->assertSame('10:30', $report->heureEvenement);
        $this->assertSame('Bureau 204', $report->lieu);
        $this->assertSame('Dupont', $report->declarantNom);
        $this->assertSame('Jean', $report->declarantPrenom);
        $this->assertSame('', $report->pourCompteDe, 'pourCompteDe is NULL in DB → defaults to empty string');
        $this->assertSame('Durant', $report->pourCompteNom);
        $this->assertSame('Pierre', $report->pourComptePrenom);
        $this->assertSame('usager', $report->natureAuteur);
        $this->assertSame('verbal', $report->typeActe);
        $this->assertSame('UR21', $report->siteText);
        $this->assertSame('Pôle A', $report->pole);
        $this->assertSame('Service B', $report->serviceAffectation);
        $this->assertSame('0601020304', $report->telephoneMobile);
        $this->assertSame('traite', $report->etat);
        $this->assertSame('Pris en charge', $report->reponse);
        $this->assertSame('report.pdf', $report->attachmentName);
        $this->assertSame('application/pdf', $report->attachmentMime);
        $this->assertSame('UR21', $report->siteCode);
        $this->assertSame('UR Test', $report->siteNom);
        $this->assertSame('Martin', $report->repondantNom);
        $this->assertSame('Sophie', $report->repondantPrenom);
    }

    public function testFindByIdReturnsAllIntFieldsWithExactValues(): void
    {
        $uuid = $this->seedFullReport();
        $report = $this->repo->findById($uuid);
        $this->assertNotNull($report);

        // Kill CastInt mutants
        $this->assertSame($this->declarantId, $report->declarantId);
        $this->assertIsInt($report->declarantId);
        $this->assertSame(1, $report->isConfidential);
        $this->assertIsInt($report->isConfidential);
        $this->assertSame(1, $report->consentSyndicat);
        $this->assertIsInt($report->consentSyndicat);
        $this->assertSame($this->repondantId, $report->repondantId);
        $this->assertIsInt($report->repondantId);
        $this->assertSame($this->siteId, $report->siteId);
        $this->assertIsInt($report->siteId);
    }

    public function testFindByIdReturnsNullableFieldsCorrectly(): void
    {
        $uuid = $this->seedFullReport();
        $report = $this->repo->findById($uuid);
        $this->assertNotNull($report);

        // Kill Coalesce mutants on ?? null
        $this->assertSame('2026-01-20 14:00:00', $report->dateReponse);
        $this->assertSame('Pris en charge', $report->reponse);
        $this->assertSame('report.pdf', $report->attachmentName);
        $this->assertSame('application/pdf', $report->attachmentMime);
        $this->assertSame('Martin', $report->repondantNom);
        $this->assertSame('Sophie', $report->repondantPrenom);
    }

    public function testFindByIdHandlesNullNullableFields(): void
    {
        // Insert a report with minimal fields — nullables should be null
        $uuid = '660e8400-e29b-41d4-a716-446655440000';
        $this->pdo->prepare('
            INSERT INTO reports (uuid, reference, type, objet, description, date_evenement,
                declarant_id, declarant_nom, declarant_prenom, etat)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ')->execute([
            $uuid, 'rsst-25-002', 'rsst', 'Minimal', 'Desc', '2026-01-01',
            $this->declarantId, 'Dupont', 'Jean', 'nouveau',
        ]);

        $report = $this->repo->findById($uuid);
        $this->assertNotNull($report);

        // Kill Coalesce mutants — null fields must stay null, not ''
        $this->assertNull($report->repondantId);
        $this->assertNull($report->dateReponse);
        $this->assertNull($report->reponse);
        $this->assertNull($report->attachmentName);
        $this->assertNull($report->attachmentMime);
        $this->assertNull($report->repondantNom);
        $this->assertNull($report->repondantPrenom);
        $this->assertNull($report->siteId, 'site_id null when not set');

        // String fields with ?? '' must be empty string, not null
        $this->assertSame('', $report->heureEvenement);
        $this->assertSame('', $report->lieu);
        $this->assertSame('', $report->pourCompteDe);
        $this->assertSame('', $report->pourCompteNom);
        $this->assertSame('', $report->pourComptePrenom);
        $this->assertSame('', $report->natureAuteur);
        $this->assertSame('', $report->typeActe);
        $this->assertSame('', $report->siteText);
        $this->assertSame('', $report->pole);
        $this->assertSame('', $report->serviceAffectation);
        $this->assertSame('', $report->telephoneMobile);
        $this->assertSame('', $report->siteCode);
        $this->assertSame('', $report->siteNom);

        // Int fields — is_confidential has DB DEFAULT 1, consent_syndicat has DB DEFAULT 0
        $this->assertSame(1, $report->isConfidential, 'DB default is_confidential=1');
        $this->assertSame(0, $report->consentSyndicat, 'DB default consent_syndicat=0');
    }

    public function testFindByIdReturnsReportDataInstance(): void
    {
        $uuid = $this->seedFullReport();
        $report = $this->repo->findById($uuid);
        $this->assertInstanceOf(ReportData::class, $report);
    }

    public function testFindByIdPreservesCreatedAtTimestamp(): void
    {
        $uuid = $this->seedFullReport();
        $report = $this->repo->findById($uuid);
        $this->assertNotEmpty($report->createdAt);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $report->createdAt);
    }
}
