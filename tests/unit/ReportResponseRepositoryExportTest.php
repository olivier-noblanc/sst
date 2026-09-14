<?php

/**
 * Tests export — ReportResponseRepository::getResponsesForUuids()
 *
 * Bug exp-7 : l'export bulk-fetch jusqu'à EXPORT_MAX_ROWS (50 000) uuids puis
 * construit un seul IN (?,...). Avec PDO SQLite en prepares NATIFS
 * (PDO::ATTR_EMULATE_PREPARES = false), la limite SQLITE_MAX_VARIABLE_NUMBER
 * (32 766 par défaut depuis SQLite 3.32) est dépassée → PDOException
 * "too many SQL variables" → l'export échoue.
 *
 * Stratégie de test :
 *  - La connexion est DÉDIÉE (jamais le getDB() partagé du bootstrap) et force
 *    les prepares natifs : c'est la condition de reproduction. Avec l'émulation,
 *    PDO substitue les placeholders côté client et la limite ne s'applique pas
 *    (le bug restait invisible depuis la suite de tests).
 *  - La taille de chunk est injectable au constructeur → les frontières de
 *    merge sont testées avec de petits volumes, sans 32k+ inserts.
 *  - Le spy PDO compte les prepare() : seule observable noir-box du
 *    découpage effectif en plusieurs requêtes.
 *  - Le volume de reproduction est le MINIMAL dépassant la limite (32 767) :
 *    l'échec est dans la compilation SQL, il ne dépend pas des données en base.
 */

use PHPUnit\Framework\TestCase;
use App\Repository\ReportResponseRepository;

/**
 * Spy minimal : compte les prepare() tout en restant un vrai PDO SQLite.
 * Le type-hint `PDO` du repository accepte la sous-classe.
 */
final class QueryCountingPdo extends PDO
{
    public int $prepareCount = 0;

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->prepareCount++;
        return parent::prepare($query, $options);
    }
}

class ReportResponseRepositoryExportTest extends TestCase
{
    /**
     * Volume minimal dépassant la limite de variables SQLite native
     * (SQLITE_MAX_VARIABLE_NUMBER = 32 766 sur les builds récents).
     * Si un build embarquait une limite inférieure (999 sur les vieux builds),
     * le test resterait valide : la requête unique échouerait toujours.
     */
    private const UUID_COUNT_ABOVE_LIMIT = 32767;

    private QueryCountingPdo $pdo;

    protected function setUp(): void
    {
        // Reproduction du bug ⇒ prepares NATIFS obligatoires.
        $this->pdo = new QueryCountingPdo('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $schema = file_get_contents(__DIR__ . '/../../schema.sql');
        if ($schema === false) {
            throw new RuntimeException('schema.sql introuvable');
        }
        $this->pdo->exec($schema);
        // Fixtures : report_responses orphelines (sans parents reports/users).
        // La requête testée ne fait qu'un LEFT JOIN users ; les FK ne sont pas
        // l'objet du test → désactivées APRÈS le chargement du schema.
        $this->pdo->exec('PRAGMA foreign_keys = OFF');
    }

    /** @return list<string> */
    private function makeUuids(int $count): array
    {
        $uuids = [];
        for ($i = 0; $i < $count; $i++) {
            $uuids[] = sprintf('uuid-%05d-0000-4000-8000-000000000000', $i);
        }
        return $uuids;
    }

    private function seedResponse(string $reportUuid, string $reponse, string $createdAt): void
    {
        $this->pdo->prepare(
            'INSERT INTO report_responses (report_uuid, user_id, reponse, nouvel_etat, created_at) VALUES (?, NULL, ?, NULL, ?)'
        )->execute([$reportUuid, $reponse, $createdAt]);
    }

    // ═══ Reproduction du bug — volume dépassant la limite de variables SQL ═══

    public function testLargeUuidListDoesNotExceedSqliteVariableLimit(): void
    {
        $chunk = ReportResponseRepository::CHUNK_SIZE;
        $uuids = $this->makeUuids(self::UUID_COUNT_ABOVE_LIMIT);
        // Réponses placées exactement sur les frontières de chunks : début de
        // liste, dernière variable du chunk 1, première du chunk 2, dernière du
        // chunk 2, première du chunk 3, dernier uuid de la liste.
        $boundaryIndexes = [0, $chunk - 1, $chunk, 2 * $chunk - 1, 2 * $chunk, count($uuids) - 1];
        foreach ($boundaryIndexes as $i) {
            $this->seedResponse($uuids[$i], 'reponse-' . $i, sprintf('2026-01-01 00:%02d:00', $i % 60));
        }

        $result = (new ReportResponseRepository($this->pdo))->getResponsesForUuids($uuids);

        $this->assertCount(count($boundaryIndexes), $result);
        foreach ($boundaryIndexes as $i) {
            $this->assertArrayHasKey($uuids[$i], $result, "uuid #$i manquant (frontière de chunk)");
            $this->assertCount(1, $result[$uuids[$i]], "uuid #$i : la ligne ne doit pas être dupliquée");
            $this->assertSame('reponse-' . $i, $result[$uuids[$i]][0]['reponse']);
            $this->assertSame($uuids[$i], $result[$uuids[$i]][0]['report_uuid']);
        }
    }

    // ═══ Liste vide ═══

    public function testEmptyUuidListReturnsEmptyArray(): void
    {
        $result = (new ReportResponseRepository($this->pdo))->getResponsesForUuids([]);
        $this->assertSame([], $result);
    }

    // ═══ Plusieurs chunks — merge sans perte ni doublon ═══

    public function testMultipleChunksMergeWithoutLossOrDuplicates(): void
    {
        $uuids = $this->makeUuids(5);
        foreach ($uuids as $i => $uuid) {
            $this->seedResponse($uuid, 'reponse-' . $i, sprintf('2026-01-01 00:00:%02d', $i));
        }
        // 5 uuids réels + 2 fantômes (aucune réponse) = 7 entrées distinctes ;
        // chunkSize=2 → au moins 2 requêtes attendues (pré-fix : une seule).
        $input = [$uuids[0], 'uuid-fantome-a', $uuids[1], $uuids[2], 'uuid-fantome-b', $uuids[3], $uuids[4]];
        $prepareCountBefore = $this->pdo->prepareCount;

        $result = (new ReportResponseRepository($this->pdo, 2))->getResponsesForUuids($input);

        $this->assertGreaterThanOrEqual(
            2,
            $this->pdo->prepareCount - $prepareCountBefore,
            'la liste doit être découpée en plusieurs requêtes (chunking effectif)'
        );
        $this->assertCount(5, $result, 'seuls les uuids avec réponses apparaissent');
        foreach ($uuids as $i => $uuid) {
            $this->assertArrayHasKey($uuid, $result);
            $this->assertCount(1, $result[$uuid], 'aucune ligne ne doit être perdue ni dupliquée');
            $this->assertSame('reponse-' . $i, $result[$uuid][0]['reponse']);
        }
        $this->assertArrayNotHasKey('uuid-fantome-a', $result);
        $this->assertArrayNotHasKey('uuid-fantome-b', $result);
    }

    // ═══ Ordre préservé (ORDER BY created_at ASC) malgré le chunking ═══

    public function testResponsesWithinUuidKeepCreatedAtOrderAcrossChunkedFetch(): void
    {
        $uuids = $this->makeUuids(3);
        // Insérées en désordre pour vérifier le ORDER BY (pas l'ordre d'insertion).
        $this->seedResponse($uuids[0], 'rep-1200', '2026-01-01 12:00:00');
        $this->seedResponse($uuids[0], 'rep-1000', '2026-01-01 10:00:00');
        $this->seedResponse($uuids[0], 'rep-1100', '2026-01-01 11:00:00');
        $this->seedResponse($uuids[2], 'rep-autre', '2026-01-02 09:00:00');

        $result = (new ReportResponseRepository($this->pdo, 2))->getResponsesForUuids($uuids);

        $this->assertSame(
            ['rep-1000', 'rep-1100', 'rep-1200'],
            array_column($result[$uuids[0]], 'reponse'),
            'ordre created_at ASC préservé intra-uuid'
        );
        $this->assertSame(['rep-autre'], array_column($result[$uuids[2]], 'reponse'));
        $this->assertArrayNotHasKey($uuids[1], $result, 'uuid sans réponse → absent du résultat');
    }

    // ═══ Doublons d'input — l'IN(...) ne dupliquait pas les lignes, le chunking non plus ═══

    public function testDuplicateUuidsInInputDoNotDuplicateRows(): void
    {
        $uuids = $this->makeUuids(3);
        $this->seedResponse($uuids[0], 'rep-a', '2026-01-01 10:00:00');
        $this->seedResponse($uuids[1], 'rep-b', '2026-01-01 11:00:00');
        $this->seedResponse($uuids[2], 'rep-c', '2026-01-01 12:00:00');
        // uuid[0] dupliqué : sans déduplication, il tomberait dans 2 chunks
        // différents et sa ligne apparaîtrait deux fois dans le résultat.
        $input = [$uuids[0], $uuids[1], $uuids[0], $uuids[2]];

        $result = (new ReportResponseRepository($this->pdo, 2))->getResponsesForUuids($input);

        $this->assertCount(3, $result);
        $this->assertCount(1, $result[$uuids[0]], "doublon d'input → une seule ligne");
    }
}
