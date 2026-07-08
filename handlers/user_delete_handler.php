<?php

/**
 * User Delete Handler — Application SST DREETS BFC
 *
 * POST handler: soft-delete (deactivate) a user.
 * Access: superviseur only
 */

require_once __DIR__ . '/../src/bootstrap_services.php';

use App\Services\UserService;

$userId = (int) ($_POST['user_id'] ?? 0);

if ($userId <= 0) {
    setFlash('error', 'Utilisateur introuvable.');
    redirect(url('users'));
}

$service = getContainer()->get(UserService::class);

try {
    $service->deactivate($userId, currentUserId());

    $pdo = getDB();
    $user = $service->findById($userId);
    auditLog($pdo, 'user', 'delete', 'Utilisateur désactivé : ' . $user['prenom'] . ' ' . $user['nom'], (int) $userId, 'user');
    setFlash('success', 'Utilisateur ' . e($user['prenom'] . ' ' . $user['nom']) . ' désactivé avec succès.');
} catch (\RuntimeException $e) {
    setFlash('error', e($e->getMessage()));
} catch (\Throwable $e) {
    error_log('[SST-DB] user_delete failed: ' . $e->getMessage());
    setFlash('error', 'Erreur lors de la désactivation de l\'utilisateur. Veuillez contacter un administrateur.');
}

redirect(url('users'));
