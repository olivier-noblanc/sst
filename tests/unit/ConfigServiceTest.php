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
use App\Enum\ReportType;
use App\Services\ConfigService;
use App\Repository\RegistryRepository;

class ConfigServiceTest extends TestCase
{
    private PDO $pdo;
    private ConfigService $service;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        $this->pdo->exec('PRAGMA foreign_keys = OFF');
        $this->pdo->exec('DELETE FROM config_app');
        $this->pdo->exec('DELETE FROM registry_fields');
        $this->pdo->exec('DELETE FROM registries');
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
        RegistryRepository::instance()->seedDefaults();
        $this->assertTrue($this->service->isRegistryEnabled(ReportType::Rsst->value));
    }

    public function testIsRegistryEnabledRamiDefaultReturnsFalse(): void
    {
        $this->service->clearCache();
        $result = $this->service->isRegistryEnabled(ReportType::Rami->value);
        $this->assertFalse($result);
    }

    public function testIsRegistryEnabledDgiDefaultReturnsFalse(): void
    {
        $this->service->clearCache();
        $result = $this->service->isRegistryEnabled(ReportType::Dgi->value);
        $this->assertFalse($result);
    }

    public function testIsRegistryEnabledRamiWhenEnabledInDB(): void
    {
        $repo = RegistryRepository::instance();
        $repo->seedDefaults();
        $rami = $repo->findByCode(ReportType::Rami->value);
        $repo->update((int) $rami['id'], ['is_enabled' => 1]);
        $this->service->clearCache();
        $result = $this->service->isRegistryEnabled(ReportType::Rami->value);
        $this->assertTrue($result);
    }

    public function testIsRegistryEnabledDgiWhenEnabledInDB(): void
    {
        $repo = RegistryRepository::instance();
        $repo->seedDefaults();
        $dgi = $repo->findByCode(ReportType::Dgi->value);
        $repo->update((int) $dgi['id'], ['is_enabled' => 1]);
        $this->service->clearCache();
        $result = $this->service->isRegistryEnabled(ReportType::Dgi->value);
        $this->assertTrue($result);
    }

    public function testIsRegistryEnabledRamiWhenExplicitlyDisabledInDB(): void
    {
        $repo = RegistryRepository::instance();
        $repo->seedDefaults();
        $rami = $repo->findByCode(ReportType::Rami->value);
        $repo->update((int) $rami['id'], ['is_enabled' => 0]);
        $this->service->clearCache();
        $result = $this->service->isRegistryEnabled(ReportType::Rami->value);
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
        RegistryRepository::instance()->seedDefaults();
        $this->service->clearCache();
        $result = $this->service->getEnabledRegistries();
        $this->assertEquals([ReportType::Rsst->value], $result);
    }

    public function testGetEnabledRegistriesIncludesRamiWhenEnabled(): void
    {
        $repo = RegistryRepository::instance();
        $repo->seedDefaults();
        $rami = $repo->findByCode(ReportType::Rami->value);
        $repo->update((int) $rami['id'], ['is_enabled' => 1]);
        $this->service->clearCache();
        $result = $this->service->getEnabledRegistries();
        $this->assertContains(ReportType::Rsst->value, $result);
        $this->assertContains(ReportType::Rami->value, $result);
    }

    public function testGetEnabledRegistriesIncludesDgiWhenEnabled(): void
    {
        $repo = RegistryRepository::instance();
        $repo->seedDefaults();
        $dgi = $repo->findByCode(ReportType::Dgi->value);
        $repo->update((int) $dgi['id'], ['is_enabled' => 1]);
        $this->service->clearCache();
        $result = $this->service->getEnabledRegistries();
        $this->assertContains(ReportType::Rsst->value, $result);
        $this->assertContains(ReportType::Dgi->value, $result);
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

    // ═══════════════════════════════════════════════════════════════════════════════
    // Empty / null / whitespace fallback edge cases
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testGetReturnsFallbackForEmptyStringStoredDirectly(): void
    {
        // Insert empty string directly into DB, bypassing set()
        $this->pdo->exec("INSERT INTO config_app (cle, valeur) VALUES ('raw_empty', '')");
        $this->service->clearCache();
        $result = $this->service->get('raw_empty', 'my_fallback');
        $this->assertEquals('my_fallback', $result);
    }

    public function testGetReturnsFallbackForNullStoredDirectly(): void
    {
        $this->pdo->exec("INSERT INTO config_app (cle, valeur) VALUES ('raw_null', NULL)");
        $this->service->clearCache();
        $result = $this->service->get('raw_null', 'null_fallback');
        $this->assertEquals('null_fallback', $result);
    }

    public function testGetReturnsEmptyStringDefaultWhenEmptyStoredAndNoFallback(): void
    {
        $this->service->set('empty_no_default', '');
        $this->service->clearCache();
        // No fallback provided — should return '' (the default of get())
        $result = $this->service->get('empty_no_default');
        $this->assertEquals('', $result);
    }

    public function testGetReturnsWhitespaceValueWhenStored(): void
    {
        // Whitespace is NOT treated as empty — it's a valid non-empty value
        $this->service->set('whitespace_key', '   ');
        $this->service->clearCache();
        $result = $this->service->get('whitespace_key', 'fallback');
        $this->assertEquals('   ', $result);
    }

    public function testGetReturnsValueAfterOverwriteWithEmpty(): void
    {
        // Set a value, then overwrite with empty — fallback should apply
        $this->service->set('overwrite_key', 'original');
        $this->service->clearCache();
        $result = $this->service->get('overwrite_key', 'default');
        $this->assertEquals('original', $result);

        $this->service->set('overwrite_key', '');
        $this->service->clearCache();
        $result = $this->service->get('overwrite_key', 'default');
        $this->assertEquals('default', $result);
    }

    public function testGetReturnsNonEmptyStringCorrectly(): void
    {
        $this->service->set('valid_key', 'valid_value');
        $this->service->clearCache();
        $result = $this->service->get('valid_key', 'fallback');
        $this->assertEquals('valid_value', $result);
    }
}
