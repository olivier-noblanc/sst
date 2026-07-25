<?php

use App\Enum\UserRole;

/**
 * Impersonate Handler — Application SST DREETS BFC
 *
 * POST handler: switch the current user's role to agent or chsct (impersonation),
 * or restore their real role.
 * Access: superviseur only (checked inline — custom logic)
 *
 * Security:
 *   - Only superviseurs can impersonate
 *   - Only lower-privilege roles (agent, chsct) can be impersonated
 *   - The real role is preserved in session via startImpersonation()
 *   - All actions are logged in the audit trail
 *   - Impersonation only changes the session, not the database
 */

require_once __DIR__ . '/../src/bootstrap_services.php';

/** @var array<string, string> $_POST */

use App\Services\SessionManager;

$session = getContainer()->get(SessionManager::class);
$http = new \App\Services\HttpService();
$sessionService = \App\Services\SessionService::getInstance();

// Must be authenticated
if (!$session->isLoggedIn()) {
    $http->redirect($http->url('home'));
}

$action = (string) ($_POST['action'] ?? '');

// === START IMPERSONATION ===
if ($action === 'start') {
    $targetRole = (string) ($_POST['target_role'] ?? '');

    // Only superviseurs can impersonate (check real_role if already impersonating)
    $effectiveRole = $session->getRealRole() ?? currentUserRole();
    if ($effectiveRole !== UserRole::Superviseur->value) {
        $sessionService->setFlash('error', 'Seuls les superviseurs peuvent incarner un autre rôle.');
        $http->redirect($http->url('home'));
    }

    // Only allow impersonating agent or chsct
    if (!in_array($targetRole, [UserRole::Agent->value, UserRole::Chsct->value], true)) {
        $sessionService->setFlash('error', 'Rôle cible invalide. Seuls Agent et ' . getRoleLabelShort('chsct') . ' peuvent être incarnés.');
        $http->redirect($http->url('home'));
    }

    // Don't impersonate if already impersonating the same role
    if ($session->getImpersonatedRole() === $targetRole) {
        $http->redirect($http->url('home'));
    }

    // Save the real role and switch to the impersonated role
    $realRole = $session->getRealRole() ?? currentUserRole();
    $session->startImpersonation($realRole, $targetRole);

    // Audit log
    $pdo = getDB();
    auditLog($pdo, 'auth', 'impersonate_start', 'Incarnation du rôle ' . (ROLE_LABELS[$targetRole] ?? $targetRole), null, 'user', [
        'real_role'  => $realRole,
        'impersonated_role' => $targetRole,
    ]);

    $sessionService->setFlash('info', 'Vous incarnez maintenant le rôle ' . (ROLE_LABELS[$targetRole] ?? $targetRole) . '.');
    $http->redirect($http->url('home'));
}

// === STOP IMPERSONATION (restore real role) ===
if ($action === 'stop') {
    $impersonatedRole = $session->getImpersonatedRole() ?? 'inconnu';
    $realRole = $session->stopImpersonation();
    if ($realRole === null) {
        // Not impersonating — nothing to do
        $http->redirect($http->url('home'));
    }

    // Audit log
    $pdo = getDB();
    /** @var string $realRole */
    auditLog($pdo, 'auth', 'impersonate_stop', 'Fin d\'incarnation du rôle ' . (ROLE_LABELS[$impersonatedRole] ?? $impersonatedRole) . ' — retour au rôle ' . (ROLE_LABELS[$realRole] ?? $realRole), null, 'user', [
        'real_role'  => $realRole,
        'impersonated_role' => $impersonatedRole,
    ]);

    $sessionService->setFlash('success', 'Vous avez repris votre rôle de ' . (ROLE_LABELS[$realRole] ?? $realRole) . '.');
    $http->redirect($http->url('home'));
}

// Unknown action
$http->redirect($http->url('home'));
