<?php
/**
 * RegistryPolicy Test — Application SST DREETS BFC
 *
 * Modular-audit P2.1 — Tests pour RegistryPolicy (3 méthodes métier).
 *
 * Vérifie :
 * 1. requiresPourCompte() — RAMI retourne true, RSST/DGI false
 * 2. hasDgiWarningPanel() — DGI retourne true, RSST/RAMI false
 * 3. getLieuLabel() — DGI retourne 'Lieu / Mesures de protection', autres 'Lieu'
 * 4. Custom registry avec flags DB — respecte les flags
 * 5. Pre-migration (colonnes absentes) — fail safe
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

class RegistryPolicyTest extends TestCase
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
        require_once __DIR__ . '/../../src/Services/RegistryPolicy.php';
        require_once __DIR__ . '/../../src/Repository/RegistryRepository.php';

        self::$pdo = getDB();
    }

    protected function setUp(): void
    {
        // Clean up test registries
        self::$pdo->exec("DELETE FROM registries WHERE code LIKE 'test.%'");
    }

    public function testRequiresPourCompteForRami(): void
    {
        $policy = new \App\Services\RegistryPolicy();
        $this->assertTrue($policy->requiresPourCompte(\App\Enum\ReportType::Rami->value));
    }

    public function testRequiresPourCompteForRsst(): void
    {
        $policy = new \App\Services\RegistryPolicy();
        $this->assertFalse($policy->requiresPourCompte(\App\Enum\ReportType::Rsst->value));
    }

    public function testRequiresPourCompteForDgi(): void
    {
        $policy = new \App\Services\RegistryPolicy();
        $this->assertFalse($policy->requiresPourCompte(\App\Enum\ReportType::Dgi->value));
    }

    public function testHasDgiWarningPanelForDgi(): void
    {
        $policy = new \App\Services\RegistryPolicy();
        $this->assertTrue($policy->hasDgiWarningPanel(\App\Enum\ReportType::Dgi->value));
    }

    public function testHasDgiWarningPanelForRsst(): void
    {
        $policy = new \App\Services\RegistryPolicy();
        $this->assertFalse($policy->hasDgiWarningPanel(\App\Enum\ReportType::Rsst->value));
    }

    public function testHasDgiWarningPanelForRami(): void
    {
        $policy = new \App\Services\RegistryPolicy();
        $this->assertFalse($policy->hasDgiWarningPanel(\App\Enum\ReportType::Rami->value));
    }

    public function testGetLieuLabelForDgi(): void
    {
        $policy = new \App\Services\RegistryPolicy();
        $this->assertSame('Lieu / Mesures de protection', $policy->getLieuLabel(\App\Enum\ReportType::Dgi->value));
    }

    public function testGetLieuLabelForRsst(): void
    {
        $policy = new \App\Services\RegistryPolicy();
        $this->assertSame('Lieu', $policy->getLieuLabel(\App\Enum\ReportType::Rsst->value));
    }

    public function testGetLieuLabelForRami(): void
    {
        $policy = new \App\Services\RegistryPolicy();
        $this->assertSame('Lieu', $policy->getLieuLabel(\App\Enum\ReportType::Rami->value));
    }

    public function testGetLieuLabelForCustomRegistryWithoutOverride(): void
    {
        // Create a custom registry with no lieu_label_override
        $pdo = self::$pdo;
        $pdo->exec("INSERT INTO registries (code, label, short_label, icon, color_theme, is_enabled, is_system, sort_order, default_visibility) VALUES ('test.custom1', 'Test Custom', 'TC', '📋', 'vert', 1, 0, 99, 'agent_choice')");

        $policy = new \App\Services\RegistryPolicy();
        $this->assertSame('Lieu', $policy->getLieuLabel('test.custom1'));
    }

    public function testGetLieuLabelForCustomRegistryWithOverride(): void
    {
        $pdo = self::$pdo;
        $pdo->exec("INSERT INTO registries (code, label, short_label, icon, color_theme, is_enabled, is_system, sort_order, default_visibility, lieu_label_override) VALUES ('test.custom2', 'Test Custom 2', 'TC2', '📋', 'orange', 1, 0, 99, 'agent_choice', 'Localisation détaillée')");

        $policy = new \App\Services\RegistryPolicy();
        $this->assertSame('Localisation détaillée', $policy->getLieuLabel('test.custom2'));
    }

    public function testRequiresPourCompteForCustomRegistryWithFlag(): void
    {
        $pdo = self::$pdo;
        $pdo->exec("INSERT INTO registries (code, label, short_label, icon, color_theme, is_enabled, is_system, sort_order, default_visibility, requires_pour_compte) VALUES ('test.custom3', 'Test Custom 3', 'TC3', '📋', 'teal', 1, 0, 99, 'agent_choice', 1)");

        $policy = new \App\Services\RegistryPolicy();
        $this->assertTrue($policy->requiresPourCompte('test.custom3'));
    }

    public function testHasDgiWarningPanelForCustomRegistryWithFlag(): void
    {
        $pdo = self::$pdo;
        $pdo->exec("INSERT INTO registries (code, label, short_label, icon, color_theme, is_enabled, is_system, sort_order, default_visibility, has_dgi_warning) VALUES ('test.custom4', 'Test Custom 4', 'TC4', '📋', 'indigo', 1, 0, 99, 'agent_choice', 1)");

        $policy = new \App\Services\RegistryPolicy();
        $this->assertTrue($policy->hasDgiWarningPanel('test.custom4'));
    }

    public function testGetLieuLabelForNonExistentRegistry(): void
    {
        $policy = new \App\Services\RegistryPolicy();
        // Non-existent registry should fall back to 'Lieu' (safe default)
        $this->assertSame('Lieu', $policy->getLieuLabel('nonexistent.registry'));
    }

    public function testRequiresPourCompteForNonExistentRegistry(): void
    {
        $policy = new \App\Services\RegistryPolicy();
        $this->assertFalse($policy->requiresPourCompte('nonexistent.registry'));
    }

    public function testHasDgiWarningPanelForNonExistentRegistry(): void
    {
        $policy = new \App\Services\RegistryPolicy();
        $this->assertFalse($policy->hasDgiWarningPanel('nonexistent.registry'));
    }
}
