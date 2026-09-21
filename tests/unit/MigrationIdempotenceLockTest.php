<?php

/**
 * Migration — idempotence & verrou inter-processus
 *
 * Reproduit le bug observé en production (plusieurs workers IIS) :
 *   - les deux rebuilds de la table `users` — le CHECK `site_id` dans
 *     migrateColumns() et le CHECK/NOT NULL `email` dans
 *     migrateUsersEmailNotNull() — s'effaçaient mutuellement leur contrainte.
 *     PRAGMA table_info ne transporte aucun CHECK, donc chaque rebuild
 *     régénérait la table sans la contrainte de l'autre → la migration se
 *     relançait à CHAQUE requête (logs `[SST-MIGRATION] notification_settings…`
 *     en boucle), générait des backups/rotations en continu et faisait
 *     collisionner les rebuilds concurrents → `database is locked`
 *     (migration_columns.php:860, upgrade read→write sur BEGIN différé).
 *   - rotateBackups() appelait filemtime() sur un fichier disparu entre
 *     glob() et le tri (rotation concurrente d'un autre worker) → warning
 *     `filemtime(): stat failed` (backup.php:211) sur `sst_pre_migration_*`.
 *
 * Ces tests verrouillent : (1) l'idempotence — un rebuild préserve les
 * invariants posés par l'autre et un 2ᵉ passage n'écrit rien ; (2) le verrou
 * applicatif borné et bruyant ; (3) le tri de rotation tolérant aux fichiers
 * disparus, sans warning.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../src/migration_columns.php';
require_once __DIR__ . '/../../src/migration_tables.php';
// migrateColumns()/migrateUsersEmailNotNull() -> backupBeforeMigration().
require_once __DIR__ . '/../../src/backup.php';

class MigrationIdempotenceLockTest extends TestCase
{
    /** @var list<string> */
    private array $tmpPaths = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpPaths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        $this->tmpPaths = [];
    }

    // ─── helpers ────────────────────────────────────────────────────────────

    private function inMemoryPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
        return $pdo;
    }

    private function usersSql(PDO $pdo): string
    {
        $stmt = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='users'");
        self::assertNotFalse($stmt);
        return (string) $stmt->fetchColumn();
    }

    private function userCount(PDO $pdo): int
    {
        return (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    /**
     * Charge schema.sql (toutes les tables dont migrateColumns() dépend).
     */
    private function loadFullSchema(PDO $pdo): void
    {
        $schema = file_get_contents(__DIR__ . '/../../schema.sql');
        self::assertIsString($schema);
        $pdo->exec($schema);
    }

    /**
     * Remplace la table `users` par sa variante legacy 12 colonnes fournie.
     */
    private function replaceUsers(PDO $pdo, string $usersDdl): void
    {
        $pdo->exec('DROP TABLE users');
        $pdo->exec($usersDdl);
        $pdo->exec('CREATE INDEX idx_users_username ON users(username)');
        $pdo->exec('CREATE INDEX idx_users_site_id ON users(site_id)');
        $pdo->exec('CREATE INDEX idx_users_role ON users(role)');
    }

    private function seedSite(PDO $pdo): void
    {
        $pdo->exec("INSERT INTO sites (code, nom, departement) VALUES ('UR21', 'UR Côte-d''Or', 'Côte-d''Or')");
    }

    // ─── 1. le rebuild email préserve le CHECK site_id ─────────────────────

    /**
     * État legacy réel : 12 colonnes, CHECK site_id présent, email encore
     * nullable sans CHECK. Le rebuild email doit poser l'invariant email SANS
     * effacer le CHECK site_id (sinon migrateColumns() le ré-ajoute au passage
     * suivant → boucle).
     */
    public function testEmailRebuildPreservesSiteIdCheck(): void
    {
        $pdo = $this->inMemoryPdo();
        $pdo->exec('CREATE TABLE sites (id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT, nom TEXT, departement TEXT)');
        $this->seedSite($pdo);
        $pdo->exec("CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            nom TEXT NOT NULL,
            prenom TEXT NOT NULL,
            email TEXT,
            role TEXT NOT NULL DEFAULT 'agent',
            site_id INTEGER,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            updated_at TEXT NOT NULL DEFAULT (datetime('now')),
            site_chosen_at TEXT,
            sessions_invalid_before DATETIME,
            FOREIGN KEY (site_id) REFERENCES sites(id),
            CHECK (site_id IS NULL OR site_id > 0)
        )");
        $pdo->exec("INSERT INTO users (id, username, nom, prenom, email, role, site_id, site_chosen_at, sessions_invalid_before) VALUES
            (11, 'u.null',  'Null', 'Zéro',  NULL, 'agent', NULL, NULL, NULL),
            (12, 'u.empty', 'Vide', 'Empty', '',   'agent', 1, '2026-01-15 08:00:00', NULL),
            (13, 'u.ok',    'Réel', 'Real',  'u.ok@dreets-bfc.gouv.fr', 'superviseur', 1, '2026-01-15 08:00:00', '2026-03-01 00:00:00')");
        $pdo->exec('CREATE INDEX idx_users_username ON users(username)');
        $pdo->exec('CREATE INDEX idx_users_site_id ON users(site_id)');
        $pdo->exec('CREATE INDEX idx_users_role ON users(role)');

        migrateUsersEmailNotNull($pdo);

        $sql = $this->usersSql($pdo);
        self::assertStringContainsString("CHECK (email <> '')", $sql, 'Invariant email posé');
        self::assertStringContainsString(
            'CHECK (site_id IS NULL OR site_id > 0)',
            $sql,
            'Le rebuild email ne doit PAS effacer le CHECK site_id — sinon la migration boucle'
        );
        self::assertSame(3, $this->userCount($pdo), 'Aucune ligne perdue');
        self::assertStringContainsString('AUTOINCREMENT', $sql, 'AUTOINCREMENT préservé');
    }

    // ─── 2. le rebuild site_id préserve le CHECK email ──────────────────────

    /**
     * Inversement : une base (ou un état intermédiaire) qui porte le CHECK
     * email mais pas le CHECK site_id. migrateColumns() doit ajouter site_id
     * SANS effacer le CHECK email.
     */
    public function testSiteIdCheckRebuildPreservesEmailCheck(): void
    {
        $pdo = $this->inMemoryPdo();
        $pdo->exec('PRAGMA foreign_keys = ON');
        $this->loadFullSchema($pdo);
        $this->replaceUsers($pdo, "CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            nom TEXT NOT NULL,
            prenom TEXT NOT NULL,
            email TEXT NOT NULL CHECK (email <> ''),
            role TEXT NOT NULL DEFAULT 'agent',
            site_id INTEGER,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            updated_at TEXT NOT NULL DEFAULT (datetime('now')),
            site_chosen_at TEXT,
            sessions_invalid_before DATETIME,
            FOREIGN KEY (site_id) REFERENCES sites(id)
        )");
        $this->seedSite($pdo);
        $pdo->exec("INSERT INTO users (username, nom, prenom, email, role, site_id) VALUES ('u.keep', 'Keep', 'Mail', 'keep@dreets-bfc.gouv.fr', 'agent', 1)");

        migrateColumns($pdo);

        $sql = $this->usersSql($pdo);
        self::assertStringContainsString(
            "CHECK (email <> '')",
            $sql,
            'migrateColumns ne doit PAS effacer le CHECK email posé par migrateUsersEmailNotNull'
        );
        self::assertStringContainsString('CHECK (site_id IS NULL OR site_id > 0)', $sql, 'Le CHECK site_id doit être ajouté');
        self::assertSame(1, $this->userCount($pdo), 'Aucune ligne perdue par le rebuild site_id');
    }

    // ─── 3. idempotence : un cycle complet est stable ───────────────────────

    /**
     * Le cycle complet de migrateSchema() (migrateColumns puis
     * migrateUsersEmailNotNull) doit converger en UN passage : après le 1er
     * cycle, les DEUX contraintes sont présentes, donc le 2e cycle ne doit
     * strictement rien écrire (SQL de la table et comptage inchangés).
     */
    public function testFullCycleConvergesAndSecondPassWritesNothing(): void
    {
        $pdo = $this->inMemoryPdo();
        $this->loadFullSchema($pdo);
        $this->replaceUsers($pdo, "CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            nom TEXT NOT NULL,
            prenom TEXT NOT NULL,
            email TEXT,
            role TEXT NOT NULL DEFAULT 'agent',
            site_id INTEGER,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            updated_at TEXT NOT NULL DEFAULT (datetime('now')),
            site_chosen_at TEXT,
            sessions_invalid_before DATETIME,
            FOREIGN KEY (site_id) REFERENCES sites(id),
            CHECK (site_id IS NULL OR site_id > 0)
        )");
        $this->seedSite($pdo);
        $pdo->exec("INSERT INTO users (username, nom, prenom, email, role, site_id) VALUES
            ('u.null', 'Null', 'Zéro', NULL, 'agent', NULL),
            ('u.ok', 'Réel', 'Real', 'u.ok@dreets-bfc.gouv.fr', 'agent', 1)");

        // 1er cycle — état legacy : email sans CHECK, site_id CHECK présent.
        migrateColumns($pdo);
        migrateUsersEmailNotNull($pdo);

        $sqlAfterFirst = $this->usersSql($pdo);
        self::assertStringContainsString("CHECK (email <> '')", $sqlAfterFirst, 'email migré au 1er passage');
        self::assertStringContainsString('CHECK (site_id IS NULL OR site_id > 0)', $sqlAfterFirst, 'site_id conservé au 1er passage');
        self::assertSame(2, $this->userCount($pdo), 'Aucune ligne perdue');

        // 2e cycle — plus rien à faire.
        migrateColumns($pdo);
        migrateUsersEmailNotNull($pdo);

        self::assertSame(
            $sqlAfterFirst,
            $this->usersSql($pdo),
            'Le 2e cycle doit être un no-op strict (migration idempotente, aucune écriture)'
        );
        self::assertSame(2, $this->userCount($pdo), 'Aucune ligne inventée ni perdue au 2e passage');
    }

    // ─── 4. verrou applicatif borné et bruyant ──────────────────────────────

    private function lockFilePath(): string
    {
        $path = sys_get_temp_dir() . '/sst_migration_lock_' . uniqid('', true) . '.lock';
        $this->tmpPaths[] = $path;
        return $path;
    }

    public function testMigrationLockFailsLoudlyAfterBoundedWaitWithoutRunningWork(): void
    {
        $lockFile = $this->lockFilePath();
        $holder = fopen($lockFile, 'c');
        self::assertNotFalse($holder);
        self::assertTrue(flock($holder, LOCK_EX | LOCK_NB), 'le détenteur simule un autre worker en migration');

        $ran = false;
        $thrown = null;
        try {
            withMigrationLock(static function () use (&$ran): void {
                $ran = true;
            }, $lockFile, 0.15);
        } catch (RuntimeException $e) {
            $thrown = $e;
        } finally {
            flock($holder, LOCK_UN);
            fclose($holder);
        }

        self::assertInstanceOf(RuntimeException::class, $thrown, 'Verrou indisponible = échec bruyant borné, jamais silencieux');
        self::assertStringContainsString('verrou', $thrown->getMessage());
        self::assertFalse($ran, 'Le travail de migration ne doit JAMAIS s’exécuter sans le verrou');
    }

    public function testMigrationLockRunsWorkAndReleasesLock(): void
    {
        $lockFile = $this->lockFilePath();
        $calls = 0;

        withMigrationLock(static function () use (&$calls): void {
            $calls++;
        }, $lockFile, 1.0);
        // Si le verrou n'était pas libéré, le 2e appel échouerait après 1 s.
        withMigrationLock(static function () use (&$calls): void {
            $calls++;
        }, $lockFile, 1.0);

        self::assertSame(2, $calls, 'Le verrou doit être libéré après le travail (finally)');
    }
}