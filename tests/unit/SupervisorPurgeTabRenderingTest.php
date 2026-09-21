<?php

/**
 * SupervisorPurgeTabRenderingTest — Application SST DREETS BFC
 *
 * TDD : le bouton superviseur de purge est TOUJOURS visible pour un
 * superviseur (onglet « Maintenance » des paramètres, page réservée au rôle
 * Superviseur). La présence de la sentinelle `erase.txt` n'est PAS testée au
 * rendu : elle est vérifiée au dernier moment, côté POST
 * (voir PurgeReportsHandlerTest). Le bouton ne doit donc jamais dépendre de
 * l'état du fichier.
 *
 * Contraintes de forme : pas de style inline, pas de <script>/handler inline.
 */

use App\DTO\SessionUser;
use PHPUnit\Framework\TestCase;

class SupervisorPurgeTabRenderingTest extends TestCase
{
    private static bool $bootstrapped = false;

    public static function setUpBeforeClass(): void
    {
        if (self::$bootstrapped) {
            return;
        }
        self::$bootstrapped = true;

        require_once __DIR__ . '/../../src/config.php';
        require_once __DIR__ . '/../../src/helpers.php';
        require_once __DIR__ . '/../../src/session.php';
        require_once __DIR__ . '/../../src/user_context.php';
        require_once __DIR__ . '/../../src/auth.php';
        require_once __DIR__ . '/../../src/Middleware/require_role.php';
        require_once __DIR__ . '/../../src/Router/Renderer.php';
        require_once __DIR__ . '/../../src/audit.php';
        require_once __DIR__ . '/../../src/Router/routes.php';
    }

    protected function setUp(): void
    {
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
    }

    private function loginAsSuperviseur(): void
    {
        setUserSession(SessionUser::fromArray([
            'id' => 2,
            'username' => 'test.sup',
            'nom' => 'Martin',
            'prenom' => 'Pierre',
            'role' => 'superviseur',
            'site_id' => null,
            'is_active' => 1,
        ]));
    }

    private function renderTabTemplate(): string
    {
        $csrfToken = 'test-csrf-token';
        ob_start();
        require __DIR__ . '/../../pages/settings/tab_maintenance.php';
        return (string) ob_get_clean();
    }

    private function tabTemplateSource(): string
    {
        return (string) file_get_contents(__DIR__ . '/../../pages/settings/tab_maintenance.php');
    }

    // ═══ Rendu du fragment ═══════════════════════════════════════════════════

    public function testTabRendersPurgeFormWithConfirmationAndCsrf(): void
    {
        $output = $this->renderTabTemplate();

        $this->assertStringContainsString('page=purge_reports', $output, 'Le formulaire poste vers la route de purge');
        $this->assertStringContainsString('method="POST"', $output, 'La purge est un POST');
        $this->assertStringContainsString('name="csrf_token"', $output, 'Le POST porte le jeton CSRF');
        $this->assertStringContainsString('name="confirm_purge"', $output, 'Une confirmation explicite est exigée');
        $this->assertStringContainsString('btn--danger', $output, 'Le bouton de purge est visuellement signalé');
        $this->assertStringContainsString('<button', $output, 'Le bouton est un vrai bouton');
    }

    public function testPurgeButtonVisibilityDoesNotDependOnEraseMarker(): void
    {
        // La visibilité est inconditionnelle : le rendu ne lit jamais la sentinelle.
        $source = $this->tabTemplateSource();
        $this->assertStringNotContainsString('is_file(', $source, 'Le rendu ne doit pas tester la sentinelle');
        $this->assertStringNotContainsString('isArmed', $source, 'La garde est vérifiée au POST, pas au rendu');
        $this->assertStringNotContainsString('PurgeService', $source, 'Le rendu ne doit pas consulter le service de purge');

        $output = $this->renderTabTemplate();
        $this->assertStringContainsString('page=purge_reports', $output, 'Le bouton est rendu même sans sentinelle');
    }

    public function testTabUsesNoInlineStyleOrScript(): void
    {
        $output = $this->renderTabTemplate();

        $this->assertStringNotContainsString('style="', $output, 'Zéro style inline : tout passe par public/css/style.css');
        $this->assertStringNotContainsString('<script', $output, 'Aucun script inline');
        $this->assertDoesNotMatchRegularExpression('/\bon(click|submit|change)=/i', $output, 'Aucun handler inline');
    }

    // ══ Câblage de l'onglet (page réservée superviseur) ═════════════════════

    public function testSettingsPageRegistersMaintenanceTabReservedToSupervisor(): void
    {
        $settings = (string) file_get_contents(__DIR__ . '/../../pages/settings.php');

        $this->assertStringContainsString("'maintenance'", $settings, 'settings.php whitelist l\'onglet maintenance');
        $this->assertStringContainsString("['tab' => 'maintenance']", $settings, 'settings.php expose le lien d\'onglet Maintenance');
        $this->assertStringContainsString('UserRole::Superviseur->value', $settings, 'Caractère superviseur requis pour accéder aux paramètres');
    }

    // ═══ Rendu de la page complète pour un superviseur ═══════════════════════

    public function testFullSettingsPageRendersPurgeButtonForSupervisor(): void
    {
        $this->loginAsSuperviseur();
        $_GET['page'] = 'settings';
        $_GET['tab'] = 'maintenance';

        ob_start();
        try {
            renderPageWithLayout(getRouter(), 'settings', 'test-csrf-token');
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->fail('settings?tab=maintenance a levé une exception : ' . $e->getMessage());
        }
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('page=purge_reports', $output, 'Le superviseur voit le bouton de purge');
        $this->assertStringContainsString('name="confirm_purge"', $output, 'La confirmation accompagne le bouton');
        $this->assertStringNotContainsString('<b>Fatal error</b>', $output, 'Pas d\'erreur fatale au rendu');
    }
}