<?php
/**
 * Export Custom Field Values Test — Application SST DREETS BFC
 *
 * TDD: tests written BEFORE implementation.
 *
 * L'export CSV doit exposer les valeurs des champs dynamiques des registres
 * (stockées dans registry_field_values), traduire les options de select,
 * formater les checkbox, et continuer d'exclure les codes legacy RAMI
 * (chemin dédié colonnes physiques — pas de doublon).
 */

use App\Services\ConfigService;
use App\Services\ExportService;
use PHPUnit\Framework\TestCase;

class ExportCustomFieldValuesTest extends TestCase
{
    private PDO $pdo;
    private ExportService $exportService;
    private int $registryId;
    private int $secondRegistryId;
    private string $reportUuid = 'cfe00000-1111-2222-3333-444444444444';

    protected function setUp(): void
    {
        $this->pdo = getDB();
        $this->exportService = new ExportService(new ConfigService());

        $this->pdo->exec('DELETE FROM registry_field_values');
        $this->pdo->exec("DELETE FROM registry_fields WHERE registry_id IN (SELECT id FROM registries WHERE code LIKE 'cfe_%' OR code = 'rami')");
        $this->pdo->exec("DELETE FROM registries WHERE code LIKE 'cfe_%' OR code = 'rami'");
        $this->pdo->exec("DELETE FROM reports WHERE uuid LIKE 'cfe%'");
        $this->pdo->exec("DELETE FROM users WHERE username LIKE 'test.cfe%'");

        // Registre d'export avec 3 champs dynamiques (aucune colonne physique)
        $this->pdo->exec("INSERT INTO registries (code, label, short_label, color_theme, is_enabled, default_visibility)
            VALUES ('cfe_reg', 'Registre export test', 'CFE', 'vert', 1, 'agent_choice')");
        $this->registryId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO registry_fields (registry_id, field_code, label, field_type, options, is_required, sort_order) VALUES
            ({$this->registryId}, 'cfe_sel', 'Nature (export)', 'select', '{\"opt1\":\"Premier\",\"opt2\":\"Deuxième\"}', 0, 1),
            ({$this->registryId}, 'cfe_chk', 'Accord (export)', 'checkbox', NULL, 0, 2),
            ({$this->registryId}, 'cfe_txt', 'Contexte (export)', 'text', NULL, 0, 3)
        ");

        $this->pdo->exec("INSERT INTO registries (code, label, short_label, color_theme, is_enabled, default_visibility)
            VALUES ('cfe_reg_bis', 'Registre export test bis', 'CFE2', 'bleu', 1, 'agent_choice')");
        $this->secondRegistryId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO registry_fields (registry_id, field_code, label, field_type, options, is_required, sort_order)
            VALUES ({$this->secondRegistryId}, 'cfe_txt', 'Contexte bis', 'text', NULL, 0, 1)");

        // RAMI legacy — même seed que la production
        $this->pdo->exec("INSERT INTO registries (code, label, short_label, color_theme, is_enabled, is_system, default_visibility)
            VALUES ('rami', 'Agressions, Menaces et Incivilités', 'RAMI', 'rami', 1, 0, 'agent_choice')");
        $ramiId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO registry_fields (registry_id, field_code, label, field_type, options, is_required, sort_order) VALUES
            ({$ramiId}, 'pour_compte', 'Pour le compte', 'checkbox', NULL, 0, 1),
            ({$ramiId}, 'nature_auteur', 'Nature de l''auteur', 'select', '{\"usager\":\"Usager\"}', 0, 2),
            ({$ramiId}, 'type_acte', 'Type d''acte', 'select', '{\"verbal\":\"Verbal\"}', 0, 3)
        ");

        $this->pdo->exec("INSERT OR IGNORE INTO sites (code, nom, is_active) VALUES ('UR21_CFE', 'UR CFE', 1)");
        $siteId = (int) $this->pdo->query("SELECT id FROM sites WHERE code = 'UR21_CFE'")->fetchColumn();
        $this->pdo->exec("INSERT INTO users (username, nom, prenom, role, site_id, is_active, email)
            VALUES ('test.cfe.user', 'Test', 'Cfe', 'agent', {$siteId}, 1, 'fixture@dreets-bfc.gouv.fr')");
        $userId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO reports (uuid, reference, type, objet, description, date_evenement, declarant_id, declarant_nom, declarant_prenom, site_id, is_confidential, etat)
            VALUES ('{$this->reportUuid}', 'CFE-26-001', 'cfe_reg', 'Objet', 'Description', '2026-01-15', {$userId}, 'Test', 'Cfe', {$siteId}, 0, 'nouveau')");
    }

    protected function tearDown(): void
    {
        $this->pdo->exec('DELETE FROM registry_field_values');
        $this->pdo->exec("DELETE FROM registry_fields WHERE registry_id IN (SELECT id FROM registries WHERE code LIKE 'cfe_%' OR code = 'rami')");
        $this->pdo->exec("DELETE FROM registries WHERE code LIKE 'cfe_%' OR code = 'rami'");
        $this->pdo->exec("DELETE FROM reports WHERE uuid LIKE 'cfe%'");
        $this->pdo->exec("DELETE FROM users WHERE username LIKE 'test.cfe%'");
    }

    private function insertValue(string $fieldCode, ?string $value): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO registry_field_values (report_uuid, registry_id, field_code, value) VALUES (?, ?, ?, ?)');
        $stmt->execute([$this->reportUuid, $this->registryId, $fieldCode, $value]);
    }

    private function getTestRow(array $filters = []): array
    {
        $rows = \App\Repository\StatsRepository::instance()->getExportData($filters, 'cfe_reg');
        foreach ($rows as $row) {
            if (($row['uuid'] ?? '') === $this->reportUuid) {
                return $row;
            }
        }
        $this->fail('Le signalement de test est absent des données export.');
        return [];
    }

    // ─── LECTURE SQL ─────────────────────────────────────────────────────────

    public function testGetExportDataIncludesDynamicValueColumns(): void
    {
        $this->insertValue('cfe_sel', 'opt1');
        $this->insertValue('cfe_chk', '1');
        $this->insertValue('cfe_txt', 'Contexte libre');

        $row = $this->getTestRow();

        $this->assertSame('opt1', $row['cfe_sel']);
        $this->assertSame('1', $row['cfe_chk']);
        $this->assertSame('Contexte libre', $row['cfe_txt']);
    }

    public function testGetExportDataReturnsNullForReportWithoutValues(): void
    {
        // Anciens signalements sans valeurs stockées : clés présentes, valeur null
        $row = $this->getTestRow();
        $this->assertArrayHasKey('cfe_sel', $row);
        $this->assertNull($row['cfe_sel']);
        $this->assertNull($row['cfe_chk']);
        $this->assertNull($row['cfe_txt']);
    }

    public function testGetExportDataScopesSameFieldCodeToSelectedRegistry(): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO registry_field_values (report_uuid, registry_id, field_code, value) VALUES (?, ?, ?, ?)');
        $stmt->execute([$this->reportUuid, $this->registryId, 'cfe_txt', 'Valeur registre A']);
        $stmt->execute([$this->reportUuid, $this->secondRegistryId, 'cfe_txt', 'Valeur registre B']);

        $rowsA = \App\Repository\StatsRepository::instance()->getExportData([], 'cfe_reg');
        $rowsB = \App\Repository\StatsRepository::instance()->getExportData([], 'cfe_reg_bis');

        $rowA = array_values(array_filter($rowsA, fn (array $row): bool => ($row['uuid'] ?? '') === $this->reportUuid))[0] ?? null;
        $rowB = array_values(array_filter($rowsB, fn (array $row): bool => ($row['uuid'] ?? '') === $this->reportUuid))[0] ?? null;

        $this->assertIsArray($rowA);
        $this->assertIsArray($rowB);
        $this->assertSame('Valeur registre A', $rowA['cfe_txt']);
        $this->assertSame('Valeur registre B', $rowB['cfe_txt']);
    }

    public function testExistingLegacyUniqueConstraintIsMigratedBeforeMultiRegistryExport(): void
    {
        $this->pdo->exec('DELETE FROM registry_field_values');
        $this->pdo->exec('DROP TABLE registry_field_values');
        $this->pdo->exec("CREATE TABLE registry_field_values (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            report_uuid TEXT NOT NULL,
            registry_id INTEGER NOT NULL,
            field_code  TEXT NOT NULL,
            value       TEXT,
            created_at  TEXT NOT NULL DEFAULT (datetime('now')),
            updated_at  TEXT NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (report_uuid) REFERENCES reports(uuid) ON DELETE CASCADE,
            FOREIGN KEY (registry_id, field_code) REFERENCES registry_fields(registry_id, field_code) ON DELETE CASCADE,
            UNIQUE(report_uuid, field_code)
        )");
        $this->pdo->exec('CREATE INDEX idx_registry_field_values_registry ON registry_field_values(registry_id, field_code)');
        $this->pdo->exec('CREATE UNIQUE INDEX idx_registry_field_values_current ON registry_field_values(report_uuid, registry_id, field_code)');
        $this->pdo->exec('CREATE INDEX idx_registry_field_values_custom ON registry_field_values(value COLLATE NOCASE DESC, report_uuid) WHERE value IS NOT NULL');
        $this->pdo->exec('CREATE UNIQUE INDEX idx_registry_field_values_legacy ON registry_field_values(report_uuid, field_code)');

        $this->pdo->exec("INSERT INTO registry_field_values (report_uuid, registry_id, field_code, value)
            VALUES ('{$this->reportUuid}', {$this->registryId}, 'cfe_txt', 'Valeur registre A')");

        migrateTables($this->pdo);

        $indexColumns = [];
        $indexes = $this->pdo->query('PRAGMA index_list(registry_field_values)')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($indexes as $index) {
            $name = (string) $index['name'];
            $columns = $this->pdo->query('PRAGMA index_info(' . $this->pdo->quote($name) . ')')->fetchAll(PDO::FETCH_ASSOC);
            $indexColumns[$name] = array_map(static fn (array $column): string => (string) $column['name'], $columns);
        }
        $this->assertSame(['registry_id', 'field_code'], $indexColumns['idx_registry_field_values_registry']);
        $this->assertArrayHasKey('idx_registry_field_values_custom', $indexColumns);
        $this->assertArrayNotHasKey('idx_registry_field_values_legacy', $indexColumns);
        $this->assertContains(['report_uuid', 'registry_id', 'field_code'], array_values($indexColumns));
        $customIndexSql = $this->pdo->query("SELECT sql FROM sqlite_master WHERE type = 'index' AND name = 'idx_registry_field_values_custom'")->fetchColumn();
        $this->assertSame(
            'CREATE INDEX idx_registry_field_values_custom ON registry_field_values(value COLLATE NOCASE DESC, report_uuid) WHERE value IS NOT NULL',
            $customIndexSql
        );
        $foreignKeys = $this->pdo->query('PRAGMA foreign_key_list(registry_field_values)')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(3, $foreignKeys);
        $this->assertSame(['registry_id', 'field_code'], array_values(array_filter(
            array_map(static fn (array $foreignKey): string => (string) $foreignKey['from'], $foreignKeys),
            static fn (string $column): bool => $column !== 'report_uuid'
        )));

        $stmt = $this->pdo->prepare('INSERT INTO registry_field_values (report_uuid, registry_id, field_code, value) VALUES (?, ?, ?, ?)');
        $stmt->execute([$this->reportUuid, $this->secondRegistryId, 'cfe_txt', 'Valeur registre B']);

        $rowsA = \App\Repository\StatsRepository::instance()->getExportData([], 'cfe_reg');
        $rowsB = \App\Repository\StatsRepository::instance()->getExportData([], 'cfe_reg_bis');
        $rowA = array_values(array_filter($rowsA, fn (array $row): bool => ($row['uuid'] ?? '') === $this->reportUuid))[0] ?? null;
        $rowB = array_values(array_filter($rowsB, fn (array $row): bool => ($row['uuid'] ?? '') === $this->reportUuid))[0] ?? null;

        $this->assertIsArray($rowA);
        $this->assertIsArray($rowB);
        $this->assertSame('Valeur registre A', $rowA['cfe_txt']);
        $this->assertSame('Valeur registre B', $rowB['cfe_txt']);

        migrateTables($this->pdo);
        $this->assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM registry_field_values')->fetchColumn());
        $secondPassIndexes = $this->pdo->query('PRAGMA index_list(registry_field_values)')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(count($secondPassIndexes), $secondPassIndexes);
        $this->assertSame(
            'CREATE INDEX idx_registry_field_values_custom ON registry_field_values(value COLLATE NOCASE DESC, report_uuid) WHERE value IS NOT NULL',
            $this->pdo->query("SELECT sql FROM sqlite_master WHERE type = 'index' AND name = 'idx_registry_field_values_custom'")->fetchColumn()
        );
    }

    // ─── COLONNES ANNONCÉES (en-têtes) ───────────────────────────────────────

    public function testGetDynamicExportFieldsIncludesTypeInOrder(): void
    {
        $fields = $this->exportService->getDynamicExportFields('cfe_reg');

        $this->assertSame([
            ['code' => 'cfe_sel', 'label' => 'Nature (export)', 'type' => 'select'],
            ['code' => 'cfe_chk', 'label' => 'Accord (export)', 'type' => 'checkbox'],
            ['code' => 'cfe_txt', 'label' => 'Contexte (export)', 'type' => 'text'],
        ], $fields);
    }

    public function testGetDynamicExportFieldsExcludesLegacyCommandMappedCodes(): void
    {
        $fields = $this->exportService->getDynamicExportFields('rami');
        $codes = array_column($fields, 'code');

        $this->assertNotContains('pour_compte', $codes, 'Code legacy (chemin dédié) jamais exporté en colonne dynamique');
        $this->assertNotContains('nature_auteur', $codes);
        $this->assertNotContains('type_acte', $codes);
        $this->assertSame([], $codes);
    }

    // ─── LIGNE CSV ───────────────────────────────────────────────────────────

    public function testBuildCsvRowTranslatesSelectAndFormatsCheckbox(): void
    {
        $this->insertValue('cfe_sel', 'opt1');
        $this->insertValue('cfe_chk', '1');
        $this->insertValue('cfe_txt', 'Contexte libre');

        $row = $this->getTestRow();
        $csvRow = $this->exportService->buildCsvRow($row, [], false, 'cfe_reg');
        $headers = $this->exportService->buildHeaders(false, 'cfe_reg');

        // Alignement en-têtes / valeurs garanti
        $this->assertCount(count($headers), $csvRow);

        $dynamicValues = array_slice($csvRow, -3);
        $this->assertSame('Premier', $dynamicValues[0], 'Select traduit via ses options');
        $this->assertSame('Oui', $dynamicValues[1], 'Checkbox cochée exportée en Oui');
        $this->assertSame('Contexte libre', $dynamicValues[2]);
    }

    public function testBuildCsvRowEmitsEmptyCellsForUncheckedCheckboxAndNullValues(): void
    {
        $this->insertValue('cfe_sel', null);
        $this->insertValue('cfe_chk', null);
        $this->insertValue('cfe_txt', 'Seul texte rempli');

        $row = $this->getTestRow();
        $csvRow = $this->exportService->buildCsvRow($row, [], false, 'cfe_reg');

        $dynamicValues = array_slice($csvRow, -3);
        $this->assertSame('', $dynamicValues[0]);
        $this->assertSame('', $dynamicValues[1], 'Checkbox décochée = cellule vide, pas de fausse valeur');
        $this->assertSame('Seul texte rempli', $dynamicValues[2]);
    }
}
