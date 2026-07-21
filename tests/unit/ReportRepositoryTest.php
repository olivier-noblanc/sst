<?php
use PHPUnit\Framework\TestCase;
use App\Repository\ReportRepository;
use App\DTO\CreateReportCommand;

class ReportRepositoryTest extends TestCase
{
    private PDO $pdo;
    private ReportRepository $repo;
    private int $siteId;
    private int $userId;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        $this->repo = new ReportRepository($this->pdo);
        // Clean and seed (getDB() is a singleton, so we must reset)
        $this->pdo->exec('DELETE FROM report_access_log');
        $this->pdo->exec('DELETE FROM report_state_history');
        $this->pdo->exec('DELETE FROM report_responses');
        $this->pdo->exec('DELETE FROM reports');
        $this->pdo->exec('DELETE FROM users');
        $this->pdo->exec('DELETE FROM sites');
        $this->pdo->exec("INSERT INTO sites (code, nom, is_active) VALUES ('UD21', 'Cote-d-Or', 1)");
        $this->siteId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO users (nom, prenom, username, role, site_id, is_active) VALUES ('Martin', 'Jean', 'jean.martin', 'agent', {$this->siteId}, 1)");
        $this->userId = (int) $this->pdo->lastInsertId();
    }

    public function testCreateAndFindById(): void
    {
        $cmd = new CreateReportCommand(
            type: 'rsst', objet: 'Test', description: 'Desc',
            dateEvenement: '2026-01-15', heureEvenement: '10:30',
            lieu: 'Bureau', declarantId: $this->userId, declarantNom: 'Martin',
            declarantPrenom: 'Jean', siteId: $this->siteId, siteText: null,
            pole: null, serviceAffectation: null, telephoneMobile: null,
            isConfidential: 1, consentSyndicat: 0,
            natureAuteur: null, typeActe: null,
            pourCompteNom: null, pourComptePrenom: null,
            attachmentBlob: null, attachmentName: null, attachmentMime: null,
        );
        $uuid = $this->repo->create($cmd);
        $this->assertNotEmpty($uuid);

        $report = $this->repo->findById($uuid);
        $this->assertNotNull($report);
        $this->assertEquals('Test', $report['objet']);
        $this->assertEquals('rsst', $report['type']);
    }

    public function testFindByIdReturnsNullForUnknownUuid(): void
    {
        $this->assertNull($this->repo->findById('nonexistent-uuid'));
    }

    // site_id = 0 is the UI/form sentinel for "no site" (no-site mode: the
    // report form submits an empty hidden field, CreateReportCommand turns
    // that into 0). Regression test for the bug where every report creation
    // failed with a FOREIGN KEY constraint violation whenever site_id came
    // through as 0 — the app's core feature (submitting a report) was
    // completely broken on any install running in no-site mode.
    public function testCreateWithSiteIdZeroSucceeds(): void
    {
        $cmd = new CreateReportCommand(
            type: 'rsst', objet: 'Sans site', description: 'Desc',
            dateEvenement: '2026-01-15', heureEvenement: null,
            lieu: null, declarantId: $this->userId, declarantNom: 'Martin',
            declarantPrenom: 'Jean', siteId: 0, siteText: null,
            pole: null, serviceAffectation: null, telephoneMobile: null,
            isConfidential: 1, consentSyndicat: 0,
            natureAuteur: null, typeActe: null,
            pourCompteNom: null, pourComptePrenom: null,
            attachmentBlob: null, attachmentName: null, attachmentMime: null,
        );
        $uuid = $this->repo->create($cmd);
        $this->assertNotEmpty($uuid);

        $report = $this->repo->findById($uuid);
        $this->assertNotNull($report);
        $this->assertNull($report['site_id']);
    }

    // findPaginated() had no test exercising more than page 1 with a
    // generous perPage — the offset math ($page - 1) * $perPage was
    // effectively unverified. This creates 5 reports and checks that
    // paging through with perPage=2 returns every report exactly once,
    // across 3 pages, with the last page correctly short (1 report) and
    // no page ever including a UUID seen on an earlier page. Doesn't
    // depend on exact ordering (SQLite timestamp ties aren't guaranteed
    // ordering) — only on the union/overlap of what each page returns.
    public function testFindPaginatedOffsetIsCorrect(): void
    {
        $filter = new \App\DTO\ReportFilter(type: 'rsst');
        $createdUuids = [];
        for ($i = 0; $i < 5; $i++) {
            $createdUuids[] = $this->repo->create($this->makeCommand("Pagination $i"));
        }

        $seenUuids = [];
        $totalFromEachPage = [];
        foreach ([1, 2, 3] as $page) {
            $result = $this->repo->findPaginated($filter, $page, 2);
            $totalFromEachPage[] = $result['total'];
            foreach ($result['reports'] as $report) {
                $this->assertNotContains(
                    $report['uuid'],
                    $seenUuids,
                    "Page $page returned uuid {$report['uuid']} already seen on an earlier page — offset math is wrong."
                );
                $seenUuids[] = $report['uuid'];
            }
        }

        $this->assertEquals([5, 5, 5], $totalFromEachPage, 'total should stay 5 regardless of which page is requested');
        $this->assertCount(5, $seenUuids, 'paging through with perPage=2 across 3 pages should surface all 5 reports exactly once');
        sort($createdUuids);
        sort($seenUuids);
        $this->assertEquals($createdUuids, $seenUuids);
    }

    public function testFindPaginatedDefaultPageIsOne(): void
    {
        // Confirms the $page = 1 default actually behaves like an explicit
        // page 1, not page 0 or page 2 (both would silently skip/duplicate
        // rows via a wrong offset).
        $uuid = $this->repo->create($this->makeCommand('Default page test'));
        $filter = new \App\DTO\ReportFilter(type: 'rsst');

        $default = $this->repo->findPaginated($filter, perPage: 20);
        $explicitPage1 = $this->repo->findPaginated($filter, 1, 20);

        $this->assertEquals($explicitPage1['reports'], $default['reports']);
        $this->assertContains($uuid, array_column($default['reports'], 'uuid'));
    }

    /** @param array<string, mixed> $overrides */
    private function makeCommand(string $objet, array $overrides = []): CreateReportCommand
    {
        $defaults = [
            'type' => 'rsst', 'objet' => $objet, 'description' => 'Desc',
            'dateEvenement' => '2026-01-15', 'heureEvenement' => null,
            'lieu' => null, 'declarantId' => $this->userId, 'declarantNom' => 'Martin',
            'declarantPrenom' => 'Jean', 'siteId' => $this->siteId, 'siteText' => null,
            'pole' => null, 'serviceAffectation' => null, 'telephoneMobile' => null,
            'isConfidential' => 1, 'consentSyndicat' => 0,
            'natureAuteur' => null, 'typeActe' => null,
            'pourCompteNom' => null, 'pourComptePrenom' => null,
            'attachmentBlob' => null, 'attachmentName' => null, 'attachmentMime' => null,
        ];
        return new CreateReportCommand(...array_merge($defaults, $overrides));
    }

}
