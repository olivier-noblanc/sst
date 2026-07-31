<?php

use App\Services\HttpService;
use App\Services\SessionService;

/**
 * User Delete Handler — Application SST DREETS BFC
 *
 * POST handler: soft-delete (deactivate) a user.
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
    $service->deactivate($userId, (int)($session->getUserSession()['id'] ?? 0));

    $pdo = getDB();
    $user = $service->findById($userId);
    /** @var array<string, string> $user */
    $label = is_array($user) ? $user['prenom'] . ' ' . $user['nom'] : '(id=' . $userId . ')';
    auditLog($pdo, 'user', 'delete', 'Utilisateur désactivé : ' . $label, $userId, 'user');
    $session->setFlash('success', 'Utilisateur ' . e($label) . ' désactivé avec succès.');
} catch (RuntimeException $e) {
    // @silent-ok: handler boundary — flash error shown to the user, standard pattern.
    $session->setFlash('error', e($e->getMessage()));
}

$http->redirect($http->url('users'));
