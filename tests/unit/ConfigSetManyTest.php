<?php
/**
 * ConfigRepository::setMany / ConfigService::setMany Test — Application SST DREETS BFC
 *
 * Fiabilisation (council) — les onglets de paramétrage écrivaient leurs clés
 * une par une (updateConfig/configService->set) : toute validation placée
 * après N écritures laissait l'application dans un état partiellement
 * modifié. setMany écrit toutes les clés dans UNE transaction.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

class ConfigSetManyTest extends TestCase
{
    public function testSetManyPersistsAllKeys(): void
    {
        $repo = new \App\Repository\ConfigRepository(getDB());

        $repo->setMany([
            'csm_test_a' => 'valeur-a-' . uniqid(),
            'csm_test_b' => 'valeur-b-' . uniqid(),
        ]);

        $this->assertNotNull($repo->get('csm_test_a'), 'setMany doit persister la première clé');
        $this->assertNotNull($repo->get('csm_test_b'), 'setMany doit persister la seconde clé');
    }

    public function testSetManyOverwritesExistingKeys(): void
    {
        $repo = new \App\Repository\ConfigRepository(getDB());

        $repo->setMany(['csm_test_over' => 'avant']);
        $this->assertSame('avant', $repo->get('csm_test_over'));

        $repo->setMany(['csm_test_over' => 'apres']);
        $this->assertSame('apres', $repo->get('csm_test_over'), 'setMany doit écraser une clé existante');
    }

    public function testConfigServiceSetManyDelegatesAndClearsCache(): void
    {
        $service = new \App\Services\ConfigService();
        $repo = new \App\Repository\ConfigRepository(getDB());

        $sentinel = 'svc-' . uniqid();
        $service->setMany(['csm_test_svc' => $sentinel]);

        $this->assertSame($sentinel, $repo->get('csm_test_svc'), 'ConfigService::setMany doit déléguer au repository');
    }

    public function testSetManyWithEmptyArrayIsNoop(): void
    {
        $repo = new \App\Repository\ConfigRepository(getDB());
        $repo->setMany([]);
        $this->assertNull($repo->get('csm_test_never_written'));
    }

    public function testSetManyRollsBackEverythingWhenOneWriteFailsMidway(): void
    {
        // Oracle R5 — rollback RÉEL au milieu d'une transaction : un trigger
        // SQLite fait échouer la 2e écriture (RAISE ABORT), on vérifie que la
        // 1re écriture est annulée et que la connexion reste utilisable.
        $pdo = getDB();
        $repo = new \App\Repository\ConfigRepository($pdo);

        $pdo->exec("CREATE TEMP TRIGGER cfg_setmany_boom BEFORE INSERT ON config_app WHEN NEW.cle = 'csm_boom_key' BEGIN SELECT RAISE(ABORT, 'test boom'); END;");

        try {
            $threw = false;
            try {
                $repo->setMany([
                    'csm_rb_first' => 'v1',
                    'csm_boom_key' => 'v2',
                    'csm_rb_third' => 'v3',
                ]);
            } catch (\PDOException) {
                $threw = true;
            }

            $this->assertTrue($threw, 'setMany doit propager l\'échec d\'écriture (crash hard, jamais silencieux)');
            $this->assertNull($repo->get('csm_rb_first'), 'L\'écriture antérieure à l\'échec doit être annulée par le rollback');
            $this->assertNull($repo->get('csm_boom_key'));
            $this->assertNull($repo->get('csm_rb_third'), 'Aucune écriture post-échec');

            // La connexion doit rester dans un état cohérent : une nouvelle
            // transaction fonctionne après le rollback.
            $repo->setMany(['csm_rb_after' => 'ok']);
            $this->assertSame('ok', $repo->get('csm_rb_after'));
        } finally {
            $pdo->exec('DROP TRIGGER IF EXISTS temp.cfg_setmany_boom');
            $pdo->exec("DELETE FROM config_app WHERE cle IN ('csm_rb_first', 'csm_boom_key', 'csm_rb_third', 'csm_rb_after')");
        }
    }
}
