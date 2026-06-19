<?php
/**
 * Auth Unit Tests — Provision Role & Promotion
 *
 * Tests authentication functions from src/auth.php:
 * - determineProvisionRole()
 * - checkAndPromoteUser()
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/auth.php';

class AuthProvisionTest extends TestCase
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
        clearConfigCache();
    }

    // ─── determineProvisionRole ─────────────────────────────────────────────

    public function testDetermineProvisionRoleWithMatchingUsername(): void
    {
        updateConfig($this->pdo, 'app_superviseur_usernames', 'jean.martin, admin.super');
        clearConfigCache();
        $this->assertEquals('superviseur', determineProvisionRole($this->pdo, 'jean.martin'));
    }

    public function testDetermineProvisionRoleWithNonMatchingUsername(): void
    {
        updateConfig($this->pdo, 'app_superviseur_usernames', 'admin.super');
        clearConfigCache();
        $this->assertEquals('agent', determineProvisionRole($this->pdo, 'jean.martin'));
    }

    public function testDetermineProvisionRoleWithEmptyConfig(): void
    {
        updateConfig($this->pdo, 'app_superviseur_usernames', '');
        clearConfigCache();
        $this->assertEquals('agent', determineProvisionRole($this->pdo, 'jean.martin'));
    }

    public function testDetermineProvisionRoleWithNoConfigRow(): void
    {
        clearConfigCache();
        $this->assertEquals('agent', determineProvisionRole($this->pdo, 'jean.martin'));
    }

    public function testDetermineProvisionRoleCaseInsensitive(): void
    {
        updateConfig($this->pdo, 'app_superviseur_usernames', 'Jean.Martin, Admin.Super');
        clearConfigCache();
        $this->assertEquals('superviseur', determineProvisionRole($this->pdo, 'jean.martin'));
        $this->assertEquals('superviseur', determineProvisionRole($this->pdo, 'JEAN.MARTIN'));
    }

    // ─── checkAndPromoteUser ────────────────────────────────────────────────

    public function testCheckAndPromoteAgentToSuperviseur(): void
    {
        updateConfig($this->pdo, 'app_superviseur_usernames', 'jean.martin');
        clearConfigCache();
        $user = ['id' => 1, 'role' => 'agent'];
        $result = checkAndPromoteUser($this->pdo, $user, 'jean.martin');
        $this->assertEquals('superviseur', $result['role']);
    }

    public function testCheckAndPromoteAgentNotInList(): void
    {
        updateConfig($this->pdo, 'app_superviseur_usernames', 'admin.super');
        clearConfigCache();
        $user = ['id' => 1, 'role' => 'agent'];
        $result = checkAndPromoteUser($this->pdo, $user, 'jean.martin');
        $this->assertEquals('agent', $result['role']);
    }

    public function testCheckAndPromoteAlreadySuperviseur(): void
    {
        updateConfig($this->pdo, 'app_superviseur_usernames', 'jean.martin');
        clearConfigCache();
        $user = ['id' => 1, 'role' => 'superviseur'];
        $result = checkAndPromoteUser($this->pdo, $user, 'jean.martin');
        $this->assertEquals('superviseur', $result['role']);
    }

    public function testCheckAndPromoteChsctNotPromoted(): void
    {
        updateConfig($this->pdo, 'app_superviseur_usernames', 'jean.martin');
        clearConfigCache();
        $user = ['id' => 1, 'role' => 'chsct'];
        $result = checkAndPromoteUser($this->pdo, $user, 'jean.martin');
        $this->assertEquals('chsct', $result['role']);
    }

    public function testCheckAndPromoteWithEmptyConfig(): void
    {
        updateConfig($this->pdo, 'app_superviseur_usernames', '');
        clearConfigCache();
        $user = ['id' => 1, 'role' => 'agent'];
        $result = checkAndPromoteUser($this->pdo, $user, 'jean.martin');
        $this->assertEquals('agent', $result['role']);
    }

    public function testCheckAndPromoteCaseInsensitive(): void
    {
        updateConfig($this->pdo, 'app_superviseur_usernames', 'Jean.Martin');
        clearConfigCache();
        $user = ['id' => 1, 'role' => 'agent'];
        $result = checkAndPromoteUser($this->pdo, $user, 'jean.martin');
        $this->assertEquals('superviseur', $result['role']);
    }

    public function testCheckAndPromoteUpdatesDatabase(): void
    {
        $siteId = createSite($this->pdo, 'UR21', 'UR Test', 'Test');
        createUser($this->pdo, [
            'nom' => 'Martin', 'prenom' => 'Jean', 'username' => 'jean.martin',
            'role' => 'agent', 'site_id' => $siteId, 'email' => '',
        ]);
        updateConfig($this->pdo, 'app_superviseur_usernames', 'jean.martin');
        clearConfigCache();
        $user = ['id' => 1, 'role' => 'agent'];
        $result = checkAndPromoteUser($this->pdo, $user, 'jean.martin');
        $this->assertEquals('superviseur', $result['role']);
        $stmt = $this->pdo->prepare('SELECT role FROM users WHERE id = :id');
        $stmt->execute([':id' => 1]);
        $dbRole = $stmt->fetchColumn();
        $this->assertEquals('superviseur', $dbRole);
    }
}
