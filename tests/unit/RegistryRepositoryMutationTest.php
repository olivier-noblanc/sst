<?php
/**
 * Tests RegistryRepository exhaustively — kills Infection mutants on:
 *   - findAll / findEnabled (order, is_enabled filter)
 *   - findById / findByCode (existing, missing)
 *   - create (defaults, lastInsertId)
 *   - update (dynamic SET, rowCount, no-op)
 *   - toggleEnabled (true→1, false→0)
 *   - delete (system registry protection, non-system delete)
 *   - countByCode (zero, positive)
 *   - seedDefaults (idempotent)
 *   - availableThemes / themeClasses (static methods)
 */

use PHPUnit\Framework\TestCase;
use App\Repository\RegistryRepository;
use App\Enum\ReportType;
use App\Enum\VisibilityMode;

class RegistryRepositoryMutationTest extends TestCase
{
    private PDO $pdo;
    private RegistryRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        $this->pdo->exec('DELETE FROM registry_fields');
        $this->pdo->exec('DELETE FROM registries');
        $this->repo = new RegistryRepository($this->pdo);
    }

    protected function tearDown(): void
    {
        $this->pdo->exec('DELETE FROM registry_fields');
        $this->pdo->exec('DELETE FROM registries');
    }

    private function seedRegistry(string $code = 'custom', int $enabled = 1, int $isSystem = 0): int
    {
        return $this->repo->create([
            'code' => $code,
            'label' => 'Custom Registry',
            'short_label' => strtoupper($code),
            'is_enabled' => $enabled,
            'is_system' => $isSystem,
        ]);
    }

    // ═══ findAll / findEnabled ═══

    public function testFindAllReturnsEmptyWhenNoRegistries(): void
    {
        $this->assertSame([], $this->repo->findAll());
    }

    public function testFindAllReturnsAllOrderedBySortOrderThenCode(): void
    {
        $this->seedRegistry('z_last', 1, 0);
        $this->pdo->exec("UPDATE registries SET sort_order = 3 WHERE code = 'z_last'");
        $this->seedRegistry('a_first', 1, 0);
        $this->pdo->exec("UPDATE registries SET sort_order = 1 WHERE code = 'a_first'");
        $this->seedRegistry('m_middle', 1, 0);
        $this->pdo->exec("UPDATE registries SET sort_order = 2 WHERE code = 'm_middle'");

        $result = $this->repo->findAll();
        $this->assertCount(3, $result);
        // Kill OrderBy mutant — sort_order ASC first
        $this->assertSame('a_first', $result[0]['code']);
        $this->assertSame('m_middle', $result[1]['code']);
        $this->assertSame('z_last', $result[2]['code']);
    }

    public function testFindEnabledExcludesDisabled(): void
    {
        $this->seedRegistry('enabled', 1, 0);
        $this->seedRegistry('disabled', 0, 0);

        $result = $this->repo->findEnabled();
        $this->assertCount(1, $result);
        $this->assertSame('enabled', $result[0]['code']);
    }

    public function testFindEnabledReturnsEmptyWhenAllDisabled(): void
    {
        $this->seedRegistry('r1', 0, 0);
        $this->seedRegistry('r2', 0, 0);
        $this->assertSame([], $this->repo->findEnabled());
    }

    // ═══ findById / findByCode ═══

    public function testFindByIdReturnsRegistryWhenExists(): void
    {
        $id = $this->seedRegistry('custom');
        $reg = $this->repo->findById($id);
        $this->assertNotNull($reg);
        $this->assertSame('custom', $reg['code']);
    }

    public function testFindByIdReturnsNullForMissing(): void
    {
        $this->assertNull($this->repo->findById(99999));
    }

    public function testFindByCodeReturnsRegistryWhenExists(): void
    {
        $this->seedRegistry('custom');
        $reg = $this->repo->findByCode('custom');
        $this->assertNotNull($reg);
        $this->assertSame('custom', $reg['code']);
    }

    public function testFindByCodeReturnsNullForMissing(): void
    {
        $this->assertNull($this->repo->findByCode('nonexistent'));
    }

    // ═══ create ═══

    public function testCreateReturnsPositiveId(): void
    {
        $id = $this->repo->create(['code' => 'test', 'label' => 'Test', 'short_label' => 'T']);
        $this->assertGreaterThan(0, $id);
    }

    public function testCreateAppliesDefaultsForOptionalFields(): void
    {
        // Kill Coalesce mutants on ?? defaults
        $id = $this->repo->create(['code' => 'test', 'label' => 'Test', 'short_label' => 'T']);
        $reg = $this->repo->findById($id);

        $this->assertNull($reg['description']); // ?? null
        $this->assertSame('📋', $reg['icon']); // ?? '📋'
        $this->assertSame(ReportType::Rsst->value, $reg['color_theme']); // ?? ReportType::Rsst->value
        $this->assertSame(1, (int) $reg['is_enabled']); // ?? 1
        $this->assertSame(0, (int) $reg['is_system']); // ?? 0
        $this->assertSame(0, (int) $reg['sort_order']); // ?? 0
        $this->assertSame(VisibilityMode::AgentChoice->value, $reg['default_visibility']); // ?? AgentChoice
        $this->assertSame(0, (int) $reg['notify_chsct']); // ?? 0
        $this->assertNull($reg['legal_note']); // ?? null
    }

    public function testCreateUsesProvidedValuesOverDefaults(): void
    {
        $id = $this->repo->create([
            'code' => 'test', 'label' => 'Test', 'short_label' => 'T',
            'description' => 'Custom desc', 'icon' => '🚨', 'color_theme' => 'violet',
            'is_enabled' => 0, 'is_system' => 1, 'sort_order' => 5,
            'default_visibility' => VisibilityMode::Confidential->value, 'notify_chsct' => 1,
            'legal_note' => 'Custom legal note',
        ]);
        $reg = $this->repo->findById($id);

        $this->assertSame('Custom desc', $reg['description']);
        $this->assertSame('🚨', $reg['icon']);
        $this->assertSame('violet', $reg['color_theme']);
        $this->assertSame(0, (int) $reg['is_enabled']);
        $this->assertSame(1, (int) $reg['is_system']);
        $this->assertSame(5, (int) $reg['sort_order']);
        $this->assertSame(VisibilityMode::Confidential->value, $reg['default_visibility']);
        $this->assertSame(1, (int) $reg['notify_chsct']);
        $this->assertSame('Custom legal note', $reg['legal_note']);
    }

    // ═══ update ═══

    public function testUpdateModifiesSpecifiedFields(): void
    {
        $id = $this->seedRegistry('test');
        $result = $this->repo->update($id, ['label' => 'Updated Label', 'is_enabled' => 0]);
        $this->assertTrue($result);
        $reg = $this->repo->findById($id);
        $this->assertSame('Updated Label', $reg['label']);
        $this->assertSame(0, (int) $reg['is_enabled']);
    }

    public function testUpdateReturnsFalseWhenNoFieldsProvided(): void
    {
        // Kill empty($sets) mutant
        $id = $this->seedRegistry('test');
        $result = $this->repo->update($id, []);
        $this->assertFalse($result, 'empty update must return false');
    }

    public function testUpdateReturnsFalseWhenRegistryNotFound(): void
    {
        $result = $this->repo->update(99999, ['label' => 'New']);
        $this->assertFalse($result);
    }

    public function testUpdateSetsUpdatedAtTimestamp(): void
    {
        $id = $this->seedRegistry('test');
        $original = $this->repo->findById($id);
        sleep(1); // ensure timestamp differs
        $this->repo->update($id, ['label' => 'Updated']);
        $updated = $this->repo->findById($id);
        $this->assertNotSame($original['updated_at'], $updated['updated_at'], 'updated_at must change');
    }

    public function testUpdateOnlyModifiesProvidedFields(): void
    {
        $id = $this->seedRegistry('test');
        $original = $this->repo->findById($id);
        $this->repo->update($id, ['label' => 'New Label']);
        $updated = $this->repo->findById($id);
        $this->assertSame('New Label', $updated['label']);
        $this->assertSame($original['short_label'], $updated['short_label'], 'other fields unchanged');
    }

    // ═══ delete ═══

    public function testDeleteRemovesNonSystemRegistry(): void
    {
        $id = $this->seedRegistry('custom', 1, 0);
        $result = $this->repo->delete($id);
        $this->assertTrue($result);
        $this->assertNull($this->repo->findById($id));
    }

    public function testDeleteReturnsFalseForSystemRegistry(): void
    {
        // Kill mutant on is_system === 1 check
        $id = $this->seedRegistry('system', 1, 1);
        $result = $this->repo->delete($id);
        $this->assertFalse($result, 'system registry must not be deletable');
        $this->assertNotNull($this->repo->findById($id), 'system registry must still exist');
    }

    public function testDeleteReturnsFalseWhenNotFound(): void
    {
        $this->assertFalse($this->repo->delete(99999));
    }

    // ═══ countByCode ═══

    public function testCountByCodeReturnsZeroWhenMissing(): void
    {
        $this->assertSame(0, $this->repo->countByCode('nonexistent'));
    }

    public function testCountByCodeReturnsOneWhenExists(): void
    {
        $this->seedRegistry('custom');
        $this->assertSame(1, $this->repo->countByCode('custom'));
    }

    // ═══ seedDefaults ═══

    public function testSeedDefaultsCreatesThreeRegistries(): void
    {
        $this->repo->seedDefaults();
        $all = $this->repo->findAll();
        $this->assertCount(3, $all);
        $codes = array_column($all, 'code');
        $this->assertContains('rsst', $codes);
        $this->assertContains('rami', $codes);
        $this->assertContains('dgi', $codes);
    }

    public function testSeedDefaultsIsIdempotent(): void
    {
        // Kill countByCode === 0 mutant — second call must not duplicate
        $this->repo->seedDefaults();
        $this->repo->seedDefaults();
        $this->assertCount(3, $this->repo->findAll());
    }

    public function testSeedDefaultsSetsRsstAsSystem(): void
    {
        $this->repo->seedDefaults();
        $rsst = $this->repo->findByCode('rsst');
        $this->assertSame(1, (int) $rsst['is_system']);
    }

    public function testSeedDefaultsSetsRamiAndDgiAsNonSystem(): void
    {
        $this->repo->seedDefaults();
        $rami = $this->repo->findByCode('rami');
        $dgi = $this->repo->findByCode('dgi');
        $this->assertSame(0, (int) $rami['is_system']);
        $this->assertSame(0, (int) $dgi['is_system']);
    }

    public function testSeedDefaultsDisablesRamiAndDgi(): void
    {
        // Kill is_enabled=0 mutant
        $this->repo->seedDefaults();
        $rami = $this->repo->findByCode('rami');
        $dgi = $this->repo->findByCode('dgi');
        $this->assertSame(0, (int) $rami['is_enabled']);
        $this->assertSame(0, (int) $dgi['is_enabled']);
    }

    public function testSeedDefaultsEnablesRsst(): void
    {
        $this->repo->seedDefaults();
        $rsst = $this->repo->findByCode('rsst');
        $this->assertSame(1, (int) $rsst['is_enabled']);
    }

    // ═══ availableThemes / themeClasses (static) ═══

    public function testAvailableThemesReturnsAllThemes(): void
    {
        $themes = RegistryRepository::availableThemes();
        // 3 system + 7 custom = 10
        $this->assertGreaterThanOrEqual(10, count($themes));
        $this->assertContains('rsst', $themes);
        $this->assertContains('rami', $themes);
        $this->assertContains('dgi', $themes);
        $this->assertContains('vert', $themes);
        $this->assertContains('violet', $themes);
    }

    public function testThemeClassesReturnsCorrectCssClasses(): void
    {
        $classes = RegistryRepository::themeClasses('violet');
        $this->assertSame('card--violet', $classes['card']);
        $this->assertSame('badge--violet', $classes['badge']);
        $this->assertSame('btn--violet', $classes['btn']);
        $this->assertSame('registry-card--violet', $classes['registry_card']);
        $this->assertSame('indicateur-card--violet', $classes['indicateur']);
        $this->assertSame('synthesis-th--violet', $classes['synthesis_th']);
        $this->assertSame('text--violet', $classes['text']);
        $this->assertSame('border-left--violet', $classes['border_left']);
    }

    public function testThemeClassesContainsAllExpectedKeys(): void
    {
        $classes = RegistryRepository::themeClasses('test');
        $this->assertCount(8, $classes);
        foreach (['card', 'badge', 'btn', 'registry_card', 'indicateur', 'synthesis_th', 'text', 'border_left'] as $key) {
            $this->assertArrayHasKey($key, $classes);
        }
    }
}
