<?php
/**
 * RegistryFieldRepository Tests — Application SST DREETS BFC
 *
 * TDD: tests written BEFORE implementation.
 */

use App\Repository\RegistryRepository;
use App\Repository\RegistryFieldRepository;
use PHPUnit\Framework\TestCase;

class RegistryFieldRepositoryTest extends TestCase
{
    private RegistryRepository $registries;
    private RegistryFieldRepository $fields;

    protected function setUp(): void
    {
        $pdo = getDB();
        $pdo->exec('DELETE FROM registry_fields');
        $pdo->exec('DELETE FROM registries');
        $this->registries = new RegistryRepository($pdo);
        $this->fields = new RegistryFieldRepository($pdo);
    }

    protected function tearDown(): void
    {
        // getDB() is a process-wide singleton shared by the whole PHPUnit
        // run — restore what setUp() wiped so later test classes don't find
        // `registries` empty. See reseedDefaultRegistries() in bootstrap.php.
        reseedDefaultRegistries(getDB());
    }

    private function createRegistry(string $code): int
    {
        return $this->registries->create([
            'code' => $code,
            'label' => strtoupper($code) . ' Label',
            'short_label' => strtoupper($code),
            'color_theme' => 'rsst',
        ]);
    }

    // ─── CREATE ──────────────────────────────────────────────────────────────

    public function testCreateFieldReturnsId(): void
    {
        $regId = $this->createRegistry('test');
        $id = $this->fields->create($regId, [
            'field_code' => 'nature_auteur',
            'label' => 'Nature de l\'auteur',
            'field_type' => 'select',
            'options' => json_encode(['usager' => 'Usager', 'collegue' => 'Collègue']),
            'is_required' => 0,
            'sort_order' => 1,
        ]);
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testCreateAndFindByRegistry(): void
    {
        $regId = $this->createRegistry('rami');
        $this->fields->create($regId, [
            'field_code' => 'nature_auteur',
            'label' => 'Nature de l\'auteur',
            'field_type' => 'select',
            'options' => json_encode(['usager' => 'Usager']),
            'is_required' => 0,
            'sort_order' => 1,
        ]);
        $this->fields->create($regId, [
            'field_code' => 'type_acte',
            'label' => 'Type d\'acte',
            'field_type' => 'select',
            'options' => json_encode(['verbal' => 'Verbal']),
            'is_required' => 0,
            'sort_order' => 2,
        ]);
        $result = $this->fields->findByRegistry($regId);
        $this->assertCount(2, $result);
        $this->assertSame('nature_auteur', $result[0]['field_code']);
        $this->assertSame('type_acte', $result[1]['field_code']);
    }

    public function testFindByRegistryRespectsSortOrder(): void
    {
        $regId = $this->createRegistry('test');
        $this->fields->create($regId, ['field_code' => 'b', 'label' => 'B', 'field_type' => 'text', 'sort_order' => 2]);
        $this->fields->create($regId, ['field_code' => 'a', 'label' => 'A', 'field_type' => 'text', 'sort_order' => 1]);
        $result = $this->fields->findByRegistry($regId);
        $this->assertSame('a', $result[0]['field_code']);
        $this->assertSame('b', $result[1]['field_code']);
    }

    public function testFindByRegistryReturnsEmptyForUnknown(): void
    {
        $this->assertCount(0, $this->fields->findByRegistry(99999));
    }

    public function testFindByRegistryReturnsEmptyWhenNoFields(): void
    {
        $regId = $this->createRegistry('empty');
        $this->assertCount(0, $this->fields->findByRegistry($regId));
    }

    // ─── FIND BY CODE ────────────────────────────────────────────────────────

    public function testFindByCodeReturnsField(): void
    {
        $regId = $this->createRegistry('test');
        $this->fields->create($regId, [
            'field_code' => 'my_field',
            'label' => 'My Field',
            'field_type' => 'text',
        ]);
        $field = $this->fields->findByCode($regId, 'my_field');
        $this->assertNotNull($field);
        $this->assertSame('my_field', $field['field_code']);
        $this->assertSame('My Field', $field['label']);
    }

    public function testFindByCodeReturnsNullForUnknown(): void
    {
        $regId = $this->createRegistry('test');
        $this->assertNull($this->fields->findByCode($regId, 'nonexistent'));
    }

    // ─── UPDATE ──────────────────────────────────────────────────────────────

    public function testUpdateModifiesFields(): void
    {
        $regId = $this->createRegistry('test');
        $id = $this->fields->create($regId, [
            'field_code' => 'upd',
            'label' => 'Old Label',
            'field_type' => 'text',
        ]);
        $this->fields->update($id, [
            'label' => 'New Label',
            'field_type' => 'select',
            'is_required' => 1,
        ]);
        $field = $this->fields->findById($id);
        $this->assertSame('New Label', $field['label']);
        $this->assertSame('select', $field['field_type']);
        $this->assertSame(1, (int) $field['is_required']);
    }

    // ─── DELETE ──────────────────────────────────────────────────────────────

    public function testDeleteField(): void
    {
        $regId = $this->createRegistry('test');
        $id = $this->fields->create($regId, ['field_code' => 'del', 'label' => 'Del', 'field_type' => 'text']);
        $this->assertTrue($this->fields->delete($id));
        $this->assertNull($this->fields->findById($id));
    }

    public function testDeleteCascadeOnRegistryDelete(): void
    {
        $regId = $this->createRegistry('test');
        $this->fields->create($regId, ['field_code' => 'f1', 'label' => 'F1', 'field_type' => 'text']);
        $this->fields->create($regId, ['field_code' => 'f2', 'label' => 'F2', 'field_type' => 'text']);
        $this->registries->delete($regId);
        $this->assertCount(0, $this->fields->findByRegistry($regId));
    }

    // ─── REQUIRED FIELDS ─────────────────────────────────────────────────────

    public function testRequiredFieldsAreFlagged(): void
    {
        $regId = $this->createRegistry('test');
        $this->fields->create($regId, ['field_code' => 'opt', 'label' => 'Optional', 'field_type' => 'text', 'is_required' => 0]);
        $this->fields->create($regId, ['field_code' => 'req', 'label' => 'Required', 'field_type' => 'text', 'is_required' => 1]);
        $result = $this->fields->findByRegistry($regId);
        $this->assertSame(0, (int) $result[0]['is_required']);
        $this->assertSame(1, (int) $result[1]['is_required']);
    }

    // ─── OPTIONS (JSON) ──────────────────────────────────────────────────────

    public function testOptionsStoredAsJson(): void
    {
        $regId = $this->createRegistry('test');
        $options = ['usager' => 'Usager', 'collegue' => 'Collègue', 'tiers' => 'Tiers'];
        $this->fields->create($regId, [
            'field_code' => 'nature',
            'label' => 'Nature',
            'field_type' => 'select',
            'options' => json_encode($options),
        ]);
        $field = $this->fields->findByCode($regId, 'nature');
        $this->assertNotNull($field);
        $decoded = json_decode($field['options'], true);
        $this->assertSame($options, $decoded);
    }

    public function testOptionsNullForTextFields(): void
    {
        $regId = $this->createRegistry('test');
        $this->fields->create($regId, ['field_code' => 'txt', 'label' => 'Text', 'field_type' => 'text']);
        $field = $this->fields->findByCode($regId, 'txt');
        $this->assertNull($field['options']);
    }
}
