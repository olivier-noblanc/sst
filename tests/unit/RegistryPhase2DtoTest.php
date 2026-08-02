<?php
/**
 * RegistryPhase2 DTO Tests — Application SST DREETS BFC
 *
 * TDD: tests written BEFORE implementation.
 * Tests CreateRegistryCommand, UpdateRegistryCommand, CreateRegistryFieldCommand DTOs.
 */

use App\DTO\CreateRegistryCommand;
use App\DTO\UpdateRegistryCommand;
use App\DTO\CreateRegistryFieldCommand;
use App\Enum\ReportType;
use App\Enum\VisibilityMode;
use PHPUnit\Framework\TestCase;

class RegistryPhase2DtoTest extends TestCase
{
    // ─── CreateRegistryCommand ─────────────────────────────────────────────

    public function testCreateRegistryCommandRequiredFields(): void
    {
        $cmd = new CreateRegistryCommand(
            code: 'test_reg',
            label: 'Registre Test',
            shortLabel: 'TEST',
        );
        $this->assertSame('test_reg', $cmd->code);
        $this->assertSame('Registre Test', $cmd->label);
        $this->assertSame('TEST', $cmd->shortLabel);
    }

    public function testCreateRegistryCommandDefaults(): void
    {
        $cmd = new CreateRegistryCommand(
            code: 'test_reg',
            label: 'Registre Test',
            shortLabel: 'TEST',
        );
        $this->assertNull($cmd->description);
        $this->assertSame('📋', $cmd->icon);
        $this->assertSame(ReportType::Rsst->value, $cmd->colorTheme);
        $this->assertSame(1, $cmd->isEnabled);
        $this->assertSame(0, $cmd->isSystem);
        $this->assertSame(0, $cmd->sortOrder);
        $this->assertSame(VisibilityMode::AgentChoice->value, $cmd->defaultVisibility);
        $this->assertSame(0, $cmd->notifyChsct);
        $this->assertNull($cmd->legalNote);
    }

    public function testCreateRegistryCommandAllFields(): void
    {
        $cmd = new CreateRegistryCommand(
            code: 'rami',
            label: 'Registre RAMI',
            shortLabel: 'RAMI',
            description: 'Description test',
            icon: '🚨',
            colorTheme: ReportType::Rami->value,
            isEnabled: 0,
            isSystem: 1,
            sortOrder: 5,
            defaultVisibility: VisibilityMode::Confidential->value,
            notifyChsct: 1,
            legalNote: 'Note légale',
        );
        $this->assertSame('rami', $cmd->code);
        $this->assertSame('Description test', $cmd->description);
        $this->assertSame('🚨', $cmd->icon);
        $this->assertSame('rami', $cmd->colorTheme);
        $this->assertSame(0, $cmd->isEnabled);
        $this->assertSame(1, $cmd->isSystem);
        $this->assertSame(5, $cmd->sortOrder);
        $this->assertSame('confidential', $cmd->defaultVisibility);
        $this->assertSame(1, $cmd->notifyChsct);
        $this->assertSame('Note légale', $cmd->legalNote);
    }

    public function testCreateRegistryCommandIsReadonly(): void
    {
        $cmd = new CreateRegistryCommand(code: 't', label: 'T', shortLabel: 'T');
        $this->expectException(\Error::class);
        /** @phpstan-ignore assign.propertyProtectedSet (intentional readonly violation test) */
        $cmd->code = 'changed';
    }

    // ─── UpdateRegistryCommand ─────────────────────────────────────────────

    public function testUpdateRegistryCommandAllNullable(): void
    {
        $cmd = new UpdateRegistryCommand();
        $this->assertNull($cmd->label);
        $this->assertNull($cmd->shortLabel);
        $this->assertNull($cmd->description);
        $this->assertNull($cmd->icon);
        $this->assertNull($cmd->colorTheme);
        $this->assertNull($cmd->isEnabled);
        $this->assertNull($cmd->sortOrder);
        $this->assertNull($cmd->defaultVisibility);
        $this->assertNull($cmd->notifyChsct);
        $this->assertNull($cmd->legalNote);
    }

    public function testUpdateRegistryCommandValues(): void
    {
        $cmd = new UpdateRegistryCommand(
            label: 'New Label',
            colorTheme: ReportType::Dgi->value,
            isEnabled: 0,
        );
        $this->assertSame('New Label', $cmd->label);
        $this->assertSame('dgi', $cmd->colorTheme);
        $this->assertSame(0, $cmd->isEnabled);
        $this->assertNull($cmd->shortLabel);
    }

    public function testUpdateRegistryCommandIsReadonly(): void
    {
        $cmd = new UpdateRegistryCommand(label: 'Test');
        $this->expectException(\Error::class);
        /** @phpstan-ignore assign.propertyProtectedSet (intentional readonly violation test) */
        $cmd->label = 'Changed';
    }

    public function testUpdateRegistryCommandNoCodeOrIsSystem(): void
    {
        $cmd = new UpdateRegistryCommand();
        $this->assertObjectNotHasProperty('code', $cmd);
        $this->assertObjectNotHasProperty('isSystem', $cmd);
    }

    // ─── CreateRegistryFieldCommand ────────────────────────────────────────

    public function testCreateRegistryFieldCommandRequiredFields(): void
    {
        $cmd = new CreateRegistryFieldCommand(
            fieldCode: 'nature_auteur',
            label: 'Nature de l\'auteur',
        );
        $this->assertSame('nature_auteur', $cmd->fieldCode);
        $this->assertSame('Nature de l\'auteur', $cmd->label);
    }

    public function testCreateRegistryFieldCommandDefaults(): void
    {
        $cmd = new CreateRegistryFieldCommand(
            fieldCode: 'my_field',
            label: 'My Field',
        );
        $this->assertSame('text', $cmd->fieldType);
        $this->assertNull($cmd->options);
        $this->assertSame(0, $cmd->isRequired);
        $this->assertSame(0, $cmd->sortOrder);
    }

    public function testCreateRegistryFieldCommandAllFields(): void
    {
        $cmd = new CreateRegistryFieldCommand(
            fieldCode: 'nature_auteur',
            label: 'Nature de l\'auteur',
            fieldType: 'select',
            options: '{"usager":"Usager","collegue":"Collègue"}',
            isRequired: 1,
            sortOrder: 3,
        );
        $this->assertSame('select', $cmd->fieldType);
        $this->assertSame('{"usager":"Usager","collegue":"Collègue"}', $cmd->options);
        $this->assertSame(1, $cmd->isRequired);
        $this->assertSame(3, $cmd->sortOrder);
    }

    public function testCreateRegistryFieldCommandIsReadonly(): void
    {
        $cmd = new CreateRegistryFieldCommand(fieldCode: 'f', label: 'F');
        $this->expectException(\Error::class);
        /** @phpstan-ignore assign.propertyProtectedSet (intentional readonly violation test) */
        $cmd->fieldCode = 'changed';
    }

    // ─── Repository create() accepts CreateRegistryCommand ─────────────────

    public function testRegistryRepositoryCreateAcceptsDto(): void
    {
        $pdo = getDB();
        $pdo->exec('DELETE FROM registries');
        $repo = new \App\Repository\RegistryRepository($pdo);
        $cmd = new CreateRegistryCommand(
            code: 'dto_test',
            label: 'DTO Test',
            shortLabel: 'DT',
            colorTheme: ReportType::Rsst->value,
        );
        $id = $repo->create($cmd);
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
        $reg = $repo->findById($id);
        $this->assertSame('dto_test', $reg['code']);
        $this->assertSame('DTO Test', $reg['label']);
        $this->assertSame('DT', $reg['short_label']);
        reseedDefaultRegistries($pdo);
    }

    // ─── Repository update() accepts UpdateRegistryCommand ─────────────────

    public function testRegistryRepositoryUpdateAcceptsDto(): void
    {
        $pdo = getDB();
        $pdo->exec('DELETE FROM registries');
        $repo = new \App\Repository\RegistryRepository($pdo);
        $createCmd = new CreateRegistryCommand(
            code: 'upd_test',
            label: 'Old Label',
            shortLabel: 'OLD',
        );
        $id = $repo->create($createCmd);
        $updateCmd = new UpdateRegistryCommand(
            label: 'New Label',
            isEnabled: 0,
        );
        $repo->update($id, $updateCmd);
        $reg = $repo->findById($id);
        $this->assertSame('New Label', $reg['label']);
        $this->assertSame(0, (int) $reg['is_enabled']);
        $this->assertSame('OLD', $reg['short_label']);
        reseedDefaultRegistries($pdo);
    }

    // ─── Repository create() with empty UpdateRegistryCommand returns false ──

    public function testRegistryRepositoryUpdateEmptyDtoReturnsFalse(): void
    {
        $pdo = getDB();
        $pdo->exec('DELETE FROM registries');
        $repo = new \App\Repository\RegistryRepository($pdo);
        $createCmd = new CreateRegistryCommand(
            code: 'upd_empty',
            label: 'Test',
            shortLabel: 'T',
        );
        $id = $repo->create($createCmd);
        $updateCmd = new UpdateRegistryCommand();
        $result = $repo->update($id, $updateCmd);
        $this->assertFalse($result);
        reseedDefaultRegistries($pdo);
    }

    // ─── RegistryFieldRepository create() accepts CreateRegistryFieldCommand ─

    public function testRegistryFieldRepositoryCreateAcceptsDto(): void
    {
        $pdo = getDB();
        $pdo->exec('DELETE FROM registry_fields');
        $pdo->exec('DELETE FROM registries');
        $regRepo = new \App\Repository\RegistryRepository($pdo);
        $regId = $regRepo->create(new CreateRegistryCommand(
            code: 'field_test',
            label: 'Field Test',
            shortLabel: 'FT',
        ));
        $fieldRepo = new \App\Repository\RegistryFieldRepository($pdo);
        $cmd = new CreateRegistryFieldCommand(
            fieldCode: 'nature_auteur',
            label: 'Nature de l\'auteur',
            fieldType: 'select',
            options: '{"usager":"Usager"}',
            isRequired: 1,
            sortOrder: 2,
        );
        $fieldId = $fieldRepo->create($regId, $cmd);
        $this->assertIsInt($fieldId);
        $this->assertGreaterThan(0, $fieldId);
        reseedDefaultRegistries($pdo);
    }
}
