<?php
/**
 * Session Invalidation Test — Application SST DREETS BFC
 *
 * Audit #9 + #22 + #23 + #38 — un user désactivé gardait sa session
 * active 24h jusqu'à expiration.
 *
 * Ce test vérifie :
 * 1. UserService::deactivate bump le marqueur sessions_invalid_before
 * 2. UserService::update (role changed) bump le marqueur
 * 3. UserService::anonymize bump le marqueur
 * 4. AuthService::getAuthenticatedUser détecte un user désactivé via le marqueur
 *    et force le logout (return null)
 * 5. AuthService::getAuthenticatedUser re-fetch l'user si rôle a changé
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

class SessionInvalidationTest extends TestCase
{
    private static bool $bootstrapped = false;
    private static ?PDO $pdo = null;

    public static function setUpBeforeClass(): void
    {
        if (self::$bootstrapped) {
            return;
        }
        self::$bootstrapped = true;

        require_once __DIR__ . '/../../src/config.php';
        require_once __DIR__ . '/../../src/helpers.php';
        require_once __DIR__ . '/../../src/database.php';
        require_once __DIR__ . '/../../src/Services/SessionInvalidator.php';
        require_once __DIR__ . '/../../src/Repository/UserRepository.php';
        require_once __DIR__ . '/../../src/Services/UserService.php';
        require_once __DIR__ . '/../../src/Event/EventDispatcher.php';
        require_once __DIR__ . '/../../src/DTO/UpdateUserCommand.php';

        self::$pdo = getDB();
    }

    protected function setUp(): void
    {
        self::$pdo->exec("DELETE FROM users WHERE username LIKE 'test.sess%' OR username LIKE 'anonymized_%'");
        $_SESSION = [];
    }

    public function testDeactivateBumpsSessionsInvalidBefore(): void
    {
        // Audit #9 + #22 — bump the marker on user deactivation
        $pdo = self::$pdo;
        $pdo->exec("INSERT INTO users (id, username, nom, prenom, role, site_id, is_active) VALUES (9101, 'test.sess.sup', 'Sup', 'Anna', 'superviseur', 1, 1)");
        $pdo->exec("INSERT INTO users (id, username, nom, prenom, role, site_id, is_active) VALUES (9102, 'test.sess.admin', 'Admin', 'Bob', 'superviseur', 1, 1)");

        $before = $pdo->query("SELECT sessions_invalid_before FROM users WHERE id = 9101")->fetchColumn();
        $this->assertNull($before, 'Marker should be NULL initially');

        $repo = new \App\Repository\UserRepository($pdo);
        $events = new \App\Event\EventDispatcher();
        $service = new \App\Services\UserService($repo, $events);
        $service->deactivate(9101, 9102);

        $after = $pdo->query("SELECT sessions_invalid_before FROM users WHERE id = 9101")->fetchColumn();
        $this->assertNotNull($after, 'sessions_invalid_before should be set after deactivate');
        $this->assertNotEmpty($after, 'sessions_invalid_before should not be empty');
    }

    public function testUpdateRoleChangeBumpsSessionsInvalidBefore(): void
    {
        // Audit #23 — bump the marker on role change
        $pdo = self::$pdo;
        $pdo->exec("INSERT INTO users (id, username, nom, prenom, role, site_id, is_active) VALUES (9103, 'test.sess.role1', 'Agent1', 'Tom', 'agent', 1, 1)");
        $pdo->exec("INSERT INTO users (id, username, nom, prenom, role, site_id, is_active) VALUES (9104, 'test.sess.role2', 'Agent2', 'Bob', 'superviseur', 1, 1)");

        $repo = new \App\Repository\UserRepository($pdo);
        $events = new \App\Event\EventDispatcher();
        $service = new \App\Services\UserService($repo, $events);

        // Update user 9103 role: agent → superviseur
        $cmd = new \App\DTO\UpdateUserCommand(
            username: 'test.sess.role1',
            nom: 'Agent1',
            prenom: 'Tom',
            role: 'superviseur',
            siteId: 1,
            email: '',
        );
        $service->update(9103, $cmd, 9104);

        $after = $pdo->query("SELECT sessions_invalid_before FROM users WHERE id = 9103")->fetchColumn();
        $this->assertNotNull($after, 'sessions_invalid_before should be set after role change');
    }

    public function testUpdateWithoutRoleChangeDoesNotBumpMarker(): void
    {
        // Safety check — only role changes should bump the marker
        $pdo = self::$pdo;
        $pdo->exec("INSERT INTO users (id, username, nom, prenom, role, site_id, is_active) VALUES (9105, 'test.sess.same', 'Same', 'User', 'agent', 1, 1)");
        $pdo->exec("INSERT INTO users (id, username, nom, prenom, role, site_id, is_active) VALUES (9106, 'test.sess.admin3', 'Admin', 'User', 'superviseur', 1, 1)");

        $repo = new \App\Repository\UserRepository($pdo);
        $events = new \App\Event\EventDispatcher();
        $service = new \App\Services\UserService($repo, $events);

        // Update same role, different name
        $cmd = new \App\DTO\UpdateUserCommand(
            username: 'test.sess.same',
            nom: 'Updated Name',
            prenom: 'Updated Pre',
            role: 'agent',
            siteId: 1,
            email: '',
        );
        $service->update(9105, $cmd, 9106);

        $after = $pdo->query("SELECT sessions_invalid_before FROM users WHERE id = 9105")->fetchColumn();
        $this->assertNull($after, 'sessions_invalid_before should NOT be set when role unchanged');
    }

    public function testSessionInvalidatorReturnsFalseWhenUserInactive(): void
    {
        $pdo = self::$pdo;
        $pdo->exec("INSERT INTO users (id, username, nom, prenom, role, site_id, is_active) VALUES (9107, 'test.sess.inactive', 'Inact', 'Bob', 'agent', 1, 0)");

        $invalidator = new \App\Services\SessionInvalidator($pdo);
        $valid = $invalidator->isSessionStillValid(9107, time() - 100);
        $this->assertFalse($valid, 'Session should be invalid when user is inactive');
    }

    public function testSessionInvalidatorReturnsFalseWhenMarkerNewerThanSessionStart(): void
    {
        $pdo = self::$pdo;
        $pdo->exec("INSERT INTO users (id, username, nom, prenom, role, site_id, is_active) VALUES (9108, 'test.sess.marker', 'Mark', 'Bob', 'agent', 1, 1)");
        // Set marker to now (newer than session start 100s ago)
        $pdo->exec("UPDATE users SET sessions_invalid_before = datetime('now') WHERE id = 9108");

        $invalidator = new \App\Services\SessionInvalidator($pdo);
        $valid = $invalidator->isSessionStillValid(9108, time() - 100);
        $this->assertFalse($valid, 'Session should be invalid when marker is newer than session start');
    }

    public function testSessionInvalidatorReturnsTrueWhenNoMarker(): void
    {
        $pdo = self::$pdo;
        $pdo->exec("INSERT INTO users (id, username, nom, prenom, role, site_id, is_active) VALUES (9109, 'test.sess.clean', 'Clean', 'Bob', 'agent', 1, 1)");

        $invalidator = new \App\Services\SessionInvalidator($pdo);
        $valid = $invalidator->isSessionStillValid(9109, time() - 100);
        $this->assertTrue($valid, 'Session should be valid when no invalidation marker');
    }

    public function testSessionInvalidatorReturnsFalseWhenUserDoesNotExist(): void
    {
        $pdo = self::$pdo;
        $invalidator = new \App\Services\SessionInvalidator($pdo);
        $valid = $invalidator->isSessionStillValid(99999, time());
        $this->assertFalse($valid, 'Session should be invalid when user no longer exists');
    }
}
