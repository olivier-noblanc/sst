<?php

use App\Services\HttpService;
use App\Services\SessionService;

/**
 * User Create Handler — Application SST DREETS BFC
 *
 * POST handler: create a new user.
 * Access: superviseur only
 */

require_once __DIR__ . '/../src/bootstrap_services.php';

/** @var array<string, string> $_POST */

use App\DTO\CreateUserCommand;
use App\Services\UserService;

$http = new HttpService();
$session = SessionService::getInstance();

$service = getContainer()->get(UserService::class);
$cmd = CreateUserCommand::fromPost($_POST);

$errors = $service->validate($cmd);

if (!empty($errors)) {
    setFormErrors($errors);
    setFormData($_POST);
    $http->redirect($http->url('users', ['tab' => 'create']));
}

$newId = $service->create($cmd);

$pdo = getDB();
auditLog($pdo, 'user', 'create', 'Utilisateur créé : ' . $cmd->prenom . ' ' . $cmd->nom, (int) $newId, 'user', ['username' => $cmd->username, 'role' => $cmd->role]);
$session->setFlash('success', 'Utilisateur ' . e($cmd->prenom . ' ' . $cmd->nom) . ' créé avec succès (ID: ' . $newId . ').');

$http->redirect($http->url('users'));
