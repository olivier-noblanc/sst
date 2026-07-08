<?php

/**
 * User Reactivate Handler — Application SST DREETS BFC
 *
 * POST handler: reactivate a previously deactivated user.
 * Access: superviseur only
 */

require_once __DIR__ . '/../src/bootstrap_services.php';

use App\Services\UserService;

validatePostRequest(url('users'), [ROLE_SUPERVISEUR]);

$userId = (int) ($_POST['user_id'] ?? 0);

if ($userId <= 0) {
    setFlash('error', 'Utilisateur introuvable.');
    redirect(url('users'));
}

$service = getContainer()->get(UserService::class);

try {
    $service->reactivate($userId);

    $pdo = getDB();
    $user = $service->findById($userId);
    auditLog($pdo, 'user', 'reactivate', 'Utilisateur réactivé : ' . $user['prenom'] . ' ' . $user['nom'], (int) $userId, 'user');
    setFlash('success', 'Utilisateur ' . e($user['prenom'] . ' ' . $user['nom']) . ' réactivé avec succès.');
} catch (\RuntimeException $e) {
    setFlash('error', e($e->getMessage()));
} catch (\Throwable $e) {
    error_log('[SST-DB] user_reactivate failed: ' . $e->getMessage());
    setFlash('error', 'Erreur lors de la réactivation de l\'utilisateur. (user_id=' . $userId . ')');
}

redirect(url('users'));
