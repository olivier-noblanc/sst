<?php
/**
 * Email Invariant Test — Application SST DREETS BFC
 *
 * Décision produit (oracle) : users.email NOT NULL — email réel obligatoire
 * pour les comptes créés/édités via la validation utilisateur, et sentinelle
 * d'anonymisation 'anonyme@anonyme.invalid' (jamais NULL, jamais de mail).
 *
 * Source de vérité unique : AnonymizationPolicy::ANONYMIZED_EMAIL.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../src/migration_columns.php';
// Isolation : ce test utilise des fonctions procédurales que d'autres fichiers
// de tests chargeaient implicitement avant lui dans l'ordre de la suite. Le
// déclarer explicitement le rend insensible à l'ordre d'exécution (require_once
// = idempotent). backupBeforeMigration() est requis par migrateUsersEmailNotNull().
require_once __DIR__ . '/../../src/mail.php';
require_once __DIR__ . '/../../src/mail_notifications.php';
require_once __DIR__ . '/../../src/backup.php';

class EmailInvariantTest extends TestCase
{
    public function testAnonymizationWritesSentinelEmailInsteadOfNull(): void
    {
        // Rouge avant implémentation : anonymize() écrivait email = NULL.
        $user = \App\Repository\UserRepository::instance()->create(
            new \App\DTO\CreateUserCommand(
                username: 'inv.anon.' . uniqid(),
                nom: 'Nommé', prenom: 'Prénommé',
                role: 'agent',
                siteId: \App\DTO\SiteId::none(),
                email: 'avant.anonymisation@dreets-bfc.gouv.fr',
            )
        );
        $this->assertNotFalse(\App\Repository\UserRepository::instance()->anonymize($id = $user));

        $row = \App\Repository\UserRepository::instance()->findById($id);
        $this->assertSame(
            \App\Repository\AnonymizationPolicy::ANONYMIZED_EMAIL,
            $row->email,
            'L\'anonymisation doit écrire la sentinelle, jamais NULL'
        );
    }

    public function testSendMailToSentinelIsNeutralizedAndSucceeds(): void
    {
        // Le guard du chokepoint sendMail doit court-circuiter : aucun envoi,
        // retour sémantiquement succès (true) même sans SMTP configuré.
        $result = sendMail(
            \App\Repository\AnonymizationPolicy::ANONYMIZED_EMAIL,
            'Test sentinelle',
            '<html><body>Ne doit jamais partir.</body></html>'
        );
        $this->assertTrue($result, 'Envoyer vers la sentinelle = aucun envoi requis, succès sémantique (pas un faux échec)');
    }

    /**
     * Oracle — les comparaisons de sentinelle sont INSENSIBLES à la casse :
     * une variante 'ANONYME@ANONYME.INVALID' saisie par un utilisateur doit
     * être refusée à la validation, ne jamais recevoir de mail et ne jamais
     * être un destinataire de notification.
     */
    public function testValidateRefusesSentinelWithUppercaseCasing(): void
    {
        $service = new \App\Services\UserService(
            \App\Repository\UserRepository::instance(),
            new \App\Event\EventDispatcher()
        );
        $cmd = new \App\DTO\CreateUserCommand(
            username: 'inv.casse.' . uniqid(),
            nom: 'Nom', prenom: 'Prenom', role: 'agent',
            siteId: \App\DTO\SiteId::none(),
            email: 'ANONYME@ANONYME.INVALID',
        );
        $errors = $service->validate($cmd);
        $this->assertArrayHasKey('email', $errors, 'La variante majuscule de la sentinelle doit être refusée');
    }

    public function testSendMailBlocksSentinelCaseInsensitive(): void
    {
        $result = sendMail(
            'ANONYME@ANONYME.INVALID',
            'Test sentinelle casse',
            '<html><body>Ne doit jamais partir.</body></html>'
        );
        $this->assertTrue($result, 'Le guard sendMail doit être insensible à la casse de la sentinelle');
    }

    public function testResponseNotificationSkipsSentinelCaseInsensitive(): void
    {
        $targets = buildResponseNotificationTargets(
            ['email' => 'ANONYME@ANONYME.INVALID'],
            [['email' => 'Anonyme@Anonyme.invalid', 'prenom' => 'Anon']]
        );
        $this->assertSame([], $targets, 'Aucune variante de casse de la sentinelle ne reçoit de notification');
    }

    /**
     * Oracle ora-4 (réserve) — casse MÉLANGÉE sur les trois chokepoints :
     * validation, guard sendMail et sélection des destinataires. La casse
     * mélangée est le cas le plus facile à rater avec une comparaison
     * sensible à la casse ('Anonyme@Anonyme.invalid' ≠ 'anonyme@anonyme.invalid').
     */
    public function testValidateRefusesSentinelWithMixedCasing(): void
    {
        $service = new \App\Services\UserService(
            \App\Repository\UserRepository::instance(),
            new \App\Event\EventDispatcher()
        );
        $cmd = new \App\DTO\CreateUserCommand(
            username: 'inv.casse.mix.' . uniqid(),
            nom: 'Nom', prenom: 'Prenom', role: 'agent',
            siteId: \App\DTO\SiteId::none(),
            email: 'Anonyme@Anonyme.invalid',
        );
        $errors = $service->validate($cmd);
        $this->assertArrayHasKey('email', $errors, 'La variante de casse mélangée de la sentinelle doit être refusée');
    }

    public function testSendMailBlocksSentinelWithMixedCasing(): void
    {
        $result = sendMail(
            'Anonyme@Anonyme.invalid',
            'Test sentinelle casse mélangée',
            '<html><body>Ne doit jamais partir.</body></html>'
        );
        $this->assertTrue($result, 'Le guard sendMail doit bloquer la casse mélangée de la sentinelle (succès sémantique, aucun envoi)');
    }

    public function testResponseNotificationSkipsSentinelWithMixedCasing(): void
    {
        $targets = buildResponseNotificationTargets(
            ['email' => 'Anonyme@Anonyme.invalid'],
            [['email' => 'ANONYME@anonyme.INVALID', 'prenom' => 'Anon']]
        );
        $this->assertSame([], $targets, 'Aucune variante de casse mélangée de la sentinelle ne reçoit de notification');
    }

    public function testValidateRequiresRealEmailForCreation(): void
    {
        $service = new \App\Services\UserService(
            \App\Repository\UserRepository::instance(),
            new \App\Event\EventDispatcher()
        );
        $cmd = new \App\DTO\CreateUserCommand(
            username: 'inv.email.' . uniqid(),
            nom: 'Nom', prenom: 'Prenom', role: 'agent',
            siteId: \App\DTO\SiteId::none(),
            email: '',
        );
        $errors = $service->validate($cmd);
        $this->assertArrayHasKey('email', $errors, 'L\'email réel est obligatoire à la création (email vide refusé)');
    }

    public function testValidateRefusesReservedSentinelAsUserInput(): void
    {
        $service = new \App\Services\UserService(
            \App\Repository\UserRepository::instance(),
            new \App\Event\EventDispatcher()
        );
        $cmd = new \App\DTO\UpdateUserCommand(
            username: 'inv.sent.' . uniqid(),
            nom: 'Nom', prenom: 'Prenom', role: 'agent',
            siteId: \App\DTO\SiteId::none(),
            email: \App\Repository\AnonymizationPolicy::ANONYMIZED_EMAIL,
        );
        $errors = $service->validate($cmd, 999999);
        $this->assertArrayHasKey('email', $errors, 'La sentinelle saisie par un utilisateur doit être refusée explicitement');
    }

    public function testUsersEmailColumnIsNotNullWithCheckWithoutDefault(): void
    {
        $pdo = getDB();
        $cols = $pdo->query('PRAGMA table_info(users)')->fetchAll();
        $emailCol = null;
        foreach ($cols as $col) {
            if ($col['name'] === 'email') {
                $emailCol = $col;
            }
        }
        $this->assertNotNull($emailCol);
        $this->assertEquals(1, $emailCol['notnull'], 'users.email doit être NOT NULL après migration');
        $this->assertNull(
            $emailCol['dflt_value'],
            'AUCUN DEFAULT : la sentinelle est réservée au chemin d\'anonymisation'
        );
        $sql = (string) $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='users'")->fetchColumn();
        $this->assertStringContainsString("CHECK (email <> '')", $sql, 'CHECK email non-vide présent');
    }

    public function testNoUserHasNullOrBlankEmailAfterMigration(): void
    {
        $count = (int) getDB()->query(
            "SELECT COUNT(*) FROM users WHERE email IS NULL OR email = ''"
        )->fetchColumn();
        $this->assertSame(0, $count, 'Post-migration : aucun NULL/vide (backfill vers la sentinelle)');
    }

    public function testInsertUserWithoutEmailIsRejected(): void
    {
        // Décision produit (oracle) — la sentinelle est RÉSERVÉE au chemin
        // d'anonymisation : le DDL n'a PAS de DEFAULT sentinelle, un INSERT
        // sans email explicite est REFUSÉ (NOT NULL sans default). L'ancien
        // DEFAULT rendait les fixtures indiscernables d'un anonymisé.
        $this->expectException(PDOException::class);
        getDB()->exec("INSERT INTO users (username, nom, prenom, role, site_id, is_active) VALUES ('inv.no.email', 'Sans', 'Email', 'agent', NULL, 1)");
    }

    public function testNoRealAccountCarriesTheSentinelExceptAnonymized(): void
    {
        // La sentinelle ne doit apparaître QUE sur les comptes anonymisés.
        $count = (int) getDB()->query(
            "SELECT COUNT(*) FROM users WHERE email = '" . \App\Repository\AnonymizationPolicy::ANONYMIZED_EMAIL . "' AND username NOT LIKE 'anonymized_%'"
        )->fetchColumn();
        $this->assertSame(0, $count, 'La sentinelle est exclusive au chemin d\'anonymisation (username anonymized_*)');
    }

    public function testResponseNotificationSkipsSentinelRecipients(): void
    {
        // Un compte anonymisé (email sentinelle) n'est jamais un destinataire.
        $targets = buildResponseNotificationTargets(
            ['email' => \App\Repository\AnonymizationPolicy::ANONYMIZED_EMAIL],
            [
                ['email' => \App\Repository\AnonymizationPolicy::ANONYMIZED_EMAIL, 'prenom' => 'Anon'],
                ['email' => 'agent.reel@dreets-bfc.gouv.fr', 'prenom' => 'Al'],
            ]
        );
        $this->assertSame(
            ['agent.reel@dreets-bfc.gouv.fr'],
            array_column($targets, 'email'),
            'La sentinelle n\'est jamais un destinataire de notification'
        );
    }

    /**
     * B1 (oracle) — fixture du schéma users LEGACY RÉEL : 12 colonnes.
     *
     * Les 10 colonnes historiques de schema.sql + site_chosen_at et
     * sessions_invalid_before, ajoutées par migrateColumns() via ALTER TABLE
     * (donc appendées en fin de table). email est encore nullable
     * (pré-invariant) ; username UNIQUE, AUTOINCREMENT, FK sites(id),
     * CHECK site_id et les 3 index nommés sont en place — migrateColumns()
     * tourne AVANT migrateUsersEmailNotNull() dans migrateSchema().
     *
     * L'ancien préflight n'attendait que 10 colonnes : il crashait sur cette
     * DB réelle — APRÈS avoir commité le backfill (état incohérent).
     */
    private function createLegacyUsersPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE sites (id INTEGER PRIMARY KEY AUTOINCREMENT, nom TEXT NOT NULL)');
        $pdo->exec("INSERT INTO sites (id, nom) VALUES (1, 'DREETS BFC')");
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
        $pdo->exec("INSERT INTO users (id, username, nom, prenom, email, role, site_id, is_active, site_chosen_at, sessions_invalid_before) VALUES
            (11, 'u.null',  'Null', 'Zéro',  NULL, 'agent', NULL, 1, NULL, NULL),
            (12, 'u.empty', 'Vide', 'Empty', '',   'agent', 1, 1, '2026-01-15 08:00:00', NULL),
            (13, 'u.ok',    'Réel', 'Real',  'u.ok@dreets-bfc.gouv.fr', 'superviseur', 1, 1, '2026-01-15 08:00:00', '2026-03-01 00:00:00')");
        $pdo->exec('CREATE INDEX idx_users_username ON users(username)');
        $pdo->exec('CREATE INDEX idx_users_site_id ON users(site_id)');
        $pdo->exec('CREATE INDEX idx_users_role ON users(role)');
        return $pdo;
    }

    private function userEmail(PDO $pdo, string $username): mixed
    {
        $stmt = $pdo->prepare('SELECT email FROM users WHERE username = :u');
        $stmt->execute([':u' => $username]);
        return $stmt->fetchColumn();
    }

    /**
     * B1 (oracle) — migration du schéma legacy RÉEL à 12 colonnes : 3/3
     * lignes conservées (NULL/vide/réel), sentinelle sur NULL et vide,
     * contenu des colonnes legacy récentes préservé, invariant email NOT
     * NULL + CHECK sans DEFAULT, AUTOINCREMENT conservé.
     */
    public function testLegacyTwelveColumnMigrationPreservesRowsAndSentinel(): void
    {
        $pdo = $this->createLegacyUsersPdo();
        migrateUsersEmailNotNull($pdo);

        // Aucune perte — 3/3 lignes conservées.
        $this->assertSame(3, (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(), 'Aucune ligne ne doit être perdue par le rebuild');
        $sentinel = \App\Repository\AnonymizationPolicy::ANONYMIZED_EMAIL;
        $this->assertSame($sentinel, $this->userEmail($pdo, 'u.null'), 'email NULL → sentinelle');
        $this->assertSame($sentinel, $this->userEmail($pdo, 'u.empty'), 'email vide → sentinelle');
        $this->assertSame('u.ok@dreets-bfc.gouv.fr', $this->userEmail($pdo, 'u.ok'), 'email réel intact');

        // Les 12 colonnes du schéma legacy réel sont toutes conservées.
        $names = array_column($pdo->query('PRAGMA table_info(users)')->fetchAll(), 'name');
        sort($names);
        $this->assertSame([
            'created_at', 'email', 'id', 'is_active', 'nom', 'prenom', 'role',
            'sessions_invalid_before', 'site_chosen_at', 'site_id', 'updated_at', 'username',
        ], $names, 'Les 12 colonnes du schéma legacy réel doivent être conservées');

        // Contenu préservé — un SELECT * positionnel mal aligné brouillerait
        // site_chosen_at / sessions_invalid_before dans is_active / created_at.
        $row = $pdo->query("SELECT nom, prenom, role, site_id, is_active, site_chosen_at, sessions_invalid_before, created_at, updated_at FROM users WHERE username = 'u.ok'")->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($row);
        $this->assertSame('Réel', $row['nom']);
        $this->assertSame('Real', $row['prenom']);
        $this->assertSame('superviseur', $row['role']);
        $this->assertSame(1, (int) $row['site_id']);
        $this->assertSame(1, (int) $row['is_active']);
        $this->assertSame('2026-01-15 08:00:00', $row['site_chosen_at']);
        $this->assertSame('2026-03-01 00:00:00', $row['sessions_invalid_before']);
        $this->assertNotEmpty($row['created_at']);
        $this->assertNotEmpty($row['updated_at']);

        // Invariant cible : email NOT NULL, SANS DEFAULT, CHECK (email <> '').
        $emailCol = null;
        foreach ($pdo->query('PRAGMA table_info(users)')->fetchAll() as $col) {
            if ($col['name'] === 'email') {
                $emailCol = $col;
            }
        }
        $this->assertNotNull($emailCol);
        $this->assertSame(1, (int) $emailCol['notnull'], 'email NOT NULL après migration');
        $this->assertNull($emailCol['dflt_value'], 'AUCUN DEFAULT : sentinelle réservée au chemin d\'anonymisation');
        $sql = (string) $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='users'")->fetchColumn();
        $this->assertStringContainsString("CHECK (email <> '')", $sql, 'CHECK email non-vide présent');
        $this->assertStringContainsString('AUTOINCREMENT', $sql, 'AUTOINCREMENT préservé');
    }

    /**
     * B1 (oracle) — le rebuild à 12 colonnes ne perd AUCUNE contrainte :
     * username UNIQUE, AUTOINCREMENT (sqlite_sequence), FK sites appliquée,
     * index nommés recréés, et toujours AUCUNE contrainte UNIQUE sur email.
     */
    public function testLegacyTwelveColumnMigrationPreservesConstraintsAndIndexes(): void
    {
        $pdo = $this->createLegacyUsersPdo();
        migrateUsersEmailNotNull($pdo);

        // Index nommés recréés après le DROP TABLE.
        $indexNames = array_column($pdo->query('PRAGMA index_list(users)')->fetchAll(), 'name');
        $this->assertContains('idx_users_username', $indexNames);
        $this->assertContains('idx_users_site_id', $indexNames);
        $this->assertContains('idx_users_role', $indexNames);

        // Aucune contrainte UNIQUE sur email (sentinelle partagée).
        foreach ($pdo->query('PRAGMA index_list(users)')->fetchAll() as $idx) {
            if ((int) ($idx['unique'] ?? 0) !== 1) {
                continue;
            }
            $cols = array_column($pdo->query("PRAGMA index_info('" . $idx['name'] . "')")->fetchAll(), 'name');
            $this->assertNotContains('email', $cols, 'email ne doit jamais être UNIQUE');
        }

        // Unicité username toujours appliquée.
        try {
            $pdo->exec("INSERT INTO users (username, nom, prenom, email, role) VALUES ('u.ok', 'X', 'Y', 'dup@dreets-bfc.gouv.fr', 'agent')");
            $this->fail('username UNIQUE doit être préservée après rebuild');
        } catch (PDOException) {
            // attendu
        }

        // FK site_id → sites(id) préservée ET appliquée.
        $siteFk = null;
        foreach ($pdo->query('PRAGMA foreign_key_list(users)')->fetchAll() as $fk) {
            if (($fk['from'] ?? '') === 'site_id') {
                $siteFk = $fk;
            }
        }
        $this->assertNotNull($siteFk, 'FK site_id doit être préservée');
        $this->assertSame('sites', $siteFk['table']);
        $this->assertSame('id', $siteFk['to']);
        $pdo->exec('PRAGMA foreign_keys = ON');
        try {
            $pdo->exec("INSERT INTO users (username, nom, prenom, email, role, site_id) VALUES ('u.fk', 'F', 'K', 'u.fk@dreets-bfc.gouv.fr', 'agent', 999)");
            $this->fail('FK sites doit être appliquée : site_id=999 doit être rejeté');
        } catch (PDOException) {
            // attendu
        } finally {
            $pdo->exec('PRAGMA foreign_keys = OFF');
        }
        $this->assertSame([], $pdo->query('PRAGMA foreign_key_check(users)')->fetchAll(), 'aucune FK orpheline après rebuild');

        // AUTOINCREMENT préservé : sqlite_sequence suit le max historique (id 13).
        $seq = $pdo->query("SELECT seq FROM sqlite_sequence WHERE name = 'users'")->fetchColumn();
        $this->assertSame(13, (int) $seq, 'sqlite_sequence.users doit suivre le max historique');
        $pdo->exec("INSERT INTO users (username, nom, prenom, email, role) VALUES ('u.new', 'N', 'N', 'u.new@dreets-bfc.gouv.fr', 'agent')");
        $newId = (int) $pdo->query("SELECT id FROM users WHERE username = 'u.new'")->fetchColumn();
        $this->assertSame(14, $newId, 'AUTOINCREMENT : le prochain id continue au-delà de 13');

        // NOT NULL sans DEFAULT : INSERT sans email refusé (sentinelle réservée).
        try {
            $pdo->exec("INSERT INTO users (username, nom, prenom, role) VALUES ('u.noemail', 'S', 'E', 'agent')");
            $this->fail('INSERT sans email doit être refusé (NOT NULL sans DEFAULT)');
        } catch (PDOException) {
            // attendu
        }
    }

    public function testLegacyTwelveColumnMigrationIsIdempotent(): void
    {
        $pdo = $this->createLegacyUsersPdo();

        migrateUsersEmailNotNull($pdo);
        migrateUsersEmailNotNull($pdo); // 2ᵉ appel : no-op, pas d'erreur

        $this->assertSame(3, (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(), 'Aucune ligne inventée ni perdue au 2ᵉ passage');
        $this->assertSame('u.ok@dreets-bfc.gouv.fr', $this->userEmail($pdo, 'u.ok'), 'Le 2ᵉ passage ne doit pas altérer un email réel');
        $this->assertSame(\App\Repository\AnonymizationPolicy::ANONYMIZED_EMAIL, $this->userEmail($pdo, 'u.null'));
    }

    /**
     * Backfill et rebuild partagent UNE transaction : si le rebuild échoue
     * (ici, une ligne site_id=0 viole le CHECK recréé), le backfill est
     * rollBack avec le reste — jamais de backfill commité sur une table
     * non migrée (c'était l'état incohérent du défaut B1).
     */
    public function testFailedRebuildRollsBackBackfill(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Legacy 12 colonnes SANS CHECK site_id ni FK + une ligne site_id=0 :
        // injection d'échec déterministe au premier INSERT strict.
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
            sessions_invalid_before DATETIME
        )");
        $pdo->exec("INSERT INTO users (id, username, nom, prenom, email, role, site_id) VALUES
            (21, 'u.null',  'Null', 'Zéro',  NULL, 'agent', 0),
            (22, 'u.empty', 'Vide', 'Empty', '',   'agent', NULL),
            (23, 'u.ok',    'Réel', 'Real',  'u.ok@dreets-bfc.gouv.fr', 'agent', NULL)");

        $thrown = null;
        try {
            migrateUsersEmailNotNull($pdo);
        } catch (Throwable $e) {
            $thrown = $e;
        }
        $this->assertNotNull($thrown, 'Le rebuild doit crasher (INSERT strict refuse site_id=0), jamais INSERT OR IGNORE');

        $this->assertSame(3, (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(), '3/3 lignes intactes');
        $this->assertNull($this->userEmail($pdo, 'u.null'), 'Backfill ROLLBACKÉ : email NULL préservé quand le rebuild échoue');
        $this->assertSame('', $this->userEmail($pdo, 'u.empty'), 'Backfill ROLLBACKÉ : email vide préservé');
        $this->assertSame('u.ok@dreets-bfc.gouv.fr', $this->userEmail($pdo, 'u.ok'));
        $sql = (string) $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='users'")->fetchColumn();
        $this->assertStringNotContainsString("CHECK (email <> '')", $sql, 'Table legacy intacte (non migrée)');
        $this->assertFalse(
            $pdo->query("SELECT name FROM sqlite_master WHERE name = 'users_new'")->fetchColumn(),
            'Aucun users_new résiduel après rollback'
        );
    }

    /**
     * Préflight strict : un schéma users déviant (ici 11 colonnes) crash
     * AVANT toute écriture — le backfill ne doit pas être exécuté.
     */
    public function testUnexpectedUserSchemaCrashesHardWithoutBackfill(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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
            site_chosen_at TEXT
        )");
        $pdo->exec("INSERT INTO users (id, username, nom, prenom, email, role) VALUES (31, 'u.null', 'Null', 'Zéro', NULL, 'agent')");

        $thrown = null;
        try {
            migrateUsersEmailNotNull($pdo);
        } catch (Throwable $e) {
            $thrown = $e;
        }
        $this->assertInstanceOf(RuntimeException::class, $thrown, 'Schéma déviant = crash hard');
        $this->assertStringContainsString('colonnes users inattendues', $thrown->getMessage());
        $this->assertNull($this->userEmail($pdo, 'u.null'), 'Préflight AVANT toute écriture : backfill non exécuté');
    }

    public function testUserRepositoryRejectsEmptyEmail(): void
    {
        // D8 — la validation utilisateur est le seul chemin légitime ; le repo
        // refuse explicitement (crash hard) au lieu de convertir en sentinelle.
        $this->expectException(\RuntimeException::class);
        \App\Repository\UserRepository::instance()->create(
            new \App\DTO\CreateUserCommand(
                username: 'inv.repo.' . uniqid(),
                nom: 'Nom', prenom: 'Prenom', role: 'agent',
                siteId: \App\DTO\SiteId::none(),
                email: '',
            )
        );
    }
}
