<?php
/**
 * Validation Unit Tests — User Fields & Last Active Superviseur
 *
 * Tests validation functions from src/validation.php and src/validation_user.php:
 * - validateUserFields()
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/validation.php';
require_once __DIR__ . '/../../src/validation_user.php';

class ValidationUserTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        $this->pdo->exec('DELETE FROM report_access_log');
        $this->pdo->exec('DELETE FROM report_responses');
        $this->pdo->exec('DELETE FROM reports');
        $this->pdo->exec('DELETE FROM notification_settings');
        $this->pdo->exec('DELETE FROM users');
        $this->pdo->exec('DELETE FROM sites');
        $this->pdo->exec('DELETE FROM config_app');

        // Reset ConfigService static caches so each test starts clean
        (new \App\Services\ConfigService())->clearCache();

        // Seed an active site so isNoSiteMode() returns false
        $this->pdo->exec("INSERT INTO sites (code, nom, is_active) VALUES ('UR01', 'Site Test', 1)");
    }

    // ─── validateUserFields (DB-dependent) ──────────────────────────────────

    public function testValidUserFieldsNoErrors(): void
    {
        $siteId = createSite($this->pdo, 'UR21', 'UR Test', 'Test');
        $errors = validateUserFields($this->pdo, [
            'nom' => 'Dupont', 'prenom' => 'Marie', 'username' => 'marie.dupont',
            'role' => 'agent', 'site_id' => $siteId, 'email' => 'marie@test.gouv.fr',
        ]);
        $this->assertEmpty($errors);
    }

    public function testValidateUserFieldsMissingNom(): void
    {
        $siteId = createSite($this->pdo, 'UR21', 'UR Test', 'Test');
        $errors = validateUserFields($this->pdo, [
            'nom' => '', 'prenom' => 'Marie', 'username' => 'marie.dupont',
            'role' => 'agent', 'site_id' => $siteId, 'email' => '',
        ]);
        $this->assertArrayHasKey('nom', $errors);
    }

    public function testValidateUserFieldsInvalidRole(): void
    {
        $siteId = createSite($this->pdo, 'UR21', 'UR Test', 'Test');
        $errors = validateUserFields($this->pdo, [
            'nom' => 'Dupont', 'prenom' => 'Marie', 'username' => 'marie.dupont',
            'role' => 'admin', 'site_id' => $siteId, 'email' => '',
        ]);
        $this->assertArrayHasKey('role', $errors);
    }

    public function testValidateUserFieldsInvalidSite(): void
    {
        $errors = validateUserFields($this->pdo, [
            'nom' => 'Dupont', 'prenom' => 'Marie', 'username' => 'marie.dupont',
            'role' => 'agent', 'site_id' => 99999, 'email' => '',
        ]);
        $this->assertArrayHasKey('site_id', $errors);
    }

    public function testValidateUserFieldsInvalidEmail(): void
    {
        $siteId = createSite($this->pdo, 'UR21', 'UR Test', 'Test');
        $errors = validateUserFields($this->pdo, [
            'nom' => 'Dupont', 'prenom' => 'Marie', 'username' => 'marie.dupont',
            'role' => 'agent', 'site_id' => $siteId, 'email' => 'not-an-email',
        ]);
        $this->assertArrayHasKey('email', $errors);
    }

    public function testValidateUserFieldsDuplicateUsername(): void
    {
        $siteId = createSite($this->pdo, 'UR21', 'UR Test', 'Test');
        \App\Repository\UserRepository::instance()->create([
            'nom' => 'Existing', 'prenom' => 'User', 'username' => 'existing.user',
            'role' => 'agent', 'site_id' => $siteId, 'email' => '',
        ]);
        $errors = validateUserFields($this->pdo, [
            'nom' => 'Dupont', 'prenom' => 'Marie', 'username' => 'existing.user',
            'role' => 'agent', 'site_id' => $siteId, 'email' => '',
        ]);
        $this->assertArrayHasKey('username', $errors);
    }

}
