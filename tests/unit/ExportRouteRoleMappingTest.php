<?php
/**
 * Export Route Role Mapping Test — Application SST DREETS BFC
 *
 * Bug confirmé : pages/export.php autorise [Superviseur, Chsct] et
 * handlers/export_handler.php documente le même contrat, mais
 * src/Router/routes.php protégeait le POST export par Superviseur seul.
 * Le CHSCT voyait donc le formulaire (GET rendu par la page) puis se faisait
 * refuser au submit (POST) → « Accès refusé ».
 *
 * Ce test verrouille l'alignement du middleware POST export sur le contrat
 * DÉJÀ documenté par la page/handler — il n'élargit aucune permission
 * au-delà de ce contrat existant.
 *
 * Le test est un vrai test de comportement runtime : il lit les rôles
 * effectivement câblés sur la route export (via createRouter()) puis exécute
 * le RoleMiddleware réel dans un sous-processus (middleware_runner.php).
 * Il ne se contente pas de recopier la liste des rôles.
 */

use PHPUnit\Framework\TestCase;
use App\Enum\UserRole;
use App\Middleware\RoleMiddleware;

require_once __DIR__ . '/../bootstrap.php';

class ExportRouteRoleMappingTest extends TestCase
{
    /**
     * Extrait les rôles effectivement configurés sur le POST export.
     *
     * @return list<string>
     */
    private function exportPostRoles(): array
    {
        $router = createRouter();
        $middlewares = $router->getPostMiddleware('export');

        /** @var list<string>|null $roles */
        $roles = null;
        foreach ($middlewares as $mw) {
            if ($mw instanceof RoleMiddleware) {
                $prop = new ReflectionProperty($mw, 'roles');
                /** @var list<string> $value */
                $value = $prop->getValue($mw);
                $roles = $value;
            }
        }

        if ($roles === null) {
            $this->fail('export doit avoir un RoleMiddleware câblé sur le POST');
        }

        return $roles;
    }

    /**
     * Exécute le RoleMiddleware RÉEL de la route export (rôles lus depuis
     * createRouter()) dans un sous-processus, avec le rôle utilisateur donné.
     *
     * @return array<string, mixed>
     */
    private function runExportPostAs(string $userRole): array
    {
        $roles = $this->exportPostRoles();

        $config = [
            'middleware' => 'RoleMiddleware',
            'args' => [$roles],
            'session' => ['user' => ['id' => 1, 'role' => $userRole]],
            'server' => ['REQUEST_METHOD' => 'POST'],
        ];

        $tmpFile = tempnam(sys_get_temp_dir(), 'export_role_');
        file_put_contents($tmpFile, json_encode($config));
        $cmd = 'php ' . escapeshellarg(__DIR__ . '/../middleware_runner.php')
            . ' ' . escapeshellarg($tmpFile) . ' 2>NUL';
        exec($cmd, $output, $exitCode);
        unlink($tmpFile);

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode(implode('', $output), true);

        return $decoded ?? ['error' => 'No output'];
    }

    // ─── RED avant correctif : le CHSCT était refusé sur le POST export ─────

    public function testChsctIsAllowedOnExportPost(): void
    {
        $result = $this->runExportPostAs(UserRole::Chsct->value);

        $this->assertArrayHasKey(
            'next_called',
            $result,
            'Sortie middleware_runner invalide : ' . json_encode($result)
        );
        $this->assertTrue(
            $result['next_called'],
            'pages/export.php autorise [Superviseur, Chsct] : le POST export doit accepter le CHSCT, '
            . 'sinon le CHSCT voit le formulaire puis se fait refuser au submit'
        );
        $this->assertNull(
            $result['redirect'],
            'Le CHSCT ne doit pas être redirigé sur le POST export'
        );
    }

    // ─── Non-régression : le Superviseur reste autorisé ─────────────────────

    public function testSuperviseurIsAllowedOnExportPost(): void
    {
        $result = $this->runExportPostAs(UserRole::Superviseur->value);

        $this->assertTrue(
            $result['next_called'],
            'Le Superviseur reste autorisé sur le POST export'
        );
        $this->assertNull($result['redirect'], 'Le Superviseur ne doit pas être redirigé');
    }

    // ─── Non-régression : un rôle non autorisé est toujours refusé ──────────

    public function testUnauthorizedRoleIsRefusedOnExportPost(): void
    {
        $result = $this->runExportPostAs(UserRole::Agent->value);

        $this->assertFalse(
            $result['next_called'],
            'Un Agent ne doit pas accéder au POST export'
        );
        $this->assertNotNull(
            $result['redirect'],
            'Un Agent doit être redirigé (accès refusé) sur le POST export'
        );
    }
}