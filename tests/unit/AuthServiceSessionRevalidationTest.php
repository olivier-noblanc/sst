<?php
/**
 * AuthService Session Revalidation Test — BUG session (reprise après arrêt sans résultat).
 *
 * Deux défauts couverts :
 *
 *  A. handleAutoAuth() court-circuitait la re-validation dès qu'une session
 *     existait (`if (isUserLoggedIn()) return;`). Or c'est getAuthenticatedUser()
 *     qui porte la logique de re-validation (throttle + isSessionValid). Une
 *     session déjà ouverte d'un utilisateur désactivé / démis / déconnecté
 *     partout / anonymisé survivait donc jusqu'à expiration (24h).
 *
 *  B. isSessionValid() comparait le marqueur DB via strtotime(). Le marqueur est
 *     écrit par SQLite datetime('now') en UTC, mais strtotime() l'interprète dans
 *     le fuseau PHP (Europe/Paris, src/config.php) : le marqueur était vu 1–2h
 *     trop tôt et la session restait considérée valide à tort. Il faut parser le
 *     marqueur en UTC.
 *
 * Le throttle existant (app_session_check_interval, 300s par défaut) reste
 * respecté : aucune re-validation dans la fenêtre.
 *
 * NB : les compteurs CHSCT / invitations ne sont pas concernés par ce lot.
 */

use PHPUnit\Framework\TestCase;
use App\Services\AuthService;
use App\Repository\UserRepository;
use App\Event\EventDispatcher;

class AuthServiceSessionRevalidationTest extends TestCase
{
    private PDO $pdo;
    private AuthService $service;
    private UserRepository $repo;
    private string $originalTimezone;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        // IDs auto-incrémentés : pas de collision avec les fixtures d'autres
        // classes qui codent en dur des ids proches (ex. AgentInviteDeliveryTest).
        cleanupForTest($this->pdo, 'test.sessreval%');
        $_SESSION = [];

        clearConfigCache();
        updateConfig($this->pdo, 'app_session_check_interval', '300');
        clearConfigCache();

        // La prod tourne en Europe/Paris (src/config.php) alors que le marqueur
        // est écrit en UTC : on fixe explicitement le fuseau pour rendre l'écart
        // UTC/local déterministe, quel que soit l'environnement d'exécution.
        $this->originalTimezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Paris');

        $this->repo = new UserRepository($this->pdo);
        $this->service = new AuthService($this->repo, new EventDispatcher());
    }

    protected function tearDown(): void
    {
        // La suite PHPUnit partage UNE seule base SQLite en mémoire (pas de
        // process isolation) : on ne laisse ni utilisateur ni clé de config
        // derrière nous, sinon les tests suivants (compteurs CHSCT,
        // invitations) deviennent dépendants de l'ordre d'exécution.
        cleanupForTest($this->pdo, 'test.sessreval%');
        $this->pdo->exec("DELETE FROM config_app WHERE cle = 'app_session_check_interval'");
        clearConfigCache();

        date_default_timezone_set($this->originalTimezone);
        $_SESSION = [];
    }

    /**
     * @return int the newly created user id
     */
    private function seedUser(string $username, int $active = 1): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO users (username, nom, prenom, role, site_id, is_active, email) "
            . "VALUES (:username, 'Nom', 'Prenom', 'agent', NULL, :active, 'fixture@dreets-bfc.gouv.fr')"
        );
        $stmt->execute([':username' => $username, ':active' => $active]);
        return (int) $this->pdo->lastInsertId();
    }

    private function openSession(int $id, int $sessionStartedAt, int $lastCheck): void
    {
        $user = $this->repo->findById($id);
        $this->assertNotNull($user, 'fixture user must exist');
        \setUserSession($user);
        $_SESSION['session_started_at'] = $sessionStartedAt;
        $_SESSION['last_session_check'] = $lastCheck;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // A. handleAutoAuth() doit re-valider une session déjà ouverte
    // ═════════════════════════════════════════════════════════════════════════

    public function testHandleAutoAuthRevalidatesDeactivatedSession(): void
    {
        $id = $this->seedUser('test.sessreval.deact');
        $this->openSession($id, time() - 60, 0);
        // Désactivation : is_active=0 (le marqueur importe peu ici, c'est le
        // chemin is_active qui doit court-circuiter la session).
        $this->pdo->exec("UPDATE users SET is_active = 0, sessions_invalid_before = datetime('now') WHERE id = $id");

        $this->service->handleAutoAuth();

        $this->assertFalse(
            \isUserLoggedIn(),
            'handleAutoAuth doit fermer la session d\'un utilisateur désactivé'
        );
    }

    public function testHandleAutoAuthRevalidatesSessionInvalidatedByMarker(): void
    {
        // Représente logout-everywhere / démotion / anonymisation : le marqueur
        // sessions_invalid_before devient plus récent que le début de session.
        $id = $this->seedUser('test.sessreval.marker');
        $this->openSession($id, time() - 60, 0);
        $this->pdo->exec("UPDATE users SET sessions_invalid_before = datetime('now') WHERE id = $id");

        $this->service->handleAutoAuth();

        $this->assertFalse(
            \isUserLoggedIn(),
            'handleAutoAuth doit fermer la session invalidée par le marqueur'
        );
    }

    public function testHandleAutoAuthRespectsRevalidationThrottle(): void
    {
        $id = $this->seedUser('test.sessreval.throttle');
        // Dernière vérification = maintenant → dans la fenêtre de throttle.
        $this->openSession($id, time() - 60, time());
        $this->pdo->exec("UPDATE users SET is_active = 0, sessions_invalid_before = datetime('now') WHERE id = $id");

        $this->service->handleAutoAuth();
        $this->assertTrue(
            \isUserLoggedIn(),
            'Dans la fenêtre de throttle, aucune re-validation ne doit avoir lieu'
        );

        // Fenêtre expirée → la re-validation reprend et ferme la session.
        $_SESSION['last_session_check'] = 0;
        $this->service->handleAutoAuth();
        $this->assertFalse(
            \isUserLoggedIn(),
            'Hors fenêtre de throttle, la re-validation doit fermer la session'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // B. isSessionValid() doit comparer le marqueur DB UTC correctement
    // ══════════════════════════════════════════════════════════════════════════

    public function testGetAuthenticatedUserInvalidatesWhenUtcMarkerIsNewerThanSessionStart(): void
    {
        // Utilisateur actif : seule la comparaison du marqueur peut invalider.
        // Marqueur écrit "maintenant" en UTC (datetime('now')) → plus récent que
        // le début de session (il y a 30 s) → session invalide.
        $id = $this->seedUser('test.sessreval.utc');
        $this->openSession($id, time() - 30, 0);
        $this->pdo->exec("UPDATE users SET sessions_invalid_before = datetime('now') WHERE id = $id");

        $fresh = $this->service->getAuthenticatedUser();

        $this->assertNull($fresh, 'Un marqueur UTC plus récent que le début de session doit invalider la session');
        $this->assertFalse(\isUserLoggedIn());
    }

    public function testGetAuthenticatedUserKeepsSessionWhenUtcMarkerIsOlderThanSessionStart(): void
    {
        // Garde-fou : le correctif UTC ne doit pas invalider une session dont le
        // marqueur est réellement antérieur à son début.
        $id = $this->seedUser('test.sessreval.old');
        $this->openSession($id, time() - 30, 0);
        $this->pdo->exec("UPDATE users SET sessions_invalid_before = datetime('now', '-1 hour') WHERE id = $id");

        $fresh = $this->service->getAuthenticatedUser();

        $this->assertNotNull($fresh, 'Un marqueur plus ancien que le début de session ne doit pas invalider la session');
        $this->assertTrue(\isUserLoggedIn());
    }
}