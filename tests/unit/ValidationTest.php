<?php
/**
 * Validation Unit Tests — Application SST DREETS BFC
 *
 * Tests validation functions from src/validation.php:
 * - validateReportFields()
 * - validateRamiFields()
 * - validatePourCompte()
 * - enforceReportVisibility()
 * - validateUserFields()
 * - isLastActiveSuperviseur()
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/validation.php';

class ValidationTest extends TestCase
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
    }

    // ─── validateReportFields ───────────────────────────────────────────────

    public function testValidReportFieldsNoErrors(): void
    {
        $errors = validateReportFields('2025-06-15', 'Objet test', 'Description test', 'Bureau 12', '14:30');
        $this->assertEmpty($errors);
    }

    public function testValidReportFieldsWithoutOptionalFields(): void
    {
        $errors = validateReportFields('2025-06-15', 'Objet test', 'Description test', '', '');
        $this->assertEmpty($errors);
    }

    public function testEmptyDateEvenement(): void
    {
        $errors = validateReportFields('', 'Objet test', 'Description test', '', '');
        $this->assertArrayHasKey('date_evenement', $errors);
    }

    public function testInvalidDateFormat(): void
    {
        $errors = validateReportFields('15/06/2025', 'Objet test', 'Description test', '', '');
        $this->assertArrayHasKey('date_evenement', $errors);
    }

    public function testFutureDateRejected(): void
    {
        $errors = validateReportFields('2099-12-31', 'Objet test', 'Description test', '', '');
        $this->assertArrayHasKey('date_evenement', $errors);
    }

    public function testEmptyObjet(): void
    {
        $errors = validateReportFields('2025-06-15', '', 'Description test', '', '');
        $this->assertArrayHasKey('objet', $errors);
    }

    public function testObjetTooLong(): void
    {
        $errors = validateReportFields('2025-06-15', str_repeat('x', MAX_OBJECT_LENGTH + 1), 'Description test', '', '');
        $this->assertArrayHasKey('objet', $errors);
    }

    public function testObjetAtMaxLengthAllowed(): void
    {
        $errors = validateReportFields('2025-06-15', str_repeat('x', MAX_OBJECT_LENGTH), 'Description test', '', '');
        $this->assertArrayNotHasKey('objet', $errors);
    }

    public function testEmptyDescription(): void
    {
        $errors = validateReportFields('2025-06-15', 'Objet test', '', '', '');
        $this->assertArrayHasKey('description', $errors);
    }

    public function testDescriptionTooLong(): void
    {
        $errors = validateReportFields('2025-06-15', 'Objet test', str_repeat('x', MAX_DESCRIPTION_LENGTH + 1), '', '');
        $this->assertArrayHasKey('description', $errors);
    }

    public function testLieuTooLong(): void
    {
        $errors = validateReportFields('2025-06-15', 'Objet test', 'Description test', str_repeat('x', MAX_LIEU_LENGTH + 1), '');
        $this->assertArrayHasKey('lieu', $errors);
    }

    public function testInvalidHeureFormat(): void
    {
        $errors = validateReportFields('2025-06-15', 'Objet test', 'Description test', '', 'not-a-time');
        $this->assertArrayHasKey('heure_evenement', $errors);
    }

    public function testHeureWithSecondsRejected(): void
    {
        $errors = validateReportFields('2025-06-15', 'Objet test', 'Description test', '', '14:30:00');
        $this->assertArrayHasKey('heure_evenement', $errors);
    }

    public function testValidHeureFormat(): void
    {
        $errors = validateReportFields('2025-06-15', 'Objet test', 'Description test', '', '14:30');
        $this->assertArrayNotHasKey('heure_evenement', $errors);
    }

    public function testMultipleErrorsAtOnce(): void
    {
        $errors = validateReportFields('', '', '', str_repeat('x', 500), 'invalid');
        $this->assertArrayHasKey('date_evenement', $errors);
        $this->assertArrayHasKey('objet', $errors);
        $this->assertArrayHasKey('description', $errors);
        $this->assertArrayHasKey('lieu', $errors);
        $this->assertArrayHasKey('heure_evenement', $errors);
    }

    // ─── validateRamiFields ─────────────────────────────────────────────────

    public function testValidRamiFields(): void
    {
        $result = validateRamiFields('usager', 'verbal');
        $this->assertEquals('usager', $result['nature_auteur']);
        $this->assertEquals('verbal', $result['type_acte']);
    }

    public function testEmptyRamiFieldsAreAllowed(): void
    {
        $result = validateRamiFields('', '');
        $this->assertEquals('', $result['nature_auteur']);
        $this->assertEquals('', $result['type_acte']);
    }

    public function testInvalidNatureAuteurIsCleared(): void
    {
        $result = validateRamiFields('hacker', 'verbal');
        $this->assertEquals('', $result['nature_auteur']);
    }

    public function testInvalidTypeActeIsCleared(): void
    {
        $result = validateRamiFields('usager', 'nuclear');
        $this->assertEquals('', $result['type_acte']);
    }

    public function testAllValidNatureAuteurs(): void
    {
        foreach (['usager', 'collegue', 'hierarchie', 'tiers'] as $nature) {
            $result = validateRamiFields($nature, '');
            $this->assertEquals($nature, $result['nature_auteur'], "Failed for nature_auteur=$nature");
        }
    }

    public function testAllValidTypeActes(): void
    {
        foreach (['verbal', 'physique', 'moral', 'sexiste', 'autre'] as $type) {
            $result = validateRamiFields('', $type);
            $this->assertEquals($type, $result['type_acte'], "Failed for type_acte=$type");
        }
    }

    // ─── validatePourCompte ─────────────────────────────────────────────────

    public function testNotPourCompteNoErrors(): void
    {
        $errors = validatePourCompte(false, '', '');
        $this->assertEmpty($errors);
    }

    public function testPourCompteWithBothNamesNoErrors(): void
    {
        $errors = validatePourCompte(true, 'Dupont', 'Marie');
        $this->assertEmpty($errors);
    }

    public function testPourCompteMissingNom(): void
    {
        $errors = validatePourCompte(true, '', 'Marie');
        $this->assertArrayHasKey('pour_compte_nom', $errors);
    }

    public function testPourCompteMissingPrenom(): void
    {
        $errors = validatePourCompte(true, 'Dupont', '');
        $this->assertArrayHasKey('pour_compte_prenom', $errors);
    }

    public function testPourCompteNomTooLong(): void
    {
        $errors = validatePourCompte(true, str_repeat('x', 101), 'Marie');
        $this->assertArrayHasKey('pour_compte_nom', $errors);
    }

    public function testPourComptePrenomTooLong(): void
    {
        $errors = validatePourCompte(true, 'Dupont', str_repeat('x', 101));
        $this->assertArrayHasKey('pour_compte_prenom', $errors);
    }

    // ─── enforceReportVisibility ────────────────────────────────────────────

    public function testEnforceVisibilityPublicForcesPublic(): void
    {
        // Need to mock the visibility mode — this test uses the $forcedVisibility parameter
        // Since enforceReportVisibility() calls reportVisibilityIsPublic() which reads config,
        // we test via the config system
        updateConfig($this->pdo, 'app_report_visibility', 'public');
        clearConfigCache();
        $this->assertEquals(0, enforceReportVisibility(1));
    }

    public function testEnforceVisibilityConfidentialForcesConfidential(): void
    {
        updateConfig($this->pdo, 'app_report_visibility', 'confidential');
        clearConfigCache();
        $this->assertEquals(1, enforceReportVisibility(0));
    }

    public function testEnforceVisibilityAgentChoiceKeepsSelection(): void
    {
        updateConfig($this->pdo, 'app_report_visibility', 'agent_choice');
        clearConfigCache();
        $this->assertEquals(0, enforceReportVisibility(0));
        $this->assertEquals(1, enforceReportVisibility(1));
    }

    // ─── validateUserFields (DB-dependent) ──────────────────────────────────

    public function testValidUserFieldsNoErrors(): void
    {
        $siteId = createSite($this->pdo, 'UR21', 'UR Test', 'Test');

        $errors = validateUserFields($this->pdo, [
            'nom' => 'Dupont',
            'prenom' => 'Marie',
            'username' => 'marie.dupont',
            'role' => 'agent',
            'site_id' => $siteId,
            'email' => 'marie@test.gouv.fr',
        ]);

        $this->assertEmpty($errors);
    }

    public function testValidateUserFieldsMissingNom(): void
    {
        $siteId = createSite($this->pdo, 'UR21', 'UR Test', 'Test');

        $errors = validateUserFields($this->pdo, [
            'nom' => '',
            'prenom' => 'Marie',
            'username' => 'marie.dupont',
            'role' => 'agent',
            'site_id' => $siteId,
            'email' => '',
        ]);

        $this->assertArrayHasKey('nom', $errors);
    }

    public function testValidateUserFieldsInvalidRole(): void
    {
        $siteId = createSite($this->pdo, 'UR21', 'UR Test', 'Test');

        $errors = validateUserFields($this->pdo, [
            'nom' => 'Dupont',
            'prenom' => 'Marie',
            'username' => 'marie.dupont',
            'role' => 'admin',
            'site_id' => $siteId,
            'email' => '',
        ]);

        $this->assertArrayHasKey('role', $errors);
    }

    public function testValidateUserFieldsInvalidSite(): void
    {
        $errors = validateUserFields($this->pdo, [
            'nom' => 'Dupont',
            'prenom' => 'Marie',
            'username' => 'marie.dupont',
            'role' => 'agent',
            'site_id' => 99999,
            'email' => '',
        ]);

        $this->assertArrayHasKey('site_id', $errors);
    }

    public function testValidateUserFieldsInvalidEmail(): void
    {
        $siteId = createSite($this->pdo, 'UR21', 'UR Test', 'Test');

        $errors = validateUserFields($this->pdo, [
            'nom' => 'Dupont',
            'prenom' => 'Marie',
            'username' => 'marie.dupont',
            'role' => 'agent',
            'site_id' => $siteId,
            'email' => 'not-an-email',
        ]);

        $this->assertArrayHasKey('email', $errors);
    }

    public function testValidateUserFieldsDuplicateUsername(): void
    {
        $siteId = createSite($this->pdo, 'UR21', 'UR Test', 'Test');
        createUser($this->pdo, [
            'nom' => 'Existing', 'prenom' => 'User', 'username' => 'existing.user',
            'role' => 'agent', 'site_id' => $siteId, 'email' => '',
        ]);

        $errors = validateUserFields($this->pdo, [
            'nom' => 'Dupont',
            'prenom' => 'Marie',
            'username' => 'existing.user',
            'role' => 'agent',
            'site_id' => $siteId,
            'email' => '',
        ]);

        $this->assertArrayHasKey('username', $errors);
    }

    // ─── isLastActiveSuperviseur (DB-dependent) ─────────────────────────────

    public function testIsLastActiveSuperviseurWhenNoSuperviseur(): void
    {
        $this->assertTrue(isLastActiveSuperviseur($this->pdo));
    }

    public function testIsLastActiveSuperviseurWhenOneSuperviseur(): void
    {
        $siteId = createSite($this->pdo, 'UR21', 'UR Test', 'Test');
        createUser($this->pdo, [
            'nom' => 'Admin', 'prenom' => 'Super', 'username' => 'admin.test',
            'role' => 'superviseur', 'site_id' => $siteId, 'email' => '',
        ]);

        $this->assertTrue(isLastActiveSuperviseur($this->pdo));
    }

    public function testIsNotLastActiveSuperviseurWhenTwo(): void
    {
        $siteId = createSite($this->pdo, 'UR21', 'UR Test', 'Test');
        createUser($this->pdo, [
            'nom' => 'Admin1', 'prenom' => 'Super', 'username' => 'admin1.test',
            'role' => 'superviseur', 'site_id' => $siteId, 'email' => '',
        ]);
        createUser($this->pdo, [
            'nom' => 'Admin2', 'prenom' => 'Super', 'username' => 'admin2.test',
            'role' => 'superviseur', 'site_id' => $siteId, 'email' => '',
        ]);

        $this->assertFalse(isLastActiveSuperviseur($this->pdo));
    }
}
