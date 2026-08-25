<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\SessionService;

/**
 * Test de non-régression CSRF — vérifie que le fix empêche l'accumulation
 * de tokens sur simple rafraîchissement de page.
 *
 * Issue: Le log "[SST-CSRF] Evicting 1 old CSRF token(s)" apparaissait
 * même avec un seul onglet car un nouveau token était généré à chaque GET.
 *
 * Fix: Réutilisation du token existant s'il est valide (< 1h).
 */
class CsrfRegressionTest extends TestCase
{
    private SessionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        if (session_status() !== PHP_SESSION_NONE) {
            session_write_close();
        }

        $_SESSION = null;
        $this->service = SessionService::getInstance();
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $_SESSION = null;
        parent::tearDown();
    }

    /**
     * Test: Un seul onglet ne doit PAS générer d'éviction
     * Scénario: 10 rafraîchissements de page = 10 appels à generateCsrfToken()
     * Résultat attendu: 1 seul token (réutilisé), pas d'éviction
     */
    public function testNoEvictionOnSingleTabRefresh(): void
    {
        // Simuler 10 rafraîchissements de page (10 appels GET)
        $firstToken = null;
        for ($i = 0; $i < 10; $i++) {
            $token = $this->service->generateCsrfToken();
            if ($i === 0) {
                $firstToken = $token;
            }
            // Tous les tokens doivent être identiques (réutilisation)
            $this->assertSame($firstToken, $token, "Token should be reused on refresh (iteration $i)");
        }

        // Un seul token en session
        $this->assertCount(1, $_SESSION['csrf_tokens'], 'Should have exactly 1 token after 10 refreshes');

        // Pas de log d'éviction
        $this->assertArrayNotHasKey('csrf_eviction_logged', $_SESSION, 'Should not log eviction for single tab');
    }

    /**
     * Test: Le token est consommé après un POST valide
     * Scénario: generate -> validate -> generate
     * Résultat attendu: nouveau token après consommation
     */
    public function testNewTokenAfterConsumption(): void
    {
        $token1 = $this->service->generateCsrfToken();

        // Valider (consommer) le token
        $isValid = $this->service->validateCsrfToken($token1);
        $this->assertTrue($isValid, 'Token should be valid before consumption');

        // Après consommation, générer un nouveau token
        $token2 = $this->service->generateCsrfToken();

        // Doit être différent
        $this->assertNotSame($token1, $token2, 'New token should be generated after consumption');

        // Ancien token invalide
        $this->assertFalse($this->service->validateCsrfToken($token1), 'Consumed token should be invalid');
    }

    /**
     * Test: Token expire après 1 heure (simulé)
     */
    public function testTokenExpiresAfterOneHour(): void
    {
        $token1 = $this->service->generateCsrfToken();

        // Simuler le passage du temps en modifiant le timestamp en session
        $tokens = &$_SESSION['csrf_tokens'];
        $keys = array_keys($tokens);
        $tokens[$keys[0]] = time() - 3601; // 1 heure + 1 seconde ago
        $_SESSION['csrf_tokens'] = $tokens;

        // Nouveau token devrait être généré (ancien expiré)
        $token2 = $this->service->generateCsrfToken();

        $this->assertNotSame($token1, $token2, 'New token should be generated after expiry');
    }

    /**
     * Test: Support multi-onglets (jusqu'à 50 tokens)
     * Scénario: 60 tokens générés (simule 60 onglets/formulaires)
     * Résultat attendu: 50 tokens max, les 10 premiers évincés
     */
    public function testMultiTabSupportWithLimit(): void
    {
        // Pour tester l'éviction, on doit forcer la création de nouveaux tokens
        // (normalement ils sont réutilisés, donc on les consomme un par un)
        for ($i = 0; $i < 60; $i++) {
            $token = $this->service->generateCsrfToken();
            $this->service->validateCsrfToken($token); // Consommer pour forcer nouveau token
        }

        // Devrait avoir max 50 tokens
        $this->assertLessThanOrEqual(50, count($_SESSION['csrf_tokens']), 'Should not exceed 50 tokens');
    }

    /**
     * Test: Token invalide rejeté
     */
    public function testInvalidTokenRejected(): void
    {
        $this->service->generateCsrfToken();
        $this->assertFalse($this->service->validateCsrfToken('invalid_token_12345'));
    }

    /**
     * Test: Token vide rejeté
     */
    public function testEmptyTokenRejected(): void
    {
        $this->service->generateCsrfToken();
        $this->assertFalse($this->service->validateCsrfToken(''));
    }

    /**
     * Test: Token length (64 chars = bin2hex(random_bytes(32)))
     */
    public function testTokenLength(): void
    {
        $token = $this->service->generateCsrfToken();
        $this->assertSame(64, strlen($token), 'Token should be 64 hex characters');
    }
}
