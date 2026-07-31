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

$sessionUser = $session->getUserSession();
if ($sessionUser === null) {
    $session->setFlash('error', 'Session invalide.');
    $http->redirect($http->url('home'));
    return;
}

$service = getContainer()->get(UserService::class);

try {
    $service->deactivate($userId, $sessionUser->id);

    $pdo = getDB();
    $user = $service->findById($userId);
    if ($user === null) {
        $session->setFlash('error', 'Utilisateur introuvable.');
        $http->redirect($http->url('users'));
        return;
    }
    $label = $user->prenom . ' ' . $user->nom;
    auditLog($pdo, 'user', 'delete', 'Utilisateur désactivé : ' . $label, $userId, 'user');
    $session->setFlash('success', 'Utilisateur ' . e($label) . ' désactivé avec succès.');
} catch (RuntimeException $e) {
    // @silent-ok: handler boundary — flash error shown to the user, standard pattern.
    $session->setFlash('error', e($e->getMessage()));
}

$http->redirect($http->url('users'));
