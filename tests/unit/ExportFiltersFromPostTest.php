<?php
/**
 * ExportFiltersFromPost Test — Application SST DREETS BFC
 *
 * Non-régression des deux erreurs PHPStan de handlers/export_handler.php :
 * getExportData() et buildExportAuditContext() exigent `etats?: list<string>`,
 * or le code inline historique `array_map(strval(...), (array) $_POST['etats'])`
 * produit array<string> (clés préservées). Un POST forgé contenant des clés non
 * séquentielles (ex: etats[5]=nouveau&etats[12]=traite) casse le contrat de type.
 *
 * La construction des filtres est extraite dans ExportService::buildFiltersFromPost()
 * (règle AGENTS.md : logique hors handlers, testable unitairement) et doit
 * normaliser etats en list<string> via array_values().
 */

use App\Services\ExportService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

class ExportFiltersFromPostTest extends TestCase
{
    private function makeService(): ExportService
    {
        return getContainer()->get(ExportService::class);
    }

    // ─── etats : list<string> ────────────────────────────────────────────────

    public function testEtatsWithNonSequentialKeysIsNormalizedToList(): void
    {
        // POST forgé : etats[5]=nouveau&etats[12]=traite — clés non séquentielles
        $post = ['etats' => [5 => 'nouveau', 12 => 'traite']];

        $filters = $this->makeService()->buildFiltersFromPost($post);

        $this->assertArrayHasKey('etats', $filters);
        $this->assertTrue(
            array_is_list($filters['etats']),
            'etats doit être une list (clés séquentielles 0..n) — contrat de getExportData()/buildExportAuditContext()'
        );
        $this->assertSame(['nouveau', 'traite'], $filters['etats']);
    }

    public function testEtatsSequentialKeysPreservedInOrder(): void
    {
        // POST standard : etats[]=nouveau&etats[]=traite&etats[]=abandonne
        $post = ['etats' => ['nouveau', 'traite', 'abandonne']];

        $filters = $this->makeService()->buildFiltersFromPost($post);

        $this->assertSame(['nouveau', 'traite', 'abandonne'], $filters['etats']);
        $this->assertTrue(array_is_list($filters['etats']));
    }

    public function testEtatsSingleStringValueBecomesList(): void
    {
        // $_POST['etats'] peut être un string seul (checkbox unique)
        $post = ['etats' => 'nouveau'];

        $filters = $this->makeService()->buildFiltersFromPost($post);

        $this->assertSame(['nouveau'], $filters['etats']);
        $this->assertTrue(array_is_list($filters['etats']));
    }

    public function testEtatsAbsentMeansNoFilterKey(): void
    {
        $filters = $this->makeService()->buildFiltersFromPost([]);

        $this->assertArrayNotHasKey('etats', $filters);
    }

    public function testEtatsEmptyStringMeansNoFilterKey(): void
    {
        // Le handler historique utilise !empty($_POST['etats'])
        $filters = $this->makeService()->buildFiltersFromPost(['etats' => '']);

        $this->assertArrayNotHasKey('etats', $filters);
    }

    // ─── Autres filtres (comportement établi par le handler historique) ─────

    public function testTypeFilterCastToString(): void
    {
        $filters = $this->makeService()->buildFiltersFromPost(['type' => 'rami']);

        $this->assertSame(['type' => 'rami'], $filters);
    }

    public function testAllRegistriesFlagSkipsTypeFilter(): void
    {
        $filters = $this->makeService()->buildFiltersFromPost(['all_registries' => '1', 'type' => 'rami']);

        $this->assertArrayNotHasKey('type', $filters);
    }

    public function testSiteIdCastToInt(): void
    {
        $filters = $this->makeService()->buildFiltersFromPost(['site_id' => '42']);

        $this->assertSame(['site_id' => 42], $filters);
    }

    public function testAllSitesFlagSkipsSiteFilter(): void
    {
        $filters = $this->makeService()->buildFiltersFromPost(['all_sites' => '1', 'site_id' => '42']);

        $this->assertArrayNotHasKey('site_id', $filters);
    }

    public function testDeclarantIdCastToInt(): void
    {
        $filters = $this->makeService()->buildFiltersFromPost(['declarant_id' => '7']);

        $this->assertSame(['declarant_id' => 7], $filters);
    }

    public function testAllAgentsFlagSkipsDeclarantFilter(): void
    {
        $filters = $this->makeService()->buildFiltersFromPost(['all_agents' => '1', 'declarant_id' => '7']);

        $this->assertArrayNotHasKey('declarant_id', $filters);
    }

    public function testDateRangeFiltersCastToString(): void
    {
        $filters = $this->makeService()->buildFiltersFromPost(['date_from' => '2025-01-01', 'date_to' => '2025-12-31']);

        $this->assertSame(['date_from' => '2025-01-01', 'date_to' => '2025-12-31'], $filters);
    }

    public function testFullPostBuildsCompleteFilterShape(): void
    {
        $post = [
            'type' => 'rsst',
            'site_id' => '3',
            'declarant_id' => '9',
            'date_from' => '2025-06-01',
            'date_to' => '2025-06-30',
            'etats' => ['nouveau', 'en_cours'],
        ];

        $filters = $this->makeService()->buildFiltersFromPost($post);

        $this->assertSame([
            'type' => 'rsst',
            'site_id' => 3,
            'declarant_id' => 9,
            'date_from' => '2025-06-01',
            'date_to' => '2025-06-30',
            'etats' => ['nouveau', 'en_cours'],
        ], $filters);
    }
}
