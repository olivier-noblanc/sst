<?php
/**
 * Router Role Mapping Test — Application SST DREETS BFC
 *
 * Audit lifecycle (gap 3) — cohérence routes ↔ matrice ReportStateMachine :
 * la matrice autorise Traite/Abandonne → Reouvert pour [Superviseur, Chsct]
 * et l'UI expose le bouton Réouvrir au CHSCT, mais la route report_reopen
 * n'acceptait que Superviseur → le CHSCT voyait le bouton puis se faisait
 * refuser. La matrice est l'autorité : la route doit l'accepter aussi.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

class RouterRoleMappingTest extends TestCase
{
    public function testReopenRouteMiddlewareAllowsChsct(): void
    {
        $router = createRouter();
        $middlewares = $router->getPostMiddleware('report_reopen');

        $roles = null;
        foreach ($middlewares as $mw) {
            if ($mw instanceof \App\Middleware\RoleMiddleware) {
                $prop = new ReflectionProperty($mw, 'roles');
                $roles = $prop->getValue($mw);
            }
        }

        $this->assertNotNull($roles, 'report_reopen doit avoir un RoleMiddleware');
        $this->assertContains(
            \App\Enum\UserRole::Superviseur->value,
            $roles,
            'Le Superviseur reste autorisé à réouvrir'
        );
        $this->assertContains(
            \App\Enum\UserRole::Chsct->value,
            $roles,
            'La matrice (Traite/Abandonne→Reouvert [Superviseur, Chsct]) est l\'autorité : le CHSCT doit être accepté par la route, comme l\'UI l\'expose déjà'
        );
    }

    /**
     * Test runtime (Beta/B lane) — createRouter() doit démarrer sans erreur :
     * getHandlerMap() a momentanément référencé une propriété inexistante
     * ($handlerMap au lieu de $postHandlers) → TypeError à CHAQUE construction
     * du routeur (createRouter() est exécuté par getRouter() à chaque requête).
     * Ce test verrouille le contrat des trois accesseurs.
     */
    public function testCreateRouterBootsAndAccessorsReturnRealState(): void
    {
        $router = createRouter();

        $handlerMap = $router->getHandlerMap();
        $this->assertNotEmpty($handlerMap, 'getHandlerMap() doit retourner la carte réelle des handlers POST ($postHandlers)');
        $this->assertArrayHasKey('report_respond', $handlerMap);
        $this->assertArrayHasKey('report_reopen', $handlerMap);
        $this->assertArrayHasKey('user_delete', $handlerMap);

        $respondMiddlewares = $router->getPostMiddleware('report_respond');
        $this->assertCount(
            2,
            $respondMiddlewares,
            'report_respond doit avoir exactement [CsrfMiddleware, RoleMiddleware]'
        );
        $this->assertInstanceOf(\App\Middleware\CsrfMiddleware::class, $respondMiddlewares[0]);
        $this->assertInstanceOf(\App\Middleware\RoleMiddleware::class, $respondMiddlewares[1]);
    }
}
