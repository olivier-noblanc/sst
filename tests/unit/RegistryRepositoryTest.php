<?php
/**
 * RegistryRepository Tests — Application SST DREETS BFC
 *
 * TDD: tests written BEFORE implementation.
 */

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
        $id = $this->repo->create([
            'code' => 'test_reg',
            'label' => 'Registre Test',
            'short_label' => 'TEST',
            'color_theme' => 'rsst',
        ]);
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testCreateAndFindByCode(): void
    {
        $this->repo->create([
            'code' => 'my_reg',
            'label' => 'Mon Registre',
            'short_label' => 'MR',
            'color_theme' => 'vert',
        ]);
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
        $this->repo->create(['code' => 'dup', 'label' => 'A', 'short_label' => 'A', 'color_theme' => 'rsst']);
        $this->expectException(Exception::class);
        $this->repo->create(['code' => 'dup', 'label' => 'B', 'short_label' => 'B', 'color_theme' => 'rami']);
    }

    // ─── READ ────────────────────────────────────────────────────────────────

    public function testFindAllReturnsAllRegistries(): void
    {
        $this->repo->create(['code' => 'r1', 'label' => 'R1', 'short_label' => 'R1', 'color_theme' => 'rsst']);
        $this->repo->create(['code' => 'r2', 'label' => 'R2', 'short_label' => 'R2', 'color_theme' => 'rami', 'is_enabled' => 0]);
        $all = $this->repo->findAll();
        $this->assertCount(2, $all);
    }

    public function testFindEnabledReturnsOnlyEnabled(): void
    {
        $this->repo->create(['code' => 'on', 'label' => 'On', 'short_label' => 'ON', 'color_theme' => 'rsst', 'is_enabled' => 1]);
        $this->repo->create(['code' => 'off', 'label' => 'Off', 'short_label' => 'OFF', 'color_theme' => 'rami', 'is_enabled' => 0]);
        $enabled = $this->repo->findEnabled();
        $this->assertCount(1, $enabled);
        $this->assertSame('on', $enabled[0]['code']);
    }

    public function testFindEnabledRespectsSortOrder(): void
    {
        $this->repo->create(['code' => 'b', 'label' => 'B', 'short_label' => 'B', 'color_theme' => 'rsst', 'sort_order' => 2]);
        $this->repo->create(['code' => 'a', 'label' => 'A', 'short_label' => 'A', 'color_theme' => 'rami', 'sort_order' => 1]);
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
        $id = $this->repo->create(['code' => 'byid', 'label' => 'By ID', 'short_label' => 'BI', 'color_theme' => 'rsst']);
        $reg = $this->repo->findById($id);
        $this->assertNotNull($reg);
        $this->assertSame('byid', $reg['code']);
    }

    // ─── UPDATE ──────────────────────────────────────────────────────────────

    public function testUpdateModifiesFields(): void
    {
        $id = $this->repo->create(['code' => 'upd', 'label' => 'Old', 'short_label' => 'OLD', 'color_theme' => 'rsst']);
        $this->repo->update($id, [
            'label' => 'New Label',
            'color_theme' => 'dgi',
            'is_enabled' => 0,
        ]);
        $reg = $this->repo->findById($id);
        $this->assertSame('New Label', $reg['label']);
        $this->assertSame('dgi', $reg['color_theme']);
        $this->assertSame(0, (int) $reg['is_enabled']);
    }

    public function testToggleEnabled(): void
    {
        $id = $this->repo->create(['code' => 'tog', 'label' => 'Tog', 'short_label' => 'TOG', 'color_theme' => 'rsst']);
        $this->assertTrue($this->repo->toggleEnabled($id, false));
        $reg = $this->repo->findById($id);
        $this->assertSame(0, (int) $reg['is_enabled']);
        $this->assertTrue($this->repo->toggleEnabled($id, true));
        $reg = $this->repo->findById($id);
        $this->assertSame(1, (int) $reg['is_enabled']);
    }

    // ─── DELETE ──────────────────────────────────────────────────────────────

    public function testDeleteNonSystemRegistry(): void
    {
        $id = $this->repo->create(['code' => 'del', 'label' => 'Del', 'short_label' => 'DEL', 'color_theme' => 'rsst']);
        $this->assertTrue($this->repo->delete($id));
        $this->assertNull($this->repo->findById($id));
    }

    public function testDeleteSystemRegistryFails(): void
    {
        $id = $this->repo->create(['code' => 'sys', 'label' => 'Sys', 'short_label' => 'SYS', 'color_theme' => 'rsst', 'is_system' => 1]);
        $this->assertFalse($this->repo->delete($id));
        $this->assertNotNull($this->repo->findById($id));
    }

    // ─── COUNT ───────────────────────────────────────────────────────────────

    public function testCountByCode(): void
    {
        $this->repo->create(['code' => 'cnt', 'label' => 'Cnt', 'short_label' => 'CNT', 'color_theme' => 'rsst']);
        $this->assertSame(1, $this->repo->countByCode('cnt'));
        $this->assertSame(0, $this->repo->countByCode('nope'));
    }

    // ─── DEFAULTS ────────────────────────────────────────────────────────────

    public function testDefaultValuesAreSet(): void
    {
        $id = $this->repo->create(['code' => 'def', 'label' => 'Def', 'short_label' => 'DEF', 'color_theme' => 'rsst']);
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
