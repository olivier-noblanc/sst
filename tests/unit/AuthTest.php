<?php
/**
 * Auth Unit Tests — Application SST DREETS BFC
 *
 * Tests authentication functions from src/auth.php:
 * - extractUsername()
 * - parseSuperviseurUsernames()
 * - determineProvisionRole()
 * - checkAndPromoteUser()
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/auth.php';

class AuthTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        // Clean tables in reverse FK order
        $this->pdo->exec('DELETE FROM report_access_log');
        $this->pdo->exec('DELETE FROM report_responses');
        $this->pdo->exec('DELETE FROM reports');
        $this->pdo->exec('DELETE FROM notification_settings');
        $this->pdo->exec('DELETE FROM users');
        $this->pdo->exec('DELETE FROM sites');
        $this->pdo->exec('DELETE FROM config_app');
        clearConfigCache();
    }

    // ─── extractUsername ───────────────────────────────────────────────────

    public function testExtractUsernameWithDomainBackslashFormat(): void
    {
        $this->assertEquals('jean.martin', extractUsername('DREETS-BFC\jean.martin'));
    }

    public function testExtractUsernameWithDomainBackslashUppercase(): void
    {
        $this->assertEquals('admin.super', extractUsername('DREETS-BFC\ADMIN.SUPER'));
    }

    public function testExtractUsernameWithAtFormat(): void
    {
        $this->assertEquals('jean.martin', extractUsername('jean.martin@dreets-bfc.gouv.fr'));
    }

    public function testExtractUsernameWithAtFormatUppercase(): void
    {
        $this->assertEquals('jean.martin', extractUsername('JEAN.MARTIN@DREETS-BFC.GOUV.FR'));
    }

    public function testExtractUsernamePlainUsername(): void
    {
        $this->assertEquals('jean.martin', extractUsername('jean.martin'));
    }

    public function testExtractUsernamePlainUppercase(): void
    {
        $this->assertEquals('admin.super', extractUsername('ADMIN.SUPER'));
    }

    public function testExtractUsernameEmptyString(): void
    {
        $this->assertEquals('', extractUsername(''));
    }

    public function testExtractUsernameWhitespaceOnly(): void
    {
        $this->assertEquals('', extractUsername('   '));
    }

    public function testExtractUsernameWithLeadingTrailingWhitespace(): void
    {
        $this->assertEquals('jean.martin', extractUsername('  jean.martin  '));
    }

    public function testExtractUsernameWithBackslashAndWhitespace(): void
    {
        $this->assertEquals('jean.martin', extractUsername('DOMAIN\  jean.martin  '));
    }

    // ─── parseSuperviseurUsernames ──────────────────────────────────────────

    public function testParseSuperviseurUsernamesCommaSeparated(): void
    {
        $result = parseSuperviseurUsernames('jean.martin, sophie.dupont, admin.super');
        $this->assertEquals(['jean.martin', 'sophie.dupont', 'admin.super'], $result);
    }

    public function testParseSuperviseurUsernamesSingleEntry(): void
    {
        $result = parseSuperviseurUsernames('jean.martin');
        $this->assertEquals(['jean.martin'], $result);
    }

    public function testParseSuperviseurUsernamesEmpty(): void
    {
        $result = parseSuperviseurUsernames('');
        $this->assertEquals([''], $result);
    }

    public function testParseSuperviseurUsernamesWhitespacePadding(): void
    {
        $result = parseSuperviseurUsernames('  jean.martin  ,  sophie.dupont  ');
        $this->assertEquals(['jean.martin', 'sophie.dupont'], $result);
    }

    public function testParseSuperviseurUsernamesLowercased(): void
    {
        $result = parseSuperviseurUsernames('JEAN.MARTIN, SOPHIE.DUPONT');
        $this->assertEquals(['jean.martin', 'sophie.dupont'], $result);
    }

    public function testParseSuperviseurUsernamesTrailingComma(): void
    {
        $result = parseSuperviseurUsernames('jean.martin,');
        $this->assertEquals(['jean.martin', ''], $result);
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
        // No config row set — getConfig returns default ''
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

        // Should remain superviseur (not double-promoted, no error)
        $this->assertEquals('superviseur', $result['role']);
    }

    public function testCheckAndPromoteChsctNotPromoted(): void
    {
        updateConfig($this->pdo, 'app_superviseur_usernames', 'jean.martin');
        clearConfigCache();

        $user = ['id' => 1, 'role' => 'chsct'];
        $result = checkAndPromoteUser($this->pdo, $user, 'jean.martin');

        // CHSCT should NOT be promoted to superviseur
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

        // Verify the database was updated
        $stmt = $this->pdo->prepare('SELECT role FROM users WHERE id = :id');
        $stmt->execute([':id' => 1]);
        $dbRole = $stmt->fetchColumn();
        $this->assertEquals('superviseur', $dbRole);
    }
}
