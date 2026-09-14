<?php
/**
 * CustomFieldsService Tests — Application SST DREETS BFC
 *
 * TDD: tests written BEFORE implementation.
 *
 * Contrat du service métier des champs dynamiques de registre :
 * - extraction des valeurs soumises (codes inconnus et codes à chemin
 *   dédié COMMAND_MAPPED_CODES exclus — pas de double source de vérité),
 * - validation serveur (obligatoire, options de select, longueurs),
 * - filtrage défensif avant persistance,
 * - formatage pour affichage/export.
 */

use App\Repository\RegistryFieldRepository;
use App\Repository\RegistryRepository;
use App\DTO\CreateRegistryFieldCommand;
use App\Services\CustomFieldsService;
use PHPUnit\Framework\TestCase;

class CustomFieldsServiceTest extends TestCase
{
    private PDO $pdo;
    private CustomFieldsService $service;
    private int $registryId;
    /** @var list<array<string, mixed>> */
    private array $defs;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        $this->pdo->exec('DELETE FROM registry_field_values');
        $this->pdo->exec("DELETE FROM registry_fields WHERE registry_id IN (SELECT id FROM registries WHERE code LIKE 'cfs_%')");
        $this->pdo->exec("DELETE FROM registries WHERE code LIKE 'cfs_%'");

        $this->pdo->exec("INSERT INTO registries (code, label, short_label, color_theme, is_enabled, default_visibility)
            VALUES ('cfs_reg', 'Registre service test', 'CFS', 'rsst', 1, 'agent_choice')");
        $this->registryId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO registry_fields (registry_id, field_code, label, field_type, options, is_required, sort_order) VALUES
            ({$this->registryId}, 'cfs_texte', 'Contexte', 'text', NULL, 1, 1),
            ({$this->registryId}, 'cfs_select', 'Nature', 'select', '{\"a\":\"Option A\",\"b\":\"Option B\"}', 0, 2),
            ({$this->registryId}, 'cfs_case', 'Accord', 'checkbox', NULL, 0, 3),
            ({$this->registryId}, 'cfs_zone', 'Détails', 'textarea', NULL, 0, 4),
            ({$this->registryId}, 'nature_auteur', 'Nature auteur (legacy)', 'select', '{\"usager\":\"Usager\"}', 0, 5)
        ");

        $this->service = new CustomFieldsService(
            new RegistryFieldRepository($this->pdo),
            new RegistryRepository($this->pdo),
        );
        $this->defs = $this->service->getDefinitions('cfs_reg');
    }

    protected function tearDown(): void
    {
        $this->pdo->exec('DELETE FROM registry_field_values');
        $this->pdo->exec("DELETE FROM registry_fields WHERE registry_id IN (SELECT id FROM registries WHERE code LIKE 'cfs_%')");
        $this->pdo->exec("DELETE FROM registries WHERE code LIKE 'cfs_%'");
    }

    // ─── DEFINITIONS ─────────────────────────────────────────────────────────

    public function testGetDefinitionsReturnsFieldsOrderedBySortOrder(): void
    {
        $this->assertCount(5, $this->defs);
        $this->assertSame('cfs_texte', $this->defs[0]['field_code']);
        $this->assertSame('nature_auteur', $this->defs[4]['field_code']);
    }

    public function testGetDefinitionsReturnsEmptyForUnknownRegistry(): void
    {
        $this->assertSame([], $this->service->getDefinitions('registre_inconnu_cfs'));
    }

    public function testReservedFieldCodeIsRejectedWhenCreatingNewField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new RegistryFieldRepository($this->pdo))->create(
            $this->registryId,
            new CreateRegistryFieldCommand('csrf_token', 'Jeton'),
        );
    }

    // ─── EXTRACT ─────────────────────────────────────────────────────────────

    public function testExtractSubmissionReadsOnlyDefinedDynamicCodes(): void
    {
        $post = [
            'cfs_texte' => '  Risque électrique  ',
            'cfs_select' => 'a',
            'cfs_case' => '1',
            'cfs_zone' => 'Détail ligne',
            'champ_pirate' => '<script>',
            'nature_auteur' => 'usager', // code legacy → chemin dédié, pas d'extraction
        ];
        $values = $this->service->extractSubmission($post, $this->defs);

        $this->assertSame([
            'cfs_texte' => 'Risque électrique',
            'cfs_select' => 'a',
            'cfs_case' => '1',
            'cfs_zone' => 'Détail ligne',
        ], $values);
        $this->assertArrayNotHasKey('nature_auteur', $values, 'Code legacy = chemin dédié (colonne reports), jamais extrait');
        $this->assertArrayNotHasKey('champ_pirate', $values, 'Code inconnu jamais extrait');
    }

    public function testExtractSubmissionNormalizesEmptyToNull(): void
    {
        $values = $this->service->extractSubmission(['cfs_texte' => '   ', 'cfs_zone' => ''], $this->defs);
        $this->assertNull($values['cfs_texte']);
        $this->assertNull($values['cfs_zone']);
        $this->assertNull($values['cfs_case'], 'Checkbox absente = décochée = null explicite');
    }

    public function testExtractSubmissionIncludesAllDynamicDefsEvenAbsent(): void
    {
        $values = $this->service->extractSubmission([], $this->defs);
        $this->assertArrayHasKey('cfs_texte', $values);
        $this->assertArrayHasKey('cfs_select', $values);
        $this->assertArrayHasKey('cfs_case', $values);
        $this->assertArrayHasKey('cfs_zone', $values);
    }

    // ─── VALIDATE (depuis POST brut — tous les defs) ─────────────────────────

    public function testValidateSubmissionDetectsMissingRequiredText(): void
    {
        $errors = $this->service->validateSubmission([], $this->defs);
        $this->assertArrayHasKey('cfs_texte', $errors);
        $this->assertStringContainsString('obligatoire', $errors['cfs_texte']);
    }

    public function testValidateSubmissionDetectsBlankRequiredText(): void
    {
        $errors = $this->service->validateSubmission(['cfs_texte' => '   '], $this->defs);
        $this->assertArrayHasKey('cfs_texte', $errors);
    }

    public function testValidateSubmissionAcceptsFilledRequiredText(): void
    {
        $errors = $this->service->validateSubmission(['cfs_texte' => 'Risque'], $this->defs);
        $this->assertArrayNotHasKey('cfs_texte', $errors);
    }

    public function testValidateSubmissionDetectsMissingRequiredCheckbox(): void
    {
        $pdo = $this->pdo;
        $pdo->exec("UPDATE registry_fields SET is_required = 1 WHERE registry_id = {$this->registryId} AND field_code = 'cfs_case'");
        $defs = $this->service->getDefinitions('cfs_reg');

        $errors = $this->service->validateSubmission([], $defs);
        $this->assertArrayHasKey('cfs_case', $errors);
    }

    public function testValidateSubmissionAcceptsCheckedRequiredCheckbox(): void
    {
        $this->pdo->exec("UPDATE registry_fields SET is_required = 1 WHERE registry_id = {$this->registryId} AND field_code = 'cfs_case'");
        $defs = $this->service->getDefinitions('cfs_reg');

        $errors = $this->service->validateSubmission(['cfs_case' => '1'], $defs);
        $this->assertArrayNotHasKey('cfs_case', $errors);
    }

    public function testValidateSubmissionRejectsInvalidSelectOption(): void
    {
        $errors = $this->service->validateSubmission(['cfs_texte' => 'ok', 'cfs_select' => 'option_fantome'], $this->defs);
        $this->assertArrayHasKey('cfs_select', $errors);
        $this->assertStringContainsString('invalide', $errors['cfs_select']);
    }

    public function testValidateSubmissionAcceptsEmptyOptionalSelect(): void
    {
        $errors = $this->service->validateSubmission(['cfs_texte' => 'ok', 'cfs_select' => ''], $this->defs);
        $this->assertArrayNotHasKey('cfs_select', $errors);
    }

    public function testValidateSubmissionAcceptsValidSelectOption(): void
    {
        $errors = $this->service->validateSubmission(['cfs_texte' => 'ok', 'cfs_select' => 'b'], $this->defs);
        $this->assertArrayNotHasKey('cfs_select', $errors);
    }

    public function testValidateSubmissionEnforcesTextMaxLength500(): void
    {
        $errors = $this->service->validateSubmission(['cfs_texte' => str_repeat('a', 501)], $this->defs);
        $this->assertArrayHasKey('cfs_texte', $errors);
        $this->assertStringContainsString('500', $errors['cfs_texte']);
    }

    public function testValidateSubmissionEnforcesTextareaMaxLength5000(): void
    {
        $errors = $this->service->validateSubmission(['cfs_texte' => 'ok', 'cfs_zone' => str_repeat('a', 5001)], $this->defs);
        $this->assertArrayHasKey('cfs_zone', $errors);
        $this->assertStringContainsString('5000', $errors['cfs_zone']);
    }

    public function testValidateSubmissionValidatesLegacyCodeFromPost(): void
    {
        // Le code legacy (nature_auteur) est validé depuis le POST même s'il
        // n'est jamais persisté dans registry_field_values (chemin dédié).
        $errors = $this->service->validateSubmission(['cfs_texte' => 'ok', 'nature_auteur' => 'pirate'], $this->defs);
        $this->assertArrayHasKey('nature_auteur', $errors);
    }

    public function testValidateSubmissionAllValidReturnsEmpty(): void
    {
        $post = [
            'cfs_texte' => 'Risque électrique au 2e étage',
            'cfs_select' => 'a',
            'cfs_case' => '1',
            'cfs_zone' => 'Détails complets',
            'nature_auteur' => 'usager',
        ];
        $this->assertSame([], $this->service->validateSubmission($post, $this->defs));
    }

    // ─── VALIDATE VALUES (depuis la map du DTO — codes dynamiques seuls) ─────

    public function testValidateValuesDetectsRequiredNull(): void
    {
        $values = $this->service->extractSubmission([], $this->defs);
        $errors = $this->service->validateValues($values, $this->defs);
        $this->assertArrayHasKey('cfs_texte', $errors);
    }

    public function testValidateValuesIgnoresUnknownCodes(): void
    {
        $errors = $this->service->validateValues(['champ_pirate' => 'x', 'cfs_texte' => 'ok'], $this->defs);
        $this->assertArrayNotHasKey('champ_pirate', $errors);
        $this->assertArrayNotHasKey('cfs_texte', $errors);
    }

    public function testValidateValuesRejectsInvalidSelectValue(): void
    {
        $errors = $this->service->validateValues(['cfs_select' => 'pirate', 'cfs_texte' => 'ok'], $this->defs);
        $this->assertArrayHasKey('cfs_select', $errors);
    }

    // ─── FILTER PERSISTABLE (défense en profondeur avant écriture) ───────────

    public function testFilterPersistableDropsCommandMappedAndUnknownCodes(): void
    {
        $values = [
            'cfs_texte' => 'ok',
            'nature_auteur' => 'usager',   // chemin dédié (colonne reports)
            'pour_compte_nom' => 'X',      // chemin dédié (colonne reports)
            'champ_pirate' => 'x',         // code inconnu
            'cfs_zone' => null,
        ];
        $filtered = $this->service->filterPersistable($values, $this->defs);
        $this->assertArrayHasKey('cfs_texte', $filtered);
        $this->assertArrayHasKey('cfs_zone', $filtered);
        $this->assertArrayNotHasKey('nature_auteur', $filtered);
        $this->assertArrayNotHasKey('pour_compte_nom', $filtered);
        $this->assertArrayNotHasKey('champ_pirate', $filtered);
    }

    // ─── FORMAT (affichage / export) ─────────────────────────────────────────

    public function testFormatFieldValueCheckbox(): void
    {
        $def = $this->defs[2];
        $this->assertSame('Oui', $this->service->formatFieldValue($def, '1'));
        $this->assertSame('', $this->service->formatFieldValue($def, null));
        $this->assertSame('', $this->service->formatFieldValue($def, ''));
    }

    public function testFormatFieldValueSelectTranslatesOption(): void
    {
        $def = $this->defs[1];
        $this->assertSame('Option A', $this->service->formatFieldValue($def, 'a'));
        $this->assertSame('valeur_inconnue', $this->service->formatFieldValue($def, 'valeur_inconnue'));
        $this->assertSame('', $this->service->formatFieldValue($def, null));
    }

    public function testFormatFieldValueTextPassthrough(): void
    {
        $def = $this->defs[0];
        $this->assertSame('Risque électrique', $this->service->formatFieldValue($def, 'Risque électrique'));
        $this->assertSame('', $this->service->formatFieldValue($def, null));
    }
}
