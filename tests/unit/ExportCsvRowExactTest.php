<?php
/**
 * Export CSV Row Exact Test — Application SST DREETS BFC
 *
 * Fiabilisation (Infection) — buildCsvRow() est le point de convergence de
 * l'export CSV : chaque cellule doit refléter EXACTEMENT la ligne source.
 * Une ligne pleine (toutes les valeurs présentes) + une ligne vide (tous les
 * défauts) verrouillent :
 *   - les swaps Coalesce d'Infection (`'' ?? $row[...]` → toujours '') : une
 *     ligne peuplée doit faire transiter CHAQUE valeur vers sa cellule ;
 *   - les labels conditionnels 'Oui'/'Non' (Confidentiel) et
 *     'Acceptée'/'Refusée' (Transmission FS/CSA) dans les deux sens ;
 *   - le strtoupper du registre et la concat prénom+nom du répondant
 *     (y compris « prénom seul » / « nom seul » sans espace parasite).
 */

use PHPUnit\Framework\TestCase;
use App\Services\ConfigService;
use App\Services\ExportService;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/helpers.php';

class ExportCsvRowExactTest extends TestCase
{
    private ExportService $service;

    protected function setUp(): void
    {
        // Même résolution que le container DI (bootstrap_services.php) :
        // ExportService(ConfigService)
        $config = getContainer()->get(ConfigService::class);
        $this->service = new ExportService($config);
    }

    /**
     * Ligne source « pleine » : chaque clé consommée par buildCsvRow() est
     * peuplée — un swap Coalesce (`'' ?? $row[...]`) sur n'importe quelle
     * colonne produirait '' et serait détecté par l'assertion exacte.
     *
     * @return array<string, mixed>
     */
    private function fullRow(): array
    {
        return [
            'uuid' => 'uuid-full-row-1',
            'reference' => 'RSST-2026-001',
            'type' => 'rsst',
            'date_evenement' => '2026-01-15',
            'heure_evenement' => '14:30',
            'lieu' => 'Hall B',
            'pole' => 'Pôle Nord',
            'service_affectation' => 'Service X',
            'telephone_mobile' => '0601020304',
            'site_text' => 'UR Test',
            'objet' => 'Objet test',
            'description' => 'Description complète',
            'declarant_nom' => 'Dupont',
            'declarant_prenom' => 'Jean',
            'site_code' => 'UR21',
            'site_nom' => 'UR Nom',
            'etat' => 'nouveau',
            'is_confidential' => 1,
            'consent_syndicat' => 1,
            'created_at' => '2026-01-15 10:00:00',
            'pour_compte_nom' => 'Martin',
            'pour_compte_prenom' => 'Pierre',
            'nature_auteur' => 'usager',
            'type_acte' => 'verbal',
            'reponse' => 'Réponse initiale',
            'repondant_prenom' => 'Marie',
            'repondant_nom' => 'Curie',
            'date_reponse' => '2026-01-16 09:00:00',
        ];
    }

    public function testBuildCsvRowFullRowCarriesEveryValueExactly(): void
    {
        $row = $this->service->buildCsvRow($this->fullRow(), [], false, null);

        $this->assertCount(27, $row, '13 colonnes de base + 2 site + 12 finales (registryCode=null → 0 colonne dynamique)');

        // Même sémantique que ExportService : label d'état si ETAT_LABELS est
        // chargé, valeur brute sinon ; options de registre si définies en DB.
        $etat = defined('ETAT_LABELS') && isset(ETAT_LABELS['nouveau']) ? ETAT_LABELS['nouveau'] : 'nouveau';
        $natureOptions = function_exists('getRegistryFieldOptions') ? getRegistryFieldOptions('rami', 'nature_auteur') : [];
        $typeOptions = function_exists('getRegistryFieldOptions') ? getRegistryFieldOptions('rami', 'type_acte') : [];

        $this->assertSame([
            'RSST-2026-001',           // [0]  référence
            'RSST',                    // [1]  strtoupper(type) — tue UnwrapStrToUpper
            '2026-01-15',              // [2]
            '14:30',                   // [3]
            'Hall B',                  // [4]
            'Pôle Nord',               // [5]
            'Service X',               // [6]
            '0601020304',              // [7]
            'UR Test',                 // [8]
            'Objet test',              // [9]
            'Description complète',    // [10]
            'Dupont',                  // [11]
            'Jean',                    // [12]
            'UR21',                    // [13] site_code (mode avec site)
            'UR Nom',                  // [14] site_nom
            $etat,                     // [15] état
            'Oui',                     // [16] is_confidential=1 — tue Ternary/LogicalNot
            'Acceptée',                // [17] consent_syndicat=1 — tue Ternary/LogicalNot
            '2026-01-15 10:00:00',     // [18] created_at
            'Pierre Martin',           // [19] pour le compte de — tue LogicalNot (branche if)
            $natureOptions['usager'] ?? 'usager',  // [20]
            $typeOptions['verbal'] ?? 'verbal',    // [21]
            '0',                       // [22] count(responses)
            'Réponse initiale',        // [23]
            'Marie Curie',             // [24] répondant — tue Coalesce swaps + Concat (ordre/omission)
            '2026-01-16 09:00:00',     // [25]
            '',                        // [26] historique (aucune réponse)
        ], $row, 'Chaque cellule doit refléter exactement la ligne source (aucun swap Coalesce toléré)');
    }

    public function testBuildCsvRowRespondantWithSurnameOnlyHasNoParasiteSpace(): void
    {
        // prenom absent : trim(' ' . 'Curie') → 'Curie' — verrouille la concat
        // (un ConcatOperandRemoval qui perdrait l'espace ou un opérande ressort).
        $rowSource = $this->fullRow();
        unset($rowSource['repondant_prenom']);
        $row = $this->service->buildCsvRow($rowSource, [], false, null);

        $this->assertSame('Curie', $row[24], 'répondant nom seul : pas d\'espace parasite');
    }

    public function testBuildCsvRowRespondantWithFirstnameOnlyHasNoParasiteSpace(): void
    {
        // nom absent : trim('Marie' . ' ') → 'Marie'.
        $rowSource = $this->fullRow();
        unset($rowSource['repondant_nom']);
        $row = $this->service->buildCsvRow($rowSource, [], false, null);

        $this->assertSame('Marie', $row[24], 'répondant prénom seul : pas d\'espace parasite');
    }

    public function testBuildCsvRowEmptyRowFallsBackToEmptyStrings(): void
    {
        // Mode sans site : pas de colonnes site_code/site_nom (25 cellules).
        // Ligne vide → tous les fallbacks `?? ''` s'appliquent.
        $row = $this->service->buildCsvRow([], [], true, null);

        $this->assertCount(25, $row, 'mode sans site : 13 base + 12 finales, sans colonnes site');
        $this->assertSame('', $row[0], 'référence absente → chaîne vide');
        $this->assertSame('', $row[1], 'type absent → chaîne vide (même après strtoupper)');
        $this->assertSame('', $row[12], 'declarant_prenom absent → chaîne vide');
        $this->assertSame('', $row[13], 'état absent → libellé chaîne vide');
        $this->assertSame('Non', $row[14], 'is_confidential absent → Non');
        $this->assertSame('Refusée', $row[15], 'consent_syndicat absent → Refusée');
        $this->assertSame('', $row[17], 'pour le compte de absent → chaîne vide');
        $this->assertSame('0', $row[20], 'aucune réponse → 0');
        $this->assertSame('', $row[24], 'historique absent → chaîne vide');
    }
}
