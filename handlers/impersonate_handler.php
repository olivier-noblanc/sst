<?php
/**
 * Impersonate Handler — Application SST DREETS BFC
 *
 * POST handler: switch the current user's role to agent or chsct (impersonation),
 * or restore their real role.
 * Access: superviseur only
 *
 * Security:
 *   - Only superviseurs can impersonate
 *   - Only lower-privilege roles (agent, chsct) can be impersonated
 *   - The real role is preserved in $_SESSION['real_role']
 *   - All actions are logged in the audit trail
 *   - Impersonation only changes the session, not the database
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('home'));
}

// Validate CSRF token
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Erreur de sécurité. Veuillez réessayer.');
    redirect(url('home'));
}

// Must be authenticated
if (!isset($_SESSION['user'])) {
    redirect(url('home'));
}

$action = $_POST['action'] ?? '';

// === START IMPERSONATION ===
if ($action === 'start') {
    $targetRole = $_POST['target_role'] ?? '';

    // Only superviseurs can impersonate (check real_role if already impersonating)
    $effectiveRole = $_SESSION['real_role'] ?? $_SESSION['user']['role'];
    if ($effectiveRole !== 'superviseur') {
        setFlash('error', 'Seuls les superviseurs peuvent incarner un autre rôle.');
        redirect(url('home'));
    }

    // Only allow impersonating agent or chsct
    if (!in_array($targetRole, ['agent', 'chsct'], true)) {
        setFlash('error', 'Rôle cible invalide. Seuls Agent et CSA/CHSCT peuvent être incarnés.');
        redirect(url('home'));
    }

    // Don't impersonate if already impersonating the same role
    if (isset($_SESSION['impersonated_role']) && $_SESSION['impersonated_role'] === $targetRole) {
        redirect(url('home'));
    }

    // Save the real role (only if not already impersonating)
    if (!isset($_SESSION['real_role'])) {
        $_SESSION['real_role'] = $_SESSION['user']['role'];
    }

    // Switch to the impersonated role
    $_SESSION['user']['role'] = $targetRole;
    $_SESSION['impersonated_role'] = $targetRole;

    // Audit log
    require_once __DIR__ . '/../src/audit.php';
    $pdo = getDB();
    auditLog($pdo, 'auth', 'impersonate_start', 'Incarnation du rôle ' . (ROLE_LABELS[$targetRole] ?? $targetRole), null, 'user', [
        'real_role'  => $_SESSION['real_role'],
        'impersonated_role' => $targetRole,
    ]);

    setFlash('info', 'Vous incarnez maintenant le rôle ' . (ROLE_LABELS[$targetRole] ?? $targetRole) . '.');
    redirect(url('home'));
}

// === STOP IMPERSONATION (restore real role) ===
if ($action === 'stop') {
    if (!isset($_SESSION['real_role'])) {
        // Not impersonating — nothing to do
        redirect(url('home'));
    }

    $realRole = $_SESSION['real_role'];
    $impersonatedRole = $_SESSION['impersonated_role'] ?? 'inconnu';

    // Restore the real role
    $_SESSION['user']['role'] = $realRole;
    unset($_SESSION['real_role']);
    unset($_SESSION['impersonated_role']);

    // Audit log
    require_once __DIR__ . '/../src/audit.php';
    $pdo = getDB();
    auditLog($pdo, 'auth', 'impersonate_stop', 'Fin d\'incarnation du rôle ' . (ROLE_LABELS[$impersonatedRole] ?? $impersonatedRole) . ' — retour au rôle ' . (ROLE_LABELS[$realRole] ?? $realRole), null, 'user', [
        'real_role'  => $realRole,
        'impersonated_role' => $impersonatedRole,
    ]);

    setFlash('success', 'Vous avez repris votre rôle de ' . (ROLE_LABELS[$realRole] ?? $realRole) . '.');
    redirect(url('home'));
}

// Unknown action
redirect(url('home'));
