<?php
/**
 * RegistryRepository Tests — Application SST DREETS BFC
 *
 * TDD: tests written BEFORE implementation.
 */

use App\DTO\CreateRegistryCommand;
use App\DTO\UpdateRegistryCommand;
use App\Repository\RegistryRepository;
use PHPUnit\Framework\TestCase;

class RegistryRepositoryTest extends TestCase
{
    private RegistryRepository $repo;

    protected function setUp(): void
    {
        $pdo = getDB();
        $pdo->exec('DELETE FROM registry_fields');
        $pdo->exec('DELETE FROM registries');
        $this->repo = new RegistryRepository($pdo);
    }

    protected function tearDown(): void
    {
        // getDB() is a process-wide singleton shared by the whole PHPUnit
        // run — restore what setUp() wiped so later test classes don't find
        // `registries` empty. See reseedDefaultRegistries() in bootstrap.php.
        reseedDefaultRegistries(getDB());
    }

    // ─── CREATE ──────────────────────────────────────────────────────────────

    public function testCreateReturnsId(): void
    {
        $id = $this->repo->create(new CreateRegistryCommand(
            code: 'test_reg',
            label: 'Registre Test',
            shortLabel: 'TEST',
            colorTheme: 'rsst',
        ));
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testCreateAndFindByCode(): void
    {
        $this->repo->create(new CreateRegistryCommand(
            code: 'my_reg',
            label: 'Mon Registre',
            shortLabel: 'MR',
            colorTheme: 'vert',
        ));
        $reg = $this->repo->findByCode('my_reg');
        $this->assertNotNull($reg);
        $this->assertSame('my_reg', $reg['code']);
        $this->assertSame('Mon Registre', $reg['label']);
        $this->assertSame('MR', $reg['short_label']);
        $this->assertSame('vert', $reg['color_theme']);
        $this->assertSame(1, (int) $reg['is_enabled']);
        $this->assertSame(0, (int) $reg['is_system']);
    }

    public function testCreateDuplicateCodeThrows(): void
    {
        $this->repo->create(new CreateRegistryCommand(code: 'dup', label: 'A', shortLabel: 'A', colorTheme: 'rsst'));
        $this->expectException(Exception::class);
        $this->repo->create(new CreateRegistryCommand(code: 'dup', label: 'B', shortLabel: 'B', colorTheme: 'rami'));
    }

    // ─── READ ────────────────────────────────────────────────────────────────

    public function testFindAllReturnsAllRegistries(): void
    {
        $this->repo->create(new CreateRegistryCommand(code: 'r1', label: 'R1', shortLabel: 'R1', colorTheme: 'rsst'));
        $this->repo->create(new CreateRegistryCommand(code: 'r2', label: 'R2', shortLabel: 'R2', colorTheme: 'rami', isEnabled: 0));
        $all = $this->repo->findAll();
        $this->assertCount(2, $all);
    }

    public function testFindEnabledReturnsOnlyEnabled(): void
    {
        $this->repo->create(new CreateRegistryCommand(code: 'on', label: 'On', shortLabel: 'ON', colorTheme: 'rsst', isEnabled: 1));
        $this->repo->create(new CreateRegistryCommand(code: 'off', label: 'Off', shortLabel: 'OFF', colorTheme: 'rami', isEnabled: 0));
        $enabled = $this->repo->findEnabled();
        $this->assertCount(1, $enabled);
        $this->assertSame('on', $enabled[0]['code']);
    }

    public function testFindEnabledRespectsSortOrder(): void
    {
        $this->repo->create(new CreateRegistryCommand(code: 'b', label: 'B', shortLabel: 'B', colorTheme: 'rsst', sortOrder: 2));
        $this->repo->create(new CreateRegistryCommand(code: 'a', label: 'A', shortLabel: 'A', colorTheme: 'rami', sortOrder: 1));
        $enabled = $this->repo->findEnabled();
        $this->assertSame('a', $enabled[0]['code']);
        $this->assertSame('b', $enabled[1]['code']);
    }

    public function testFindByCodeReturnsNullForUnknown(): void
    {
        $this->assertNull($this->repo->findByCode('nonexistent'));
    }

    public function testFindByIdReturnsRegistry(): void
    {
        $id = $this->repo->create(new CreateRegistryCommand(code: 'byid', label: 'By ID', shortLabel: 'BI', colorTheme: 'rsst'));
        $reg = $this->repo->findById($id);
        $this->assertNotNull($reg);
        $this->assertSame('byid', $reg['code']);
    }

    // ─── UPDATE ──────────────────────────────────────────────────────────────

    public function testUpdateModifiesFields(): void
    {
        $id = $this->repo->create(new CreateRegistryCommand(code: 'upd', label: 'Old', shortLabel: 'OLD', colorTheme: 'rsst'));
        $this->repo->update($id, new UpdateRegistryCommand(
            label: 'New Label',
            colorTheme: 'dgi',
            isEnabled: 0,
        ));
        $reg = $this->repo->findById($id);
        $this->assertSame('New Label', $reg['label']);
        $this->assertSame('dgi', $reg['color_theme']);
        $this->assertSame(0, (int) $reg['is_enabled']);
    }

    // ─── DELETE ──────────────────────────────────────────────────────────────

    public function testDeleteNonSystemRegistry(): void
    {
        $id = $this->repo->create(new CreateRegistryCommand(code: 'del', label: 'Del', shortLabel: 'DEL', colorTheme: 'rsst'));
        $this->assertTrue($this->repo->delete($id));
        $this->assertNull($this->repo->findById($id));
    }

    public function testDeleteSystemRegistryFails(): void
    {
        $id = $this->repo->create(new CreateRegistryCommand(code: 'sys', label: 'Sys', shortLabel: 'SYS', colorTheme: 'rsst', isSystem: 1));
        $this->assertFalse($this->repo->delete($id));
        $this->assertNotNull($this->repo->findById($id));
    }

    // ─── COUNT ───────────────────────────────────────────────────────────────

    public function testCountByCode(): void
    {
        $this->repo->create(new CreateRegistryCommand(code: 'cnt', label: 'Cnt', shortLabel: 'CNT', colorTheme: 'rsst'));
        $this->assertSame(1, $this->repo->countByCode('cnt'));
        $this->assertSame(0, $this->repo->countByCode('nope'));
    }

    // ─── DEFAULTS ────────────────────────────────────────────────────────────

    public function testDefaultValuesAreSet(): void
    {
        $id = $this->repo->create(new CreateRegistryCommand(code: 'def', label: 'Def', shortLabel: 'DEF', colorTheme: 'rsst'));
        $reg = $this->repo->findById($id);
        $this->assertSame(1, (int) $reg['is_enabled']);
        $this->assertSame(0, (int) $reg['is_system']);
        $this->assertSame(0, (int) $reg['sort_order']);
        $this->assertSame('agent_choice', $reg['default_visibility']);
        $this->assertSame(0, (int) $reg['notify_chsct']);
        $this->assertNotNull($reg['created_at']);
        $this->assertNotNull($reg['updated_at']);
    }

    // ─── SEED DEFAULT REGISTRIES ─────────────────────────────────────────────

    public function testSeedDefaultRegistriesCreatesThree(): void
    {
        $this->repo->seedDefaults();
        $all = $this->repo->findAll();
        $this->assertCount(3, $all);
        $codes = array_column($all, 'code');
        $this->assertContains('rsst', $codes);
        $this->assertContains('rami', $codes);
        $this->assertContains('dgi', $codes);
    }

    public function testSeedDefaultRegistriesIsIdempotent(): void
    {
        $this->repo->seedDefaults();
        $this->repo->seedDefaults();
        $all = $this->repo->findAll();
        $this->assertCount(3, $all);
    }

    public function testSeedRsstIsSystem(): void
    {
        $this->repo->seedDefaults();
        $rsst = $this->repo->findByCode('rsst');
        $this->assertNotNull($rsst);
        $this->assertSame(1, (int) $rsst['is_system']);
    }
}
