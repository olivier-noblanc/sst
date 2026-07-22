<?php
/**
 * ConfigService Unit Tests — Configuration read/write, registry toggles
 *
 * Tests ConfigService from src/Services/ConfigService.php:
 * - get() returns config values from DB, falls back to default
 * - set() inserts/updates config values
 * - clearCache() invalidates cached values
 * - isNoSiteMode() checks for zero active sites
 * - hasActiveSites() / countActiveSites() reflect DB state
 * - isRegistryEnabled() checks registry type toggles
 * - getAppVersion() reads from CHANGELOG.md
 */

use PHPUnit\Framework\TestCase;
use App\Services\ConfigService;

class ConfigServiceTest extends TestCase
{
    private PDO $pdo;
    private ConfigService $service;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        $this->pdo->exec('PRAGMA foreign_keys = OFF');
        $this->pdo->exec('DELETE FROM config_app');
        $this->pdo->exec('DELETE FROM sites');
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        clearConfigCache();

        $this->service = new ConfigService();

        // Reset the isNoSiteMode static cache via reflection
        $reflection = new ReflectionMethod($this->service, 'isNoSiteMode');
        // The static $cache is a local static — we can't reset it directly.
        // We rely on fresh process per test class or test ordering.
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // get()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testGetReturnsDefaultForMissingKey(): void
    {
        $result = $this->service->get('nonexistent_key', 'fallback');
        $this->assertEquals('fallback', $result);
    }

    public function testGetReturnsEmptyStringDefault(): void
    {
        $result = $this->service->get('nonexistent_key');
        $this->assertEquals('', $result);
    }

    public function testGetReturnsValueFromDB(): void
    {
        $this->pdo->exec("INSERT INTO config_app (cle, valeur) VALUES ('test_key', 'test_value')");
        $this->service->clearCache();
        $result = $this->service->get('test_key');
        $this->assertEquals('test_value', $result);
    }

    public function testGetReturnsDefaultWhenKeyMissingInDB(): void
    {
        $result = $this->service->get('missing_key', 'my_default');
        $this->assertEquals('my_default', $result);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // set() + get() round-trip
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testSetAndGetValueRoundTrip(): void
    {
        $this->service->set('my_setting', 'hello');
        $result = $this->service->get('my_setting');
        $this->assertEquals('hello', $result);
    }

    public function testSetUpdatesExistingValue(): void
    {
        $this->service->set('key', 'old');
        $this->service->set('key', 'new');
        $result = $this->service->get('key');
        $this->assertEquals('new', $result);
    }

    public function testSetClearsCacheSoGetReadsFreshValue(): void
    {
        $this->service->get('cached_key', 'default');
        $this->service->set('cached_key', 'fresh');
        $result = $this->service->get('cached_key', 'stale');
        $this->assertEquals('fresh', $result);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // clearCache()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testClearCacheAllowsFreshDBRead(): void
    {
        $this->service->get('cached_val', 'first');
        // Insert directly into DB to bypass the service cache
        $this->pdo->exec("INSERT INTO config_app (cle, valeur) VALUES ('cached_val', 'second')");
        $this->service->clearCache();
        $result = $this->service->get('cached_val', 'fallback');
        $this->assertEquals('second', $result);
    }

    public function testClearCacheSetsGlobalFlag(): void
    {
        $this->service->clearCache();
        $this->assertTrue($GLOBALS['_config_cache_cleared'] ?? false);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // isNoSiteMode()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testIsNoSiteModeReturnsTrueWhenNoActiveSites(): void
    {
        // DB is empty (no sites), so should be no-site mode.
        // Note: isNoSiteMode() caches its result in a static local.
        // First call in a fresh process with empty DB returns true.
        $result = $this->service->isNoSiteMode();
        $this->assertTrue($result);
    }

    public function testHasActiveSitesReturnsFalseWhenNoSites(): void
    {
        $this->assertFalse($this->service->hasActiveSites());
    }

    public function testHasActiveSitesReturnsTrueWhenActiveSiteExists(): void
    {
        $this->pdo->exec("INSERT INTO sites (code, nom, is_active) VALUES ('UD1', 'Site 1', 1)");
        $this->assertTrue($this->service->hasActiveSites());
    }

    public function testCountActiveSitesReturnsZeroWhenEmpty(): void
    {
        $this->assertEquals(0, $this->service->countActiveSites());
    }

    public function testCountActiveSitesReturnsCorrectCount(): void
    {
        $this->pdo->exec("INSERT INTO sites (code, nom, is_active) VALUES ('UD1', 'Site 1', 1)");
        $this->pdo->exec("INSERT INTO sites (code, nom, is_active) VALUES ('UD2', 'Site 2', 1)");
        $this->assertEquals(2, $this->service->countActiveSites());
    }

    public function testCountActiveSitesExcludesInactiveSites(): void
    {
        $this->pdo->exec("INSERT INTO sites (code, nom, is_active) VALUES ('UD1', 'Active', 1)");
        $this->pdo->exec("INSERT INTO sites (code, nom, is_active) VALUES ('UD2', 'Inactive', 0)");
        $this->assertEquals(1, $this->service->countActiveSites());
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // isRegistryEnabled()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testIsRegistryEnabledRsstAlwaysReturnsTrue(): void
    {
        $this->assertTrue($this->service->isRegistryEnabled(TYPE_RSST));
    }

    public function testIsRegistryEnabledRamiDefaultReturnsFalse(): void
    {
        $this->service->clearCache();
        $result = $this->service->isRegistryEnabled(TYPE_RAMI);
        $this->assertFalse($result);
    }

    public function testIsRegistryEnabledDgiDefaultReturnsFalse(): void
    {
        $this->service->clearCache();
        $result = $this->service->isRegistryEnabled(TYPE_DGI);
        $this->assertFalse($result);
    }

    public function testIsRegistryEnabledRamiWhenEnabledInDB(): void
    {
        $this->service->set('app_registry_rami_enabled', '1');
        $result = $this->service->isRegistryEnabled(TYPE_RAMI);
        $this->assertTrue($result);
    }

    public function testIsRegistryEnabledDgiWhenEnabledInDB(): void
    {
        $this->service->set('app_registry_dgi_enabled', '1');
        $result = $this->service->isRegistryEnabled(TYPE_DGI);
        $this->assertTrue($result);
    }

    public function testIsRegistryEnabledRamiWhenExplicitlyDisabledInDB(): void
    {
        $this->service->set('app_registry_rami_enabled', '0');
        $result = $this->service->isRegistryEnabled(TYPE_RAMI);
        $this->assertFalse($result);
    }

    public function testIsRegistryEnabledUnknownTypeReturnsFalse(): void
    {
        $this->assertFalse($this->service->isRegistryEnabled('unknown'));
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // getEnabledRegistries()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testGetEnabledRegistriesReturnsRsstByDefault(): void
    {
        $this->service->clearCache();
        $result = $this->service->getEnabledRegistries();
        $this->assertEquals([TYPE_RSST], $result);
    }

    public function testGetEnabledRegistriesIncludesRamiWhenEnabled(): void
    {
        $this->service->set('app_registry_rami_enabled', '1');
        $result = $this->service->getEnabledRegistries();
        $this->assertContains(TYPE_RSST, $result);
        $this->assertContains(TYPE_RAMI, $result);
    }

    public function testGetEnabledRegistriesIncludesDgiWhenEnabled(): void
    {
        $this->service->set('app_registry_dgi_enabled', '1');
        $result = $this->service->getEnabledRegistries();
        $this->assertContains(TYPE_RSST, $result);
        $this->assertContains(TYPE_DGI, $result);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // getRoleLabel() / getRoleLabels()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testGetRoleLabelReturnsDefaultWhenNoCustomLabel(): void
    {
        $result = $this->service->getRoleLabel('agent');
        $this->assertEquals(ROLE_LABELS_DEFAULT['agent'], $result);
    }

    public function testGetRoleLabelReturnsCustomFromDB(): void
    {
        $this->service->set('app_role_label_agent', 'Mon Agent');
        $result = $this->service->getRoleLabel('agent');
        $this->assertEquals('Mon Agent', $result);
    }

    public function testGetRoleLabelsReturnsAllThreeRoles(): void
    {
        $labels = $this->service->getRoleLabels();
        $this->assertArrayHasKey('agent', $labels);
        $this->assertArrayHasKey('superviseur', $labels);
        $this->assertArrayHasKey('chsct', $labels);
    }

    public function testGetRoleLabelShortStripsMembrePrefix(): void
    {
        $this->service->set('app_role_label_chsct', 'Membre CSA/CHSCT');
        $result = $this->service->getRoleLabelShort('chsct');
        $this->assertEquals('CSA/CHSCT', $result);
    }

    public function testGetRoleLabelShortReturnsFullLabelWhenNoPrefix(): void
    {
        $result = $this->service->getRoleLabelShort('agent');
        $this->assertEquals(ROLE_LABELS_DEFAULT['agent'], $result);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // getAppVersion()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testGetAppVersionReturnsString(): void
    {
        $version = $this->service->getAppVersion();
        $this->assertIsString($version);
        $this->assertNotEmpty($version);
    }

    public function testGetAppVersionMatchesSemverPattern(): void
    {
        $version = $this->service->getAppVersion();
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $version);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Service instantiation
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testServiceCanBeInstantiated(): void
    {
        $service = new ConfigService();
        $this->assertInstanceOf(ConfigService::class, $service);
    }

    public function testGetInstanceReturnsSameInstance(): void
    {
        $a = ConfigService::getInstance();
        $b = ConfigService::getInstance();
        $this->assertSame($a, $b);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // app_linked_agents_label (personnalisation admin)
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testLinkedAgentsLabelDefaultsWithExpectedValue(): void
    {
        // When no value is set in DB, getConfig should return the fallback.
        $result = $this->service->get('app_linked_agents_label', 'Rattacher des collègues au signalement');
        $this->assertEquals('Rattacher des collègues au signalement', $result);
    }

    public function testLinkedAgentsLabelAcceptsCustomValue(): void
    {
        $this->service->set('app_linked_agents_label', 'Associer des collègues');
        $this->service->clearCache();
        $result = $this->service->get('app_linked_agents_label');
        $this->assertEquals('Associer des collègues', $result);
    }

    public function testLinkedAgentsLabelFallbackWhenEmptyString(): void
    {
        $this->service->set('app_linked_agents_label', '');
        $this->service->clearCache();
        // Handler should never save blank, but if it does, the template fallback applies.
        $result = $this->service->get('app_linked_agents_label', 'Rattacher des collègues au signalement');
        $this->assertEquals('Rattacher des collègues au signalement', $result);
    }
}
