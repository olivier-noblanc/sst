<?php
/**
 * User Delete Handler — Application SST DREETS BFC
 * 
 * POST handler: soft-delete (deactivate) a user.
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

// Prevent self-deletion
if ((int) $_SESSION['user']['id'] === $userId) {
    setFlash('error', 'Vous ne pouvez pas désactiver votre propre compte.');
    redirect(url('users'));
}

// Verify user exists
$user = getUserById($pdo, $userId);
if (!$user) {
    setFlash('error', 'Utilisateur introuvable.');
    redirect(url('users'));
}

// Soft delete — guard: prevent deactivating the last active superviseur
if ($user['role'] === 'superviseur') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'superviseur' AND is_active = 1");
    $stmt->execute();
    $activeSups = (int) $stmt->fetchColumn();
    if ($activeSups <= 1) {
        setFlash('error', 'Impossible de désactiver le dernier superviseur actif. Nommez un autre superviseur d\'abord.');
        redirect(url('users'));
    }
}

$success = deactivateUser($pdo, $userId);

if ($success) {
    require_once __DIR__ . '/../src/audit.php';
    auditLog($pdo, 'user', 'delete', 'Utilisateur désactivé : ' . $user['prenom'] . ' ' . $user['nom'], (int) $userId, 'user');
    setFlash('success', 'Utilisateur ' . e($user['prenom'] . ' ' . $user['nom']) . ' désactivé avec succès.');
} else {
    error_log('[SST-DB] user_delete failed for user_id=' . $userId);
    setFlash('error', 'Erreur lors de la désactivation de l\'utilisateur. (user_id=' . $userId . ')');
}

redirect(url('users'));
