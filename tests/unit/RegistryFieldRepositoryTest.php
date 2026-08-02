<?php
/**
 * RegistryFieldRepository Tests — Application SST DREETS BFC
 *
 * TDD: tests written BEFORE implementation.
 */

use App\DTO\CreateRegistryCommand;
use App\DTO\CreateRegistryFieldCommand;
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
        return $this->registries->create(new CreateRegistryCommand(
            code: $code,
            label: strtoupper($code) . ' Label',
            shortLabel: strtoupper($code),
            colorTheme: 'rsst',
        ));
    }

    // ─── CREATE ──────────────────────────────────────────────────────────────

    public function testCreateFieldReturnsId(): void
    {
        $regId = $this->createRegistry('test');
        $id = $this->fields->create($regId, new CreateRegistryFieldCommand(
            fieldCode: 'nature_auteur',
            label: 'Nature de l\'auteur',
            fieldType: 'select',
            options: json_encode(['usager' => 'Usager', 'collegue' => 'Collègue']),
            isRequired: 0,
            sortOrder: 1,
        ));
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testCreateAndFindByRegistry(): void
    {
        $regId = $this->createRegistry('rami');
        $this->fields->create($regId, new CreateRegistryFieldCommand(
            fieldCode: 'nature_auteur',
            label: 'Nature de l\'auteur',
            fieldType: 'select',
            options: json_encode(['usager' => 'Usager']),
            isRequired: 0,
            sortOrder: 1,
        ));
        $this->fields->create($regId, new CreateRegistryFieldCommand(
            fieldCode: 'type_acte',
            label: 'Type d\'acte',
            fieldType: 'select',
            options: json_encode(['verbal' => 'Verbal']),
            isRequired: 0,
            sortOrder: 2,
        ));
        $result = $this->fields->findByRegistry($regId);
        $this->assertCount(2, $result);
        $this->assertSame('nature_auteur', $result[0]['field_code']);
        $this->assertSame('type_acte', $result[1]['field_code']);
    }

    public function testFindByRegistryRespectsSortOrder(): void
    {
        $regId = $this->createRegistry('test');
        $this->fields->create($regId, new CreateRegistryFieldCommand(fieldCode: 'b', label: 'B', fieldType: 'text', sortOrder: 2));
        $this->fields->create($regId, new CreateRegistryFieldCommand(fieldCode: 'a', label: 'A', fieldType: 'text', sortOrder: 1));
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
        $this->fields->create($regId, new CreateRegistryFieldCommand(
            fieldCode: 'my_field',
            label: 'My Field',
            fieldType: 'text',
        ));
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

    public function testDeleteCascadeOnRegistryDelete(): void
    {
        $regId = $this->createRegistry('test');
        $this->fields->create($regId, new CreateRegistryFieldCommand(fieldCode: 'f1', label: 'F1', fieldType: 'text'));
        $this->fields->create($regId, new CreateRegistryFieldCommand(fieldCode: 'f2', label: 'F2', fieldType: 'text'));
        $this->registries->delete($regId);
        $this->assertCount(0, $this->fields->findByRegistry($regId));
    }

    // ─── REQUIRED FIELDS ─────────────────────────────────────────────────────

    public function testRequiredFieldsAreFlagged(): void
    {
        $regId = $this->createRegistry('test');
        $this->fields->create($regId, new CreateRegistryFieldCommand(fieldCode: 'opt', label: 'Optional', fieldType: 'text', isRequired: 0));
        $this->fields->create($regId, new CreateRegistryFieldCommand(fieldCode: 'req', label: 'Required', fieldType: 'text', isRequired: 1));
        $result = $this->fields->findByRegistry($regId);
        $this->assertSame(0, (int) $result[0]['is_required']);
        $this->assertSame(1, (int) $result[1]['is_required']);
    }

    // ─── OPTIONS (JSON) ──────────────────────────────────────────────────────

    public function testOptionsStoredAsJson(): void
    {
        $regId = $this->createRegistry('test');
        $options = ['usager' => 'Usager', 'collegue' => 'Collègue', 'tiers' => 'Tiers'];
        $this->fields->create($regId, new CreateRegistryFieldCommand(
            fieldCode: 'nature',
            label: 'Nature',
            fieldType: 'select',
            options: json_encode($options),
        ));
        $field = $this->fields->findByCode($regId, 'nature');
        $this->assertNotNull($field);
        $decoded = json_decode($field['options'], true);
        $this->assertSame($options, $decoded);
    }

    public function testOptionsNullForTextFields(): void
    {
        $regId = $this->createRegistry('test');
        $this->fields->create($regId, new CreateRegistryFieldCommand(fieldCode: 'txt', label: 'Text', fieldType: 'text'));
        $field = $this->fields->findByCode($regId, 'txt');
        $this->assertNull($field['options']);
    }
}
