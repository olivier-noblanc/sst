<?php
/**
 * NotificationRepository Atomicity Test — Application SST DREETS BFC
 *
 * Correctif moyen (audit) — les onglets settings « sites » et « global »
 * enchaînaient deleteByType() + N save() HORS transaction : un échec au
 * milieu (ex. FK site_id inexistant, disque plein) laissait la table dans
 * un état partiel — anciennes notifications DÉJÀ supprimées, nouvelles
 * partiellement insérées. L'admin devait tout ressaisir.
 *
 * NotificationRepository::replaceByType() remplace cet enchaînement par
 * une opération transactionnelle tout-ou-rien dans le repository
 * (rollback + rethrow — crash hard, jamais d'échec silencieux, AGENTS.md).
 *
 * Le déclencheur d'échec « au milieu » est déterministe : le schéma
 * (schema.sql) porte une FK réelle notification_settings.site_id →
 * sites(id) et getDB() active PRAGMA foreign_keys = ON — une entrée
 * pointant vers un site inexistant fait échouer l'INSERT N, après les
 * INSERT 1..N-1 réussis.
 */

use PHPUnit\Framework\TestCase;
use App\Repository\NotificationRepository;

class NotificationRepositoryAtomicityTest extends TestCase
{
    private PDO $pdo;
    private NotificationRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        // Sites : on ne purge PAS toute la table (des seeds d'autres tests
        // peuvent en dépendre — voir bootstrap.php cleanupAllForTest) — on
        // utilise des codes dédiés et on ne nettoie que les nôtres.
        $this->pdo->exec("DELETE FROM notification_settings");
        $this->repo = new NotificationRepository($this->pdo);
    }

    protected function tearDown(): void
    {
        $this->pdo->exec("DELETE FROM notification_settings");
        $this->pdo->exec("DELETE FROM sites WHERE code LIKE 'TESTATOMIC-%'");
    }

    private function seedSite(string $code): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO sites (code, nom, departement, is_active) VALUES (:code, :nom, :dep, 1)'
        );
        $stmt->execute([':code' => $code, ':nom' => 'UR Test ' . $code, ':dep' => 'Test']);
        return (int) $this->pdo->lastInsertId();
    }

    private function seedNotif(?int $siteId, string $type, string $email): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO notification_settings (site_id, type, registry, email)
             VALUES (:site_id, :type, :registry, :email)'
        );
        $stmt->execute([
            ':site_id'  => $siteId,
            ':type'     => $type,
            ':registry' => 'all',
            ':email'    => $email,
        ]);
    }

    /** @return list<array{site_id: int|string|null, email: string}> */
    private function notifRows(string $type): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT site_id, email FROM notification_settings WHERE type = :type ORDER BY email'
        );
        $stmt->execute([':type' => $type]);
        /** @var list<array{site_id: int|string|null, email: string}> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // SQLite retourne les ints en string via PDO par défaut — normalise
        // pour des comparaisons fiables (NULL reste NULL).
        return array_map(static fn(array $r): array => [
            'site_id' => $r['site_id'] === null ? null : (int) $r['site_id'],
            'email'   => $r['email'],
        ], $rows);
    }

    // ═══ replaceByType — tout ou rien ═══

    /**
     * LE test rouge du correctif : échec au milieu de l'opération (FK
     * violation sur la 2e entrée) → l'exception est rethrowée ET la table
     * est exactement dans son état d'origine (pas de delete partiel,
     * pas d'insert partiel).
     */
    public function testReplaceByTypeIsAtomicOnMidWayFailure(): void
    {
        $siteA = $this->seedSite('TESTATOMIC-A');
        $this->seedNotif($siteA, 'site', 'ancien@exemple.fr');

        $entries = [
            ['site_id' => $siteA, 'email' => 'nouveau@exemple.fr'],      // réussit
            ['site_id' => 999999999, 'email' => 'fantome@exemple.fr'],   // FK → échec au milieu
        ];

        try {
            $this->repo->replaceByType('site', $entries);
            $this->fail('PDOException attendue — échec FK au milieu de l\'opération (crash hard, rethrow).');
        } catch (PDOException $e) {
            // Attendu : l'exception d'origine remonte (rethrow, pas de catch silencieux).
        }

        // Rollback complet : l'ancienne notification est INTACTE, aucune
        // ligne nouvelle n'a survécu.
        $this->assertSame(
            [['site_id' => $siteA, 'email' => 'ancien@exemple.fr']],
            $this->notifRows('site'),
            'Après un échec au milieu, l\'état d\'origine doit être restauré (rollback).'
        );
    }

    public function testReplaceByTypeReplacesAllEntriesOnSuccess(): void
    {
        $siteA = $this->seedSite('TESTATOMIC-A');
        $siteB = $this->seedSite('TESTATOMIC-B');
        $this->seedNotif($siteA, 'site', 'ancien@exemple.fr');

        $this->repo->replaceByType('site', [
            ['site_id' => $siteA, 'email' => 'un@exemple.fr'],
            ['site_id' => $siteB, 'email' => 'deux@exemple.fr'],
        ]);

        $rows = $this->notifRows('site');
        $this->assertCount(2, $rows);
        $this->assertNotContains('ancien@exemple.fr', array_column($rows, 'email'), 'Les anciennes lignes du type sont supprimées.');

        $stmt = $this->pdo->query("SELECT DISTINCT registry FROM notification_settings WHERE type = 'site'");
        $this->assertSame(['all'], $stmt->fetchAll(PDO::FETCH_COLUMN), 'registry="all" préservé (comportement existant des onglets).');
    }

    /**
     * Portée « global » préservée : site_id doit rester NULL en base
     * (vérité DB — AGENTS.md : jamais 0 en colonne).
     */
    public function testReplaceByTypeGlobalKeepsNullSiteId(): void
    {
        $this->repo->replaceByType('global', [
            ['site_id' => null, 'email' => 'global1@exemple.fr'],
            ['site_id' => null, 'email' => 'global2@exemple.fr'],
        ]);

        $rows = $this->notifRows('global');
        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertNull($row['site_id'], 'Une notification globale a site_id NULL en base.');
        }
    }

    /**
     * Entrées vides = on VIDE la portée (comportement existant :
     * deleteByType + 0 save) — mais les autres types ne sont pas touchés.
     */
    public function testReplaceByTypeEmptyEntriesClearsTypeOnly(): void
    {
        $siteA = $this->seedSite('TESTATOMIC-A');
        $this->seedNotif($siteA, 'site', 'site@exemple.fr');
        $this->seedNotif(null, 'global', 'global@exemple.fr');

        $this->repo->replaceByType('site', []);

        $this->assertSame([], $this->notifRows('site'), 'La portée site est vidée.');
        $this->assertCount(1, $this->notifRows('global'), 'La portée global est intacte (isolation des types de portée).');
    }

    // ═══ Caractérisation du bug historique ═══

    /**
     * Documente le bug corrigé : l'ANCIEN enchaînement deleteByType() + N
     * save() n'est PAS atomique — un échec au milieu perd les anciennes
     * notifications et laisse un état partiel. Ce test reste vert avant et
     * après le correctif ; il garde le contrat des méthodes legacy et
     * justifie replaceByType(). Le handler ne doit plus utiliser cet
     * enchaînement (voir handlers/settings_handler.php).
     */
    public function testLegacyChainIsNotAtomic(): void
    {
        $siteA = $this->seedSite('TESTATOMIC-A');
        $this->seedNotif($siteA, 'site', 'ancien@exemple.fr');

        // Ancien enchaînement du handler (settings_handler.php avant fix)
        $this->repo->deleteByType('site');
        try {
            $this->repo->save($siteA, 'site', 'all', 'nouveau@exemple.fr');
            $this->repo->save(999999999, 'site', 'all', 'fantome@exemple.fr');
        } catch (PDOException $e) {
            // Échec attendu au milieu — l'ancien code n'annulait rien.
        }

        $rows = $this->notifRows('site');
        $this->assertSame(
            [['site_id' => $siteA, 'email' => 'nouveau@exemple.fr']],
            $rows,
            'Bug documenté : anciennes lignes perdues + état partiel (d\'où replaceByType).'
        );
    }
}
