<?php

/**
 * User Delete Handler — Application SST DREETS BFC
 *
 * POST handler: soft-delete (deactivate) a user.
 * Access: superviseur only
 */

require_once __DIR__ . '/../src/bootstrap_services.php';

/** @var array<string, string> $_POST */

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
    /** @var array<string, string> $user */
    $label = is_array($user) ? $user['prenom'] . ' ' . $user['nom'] : '(id=' . $userId . ')';
    auditLog($pdo, 'user', 'delete', 'Utilisateur désactivé : ' . $label, $userId, 'user');
    setFlash('success', 'Utilisateur ' . e($label) . ' désactivé avec succès.');
} catch (RuntimeException $e) {
    setFlash('error', e($e->getMessage()));
}

redirect(url('users'));
