<?php

/**
 * OutboxBannerRenderingTest — Application SST DREETS BFC
 *
 * TDD : tests de rendu de la bannière locale d'état SMTP/outbox, écrits AVANT
 * l'implémentation.
 *
 * Contrat verrouillé ici :
 *   - état sain (aucun failed, aucun pending trop ancien) → aucune bannière ;
 *   - un message failed (échec définitif) → bannière NON-DISMISSIBLE ;
 *   - un pending échu et en file depuis trop longtemps → bannière ;
 *   - un pending récent ou en attente de backoff (next_attempt_at futur) → pas
 *     de bannière (son attente est programmée, ce n'est pas un incident) ;
 *   - le texte demande EXPLICITEMENT l'intervention d'un technicien ;
 *   - la bannière porte un lien vers les paramètres SMTP (settings?tab=smtp) ;
 *   - aucun style inline (zéro attribut style="") ;
 *   - aucun bouton/formulaire de fermeture (bannière non-dismissible) ;
 *   - la bannière n'apparaît pas pour un visiteur non connecté.
 *
 * Le rendu s'appuie sur l'état réel de la table email_outbox (base SQLite
 * mémoire du bootstrap) — le flag est donc testé de bout en bout.
 */

use App\DTO\SessionUser;
use PHPUnit\Framework\TestCase;

class OutboxBannerRenderingTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        $this->pdo->exec('DELETE FROM email_outbox');
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $this->pdo->exec('DELETE FROM email_outbox');
        $_SESSION = [];
    }

    private function login(): void
    {
        setUserSession(SessionUser::fromArray([
            'id' => 1,
            'username' => 'test.agent',
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'role' => 'agent',
            'site_id' => 1,
            'is_active' => 1,
        ]));
    }

    /**
     * Insère une ligne d'outbox brute — les colonnes de temps sont contrôlées
     * explicitement pour piloter l'ancienneté sans dépendre de l'horloge.
     */
    private function insertRow(
        string $dedupKey,
        string $status,
        string $createdAt,
        ?string $nextAttemptAt = null,
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO email_outbox (dedup_key, recipient, subject, body, status, attempts, created_at, next_attempt_at)
             VALUES (:k, :r, :s, :b, :st, 0, :created, :next)'
        );
        $stmt->execute([
            ':k'       => $dedupKey,
            ':r'       => 'agent@dreets-bfc.gouv.fr',
            ':s'       => 'Sujet',
            ':b'       => 'Corps',
            ':st'      => $status,
            ':created' => $createdAt,
            ':next'    => $nextAttemptAt,
        ]);
    }

    private function renderBanner(): string
    {
        $template = __DIR__ . '/../../templates/outbox_banner.php';
        if (!file_exists($template)) {
            $this->fail('templates/outbox_banner.php doit exister pour porter la bannière');
        }

        ob_start();
        require $template;

        return (string) ob_get_clean();
    }

    private function oldTimestamp(): string
    {
        // Bien antérieur à tout seuil raisonnable : la ligne est « trop ancienne ».
        return '2000-01-01 00:00:00';
    }

    private function freshTimestamp(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    // ═══ État sain → pas de bannière ═══════════════════════════════════════

    public function testNoBannerWhenOutboxIsHealthy(): void
    {
        $this->login();

        $output = $this->renderBanner();

        $this->assertStringNotContainsString('outbox-banner', $output, 'Outbox sain : aucune bannière');
        $this->assertSame('', trim($output), 'Outbox sain : le template ne doit rien rendre');
    }

    public function testNoBannerWhenVisitorIsNotLoggedIn(): void
    {
        // État en incident, mais aucun utilisateur connecté.
        $this->insertRow('anon-failed', 'failed', $this->oldTimestamp());

        $output = $this->renderBanner();

        $this->assertStringNotContainsString('outbox-banner', $output, 'Visiteur non connecté : pas de bannière');
    }

    // ═══ Incident : messages failed ═════════════════════════════════════════

    public function testBannerShownForFailedMessages(): void
    {
        $this->login();
        $this->insertRow('failed-1', 'failed', $this->oldTimestamp());

        $output = $this->renderBanner();

        $this->assertStringContainsString('outbox-banner', $output, 'Un échec définitif déclenche la bannière');
        $this->assertStringContainsString('role="alert"', $output, 'La bannière doit être annoncée comme alerte');
        $this->assertStringContainsString('technicien', $output, 'Le texte demande explicitement l\'intervention d\'un technicien');
        $this->assertStringContainsString('tab=smtp', $output, 'La bannière renvoie vers les paramètres SMTP');
    }

    // ═══ Incident : pending trop ancien ═════════════════════════════════════

    public function testBannerShownForStalePendingMessages(): void
    {
        $this->login();
        // Échu (next_attempt_at NULL) et en file depuis très longtemps.
        $this->insertRow('stale-1', 'pending', $this->oldTimestamp(), null);

        $output = $this->renderBanner();

        $this->assertStringContainsString('outbox-banner', $output, 'Un pending échu trop ancien déclenche la bannière');
    }

    public function testNoBannerForFreshPendingMessages(): void
    {
        $this->login();
        $this->insertRow('fresh-1', 'pending', $this->freshTimestamp(), null);

        $output = $this->renderBanner();

        $this->assertStringNotContainsString('outbox-banner', $output, 'Un pending récent n\'est pas un incident');
    }

    public function testNoBannerForPendingWaitingOnBackoff(): void
    {
        $this->login();
        // Ancien à l'enqueue MAIS programmé dans le futur (backoff) : son attente
        // est normale, ce n'est pas un incident.
        $future = gmdate('Y-m-d H:i:s', time() + 3600);
        $this->insertRow('backoff-1', 'pending', $this->oldTimestamp(), $future);

        $output = $this->renderBanner();

        $this->assertStringNotContainsString('outbox-banner', $output, 'Un pending en attente de backoff n\'est pas un incident');
    }

    // ═══ Contraintes de forme ══════════════════════════════════════════════

    public function testBannerUsesNoInlineStyle(): void
    {
        $this->login();
        $this->insertRow('failed-style', 'failed', $this->oldTimestamp());

        $output = $this->renderBanner();

        $this->assertStringNotContainsString('style="', $output, 'Zéro style inline : tout passe par public/css/style.css');
    }

    public function testBannerIsNotDismissible(): void
    {
        $this->login();
        $this->insertRow('failed-dismiss', 'failed', $this->oldTimestamp());

        $output = $this->renderBanner();

        $this->assertStringNotContainsString('<button', $output, 'Pas de bouton de fermeture');
        $this->assertStringNotContainsString('<form', $output, 'Pas de formulaire de fermeture');
    }
}
