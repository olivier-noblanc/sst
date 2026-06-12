<?php
/**
 * User Reactivate Handler — Application SST DREETS BFC
 *
 * POST handler: reactivate a previously deactivated user.
 * Access: superviseur only
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('users'));
}

// Validate CSRF token
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Erreur de sécurité. Veuillez réessayer.');
    redirect(url('users'));
}

// Check role
if (!hasRole('superviseur')) {
    setFlash('error', 'Vous n\'avez pas les permissions nécessaires.');
    redirect(url('home'));
}

$userId = (int) ($_POST['user_id'] ?? 0);

if ($userId <= 0) {
    setFlash('error', 'Utilisateur introuvable.');
    redirect(url('users'));
}

$pdo = getDB();

// Verify user exists and is currently inactive
$user = getUserById($pdo, $userId);
if (!$user) {
    setFlash('error', 'Utilisateur introuvable.');
    redirect(url('users'));
}

if ($user['is_active']) {
    setFlash('warning', 'Cet utilisateur est déjà actif.');
    redirect(url('users'));
}

// Reactivate
$success = reactivateUser($pdo, $userId);

if ($success) {
    require_once __DIR__ . '/../src/audit.php';
    auditLog($pdo, 'user', 'reactivate', 'Utilisateur réactivé : ' . $user['prenom'] . ' ' . $user['nom'], (int) $userId, 'user');
    setFlash('success', 'Utilisateur ' . e($user['prenom'] . ' ' . $user['nom']) . ' réactivé avec succès.');
} else {
    error_log('[SST-DB] user_reactivate failed for user_id=' . $userId);
    setFlash('error', 'Erreur lors de la réactivation de l\'utilisateur. (user_id=' . $userId . ')');
}

redirect(url('users'));
