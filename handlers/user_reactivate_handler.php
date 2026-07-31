<?php

use App\Services\HttpService;
use App\Services\SessionService;

/**
 * User Reactivate Handler — Application SST DREETS BFC
 *
 * POST handler: reactivate a previously deactivated user.
 * Access: superviseur only
 */

require_once __DIR__ . '/../src/bootstrap_services.php';

/** @var array<string, string> $_POST */

use App\Services\UserService;

$http = new HttpService();
$session = SessionService::getInstance();

$userId = (int) ($_POST['user_id'] ?? 0);

if ($userId <= 0) {
    $session->setFlash('error', 'Utilisateur introuvable.');
    $http->redirect($http->url('users'));
}

$service = getContainer()->get(UserService::class);

try {
    $service->reactivate($userId);

    $pdo = getDB();
    $user = $service->findById($userId);
    /** @var array<string, string> $user */
    $label = $user['prenom'] . ' ' . $user['nom'];
    auditLog($pdo, 'user', 'reactivate', 'Utilisateur réactivé : ' . $label, $userId, 'user');
    $session->setFlash('success', 'Utilisateur ' . e($label) . ' réactivé avec succès.');
} catch (RuntimeException $e) {
    // @silent-ok: handler boundary — flash error shown to the user, standard pattern.
    $session->setFlash('error', e($e->getMessage()));
}

$http->redirect($http->url('users'));
