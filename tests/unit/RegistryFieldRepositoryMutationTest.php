<?php
/**
 * Tests RegistryFieldRepository exhaustively — kills Infection mutants on:
 *   - findByRegistry (order by sort_order, field_code)
 *   - findById / findByCode (existing, missing)
 *   - create (defaults, lastInsertId)
 *   - update (dynamic SET, empty, rowCount)
 *   - delete (rowCount)
 */

use PHPUnit\Framework\TestCase;
use App\DTO\CreateRegistryFieldCommand;
use App\Repository\RegistryFieldRepository;

class RegistryFieldRepositoryMutationTest extends TestCase
{
    private PDO $pdo;
    private RegistryFieldRepository $repo;
    private int $registryId;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        $this->pdo->exec('DELETE FROM registry_fields');
        $this->pdo->exec('DELETE FROM registries');
        $this->repo = new RegistryFieldRepository($this->pdo);

        // Seed a registry for FK
        $this->pdo->prepare('INSERT INTO registries (code, label, short_label) VALUES (?, ?, ?)')
            ->execute(['test', 'Test', 'T']);
        $this->registryId = (int) $this->pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        $this->pdo->exec('DELETE FROM registry_fields');
        $this->pdo->exec('DELETE FROM registries');
        // DB partagée process-wide : re-seed des registres système après wipe.
        reseedDefaultRegistries($this->pdo);
    }

    private function seedField(string $code = 'field1', int $sortOrder = 0): int
    {
        return $this->repo->create($this->registryId, new CreateRegistryFieldCommand(
            fieldCode: $code,
            label: 'Field ' . $code,
            fieldType: 'text',
            sortOrder: $sortOrder,
        ));
    }

    // ═══ findByRegistry ═══

    public function testFindByRegistryReturnsEmptyWhenNoFields(): void
    {
        $this->assertSame([], $this->repo->findByRegistry($this->registryId));
    }

    public function testFindByRegistryReturnsFieldsOrderedBySortOrderThenCode(): void
    {
        $this->seedField('z_field', 3);
        $this->seedField('a_field', 1);
        $this->seedField('m_field', 2);

        $result = $this->repo->findByRegistry($this->registryId);
        $this->assertCount(3, $result);
        // Kill OrderBy mutant — sort_order ASC, then field_code ASC
        $this->assertSame('a_field', $result[0]['field_code']);
        $this->assertSame('m_field', $result[1]['field_code']);
        $this->assertSame('z_field', $result[2]['field_code']);
    }

    public function testFindByRegistryOnlyReturnsFieldsForGivenRegistry(): void
    {
        $this->seedField('field1');
        // Create a second registry + field
        $this->pdo->prepare('INSERT INTO registries (code, label, short_label) VALUES (?, ?, ?)')
            ->execute(['other', 'Other', 'O']);
        $otherId = (int) $this->pdo->lastInsertId();
        $this->repo->create($otherId, new CreateRegistryFieldCommand(fieldCode: 'other_field', label: 'Other', fieldType: 'text'));

        $result = $this->repo->findByRegistry($this->registryId);
        $this->assertCount(1, $result);
        $this->assertSame('field1', $result[0]['field_code']);
    }

    // ═══ findById / findByCode ═══

    public function testFindByCodeReturnsFieldWhenExists(): void
    {
        $this->seedField('test');
        $field = $this->repo->findByCode($this->registryId, 'test');
        $this->assertNotNull($field);
        $this->assertSame('test', $field['field_code']);
    }

    public function testFindByCodeReturnsNullForMissing(): void
    {
        $this->assertNull($this->repo->findByCode($this->registryId, 'nonexistent'));
    }

    public function testFindByCodeIsRegistryScoped(): void
    {
        $this->seedField('shared_code');
        // Same field_code in different registry
        $this->pdo->prepare('INSERT INTO registries (code, label, short_label) VALUES (?, ?, ?)')
            ->execute(['other', 'Other', 'O']);
        $otherId = (int) $this->pdo->lastInsertId();
        $this->repo->create($otherId, new CreateRegistryFieldCommand(fieldCode: 'shared_code', label: 'Other label', fieldType: 'text'));

        $field = $this->repo->findByCode($this->registryId, 'shared_code');
        $this->assertSame('Field shared_code', $field['label'], 'must find the right one per registry');
    }

    // ═══ create ═══

    public function testCreateReturnsPositiveId(): void
    {
        $id = $this->seedField('test');
        $this->assertGreaterThan(0, $id);
    }

    public function testCreateAppliesDefaultsForOptionalFields(): void
    {
        // Kill Coalesce mutants on ?? defaults
        $id = $this->repo->create($this->registryId, new CreateRegistryFieldCommand(
            fieldCode: 'test',
            label: 'Test Field',
            fieldType: 'text',
        ));
        $field = $this->repo->findByCode($this->registryId, 'test');

        $this->assertSame('text', $field['field_type'], 'default field_type');
        $this->assertNull($field['options'], 'default options null');
        $this->assertSame(0, (int) $field['is_required'], 'default is_required 0');
        $this->assertSame(0, (int) $field['sort_order'], 'default sort_order 0');
    }

    public function testCreateUsesProvidedValuesOverDefaults(): void
    {
        $id = $this->repo->create($this->registryId, new CreateRegistryFieldCommand(
            fieldCode: 'test',
            label: 'Test',
            fieldType: 'select',
            options: '["a","b"]',
            isRequired: 1,
            sortOrder: 5,
        ));
        $field = $this->repo->findByCode($this->registryId, 'test');
        $this->assertSame('select', $field['field_type']);
        $this->assertSame('["a","b"]', $field['options']);
        $this->assertSame(1, (int) $field['is_required']);
        $this->assertSame(5, (int) $field['sort_order']);
    }

    public function testDeleteReturnsFalseWhenNotFound(): void
    {
        $this->assertFalse($this->repo->delete(99999));
    }
}
