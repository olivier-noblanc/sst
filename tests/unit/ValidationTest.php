<?php
/**
 * Validation Unit Tests — Report Fields, RAMI, Pour Compte, Visibility
 *
 * Tests validation functions from src/validation.php:
 * - validateReportFields()
 * - validateRamiFields()
 * - validatePourCompte()
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/validation.php';

class ValidationTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        cleanupAllForTest($this->pdo);
        $this->pdo->exec('DELETE FROM notification_settings');
        $this->pdo->exec('DELETE FROM sites');
        $this->pdo->exec('DELETE FROM config_app');

        // Audit #85 — validateRamiFields() lit les valeurs permises via
        // getRegistryFieldKeys() -> registry_fields en DB, pas une whitelist
        // codée en dur. Sans ce seed explicite (même contenu que
        // seed/_registries.php), le résultat dépendait de l'état laissé par
        // un autre test exécuté avant, selon l'ordre. clearRegistryFieldCache()
        // d'abord : sans ça, un résultat vide mis en cache par un test
        // antérieur (avant que ce seed ne tourne) resterait figé.
        clearRegistryFieldCache();
        $registryRepo = \App\Repository\RegistryRepository::instance();
        $registryRepo->seedDefaults();
        $rami = $registryRepo->findByCode('rami');
        if ($rami !== null) {
            $fieldRepo = \App\Repository\RegistryFieldRepository::instance();
            if ($fieldRepo->findByCode((int) $rami['id'], 'nature_auteur') === null) {
                $fieldRepo->create((int) $rami['id'], [
                    'field_code' => 'nature_auteur',
                    'label' => 'Nature de l\'auteur',
                    'field_type' => 'select',
                    'options' => json_encode(['usager' => 'Usager', 'collegue' => 'Collègue', 'hierarchie' => 'Hiérarchie', 'tiers' => 'Tiers']),
                    'is_required' => 0,
                    'sort_order' => 1,
                ]);
            }
            if ($fieldRepo->findByCode((int) $rami['id'], 'type_acte') === null) {
                $fieldRepo->create((int) $rami['id'], [
                    'field_code' => 'type_acte',
                    'label' => 'Type d\'acte',
                    'field_type' => 'select',
                    'options' => json_encode(['verbal' => 'Verbal', 'physique' => 'Physique', 'moral' => 'Moral', 'sexiste' => 'Sexiste', 'autre' => 'Autre']),
                    'is_required' => 0,
                    'sort_order' => 2,
                ]);
            }
        }
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

}
