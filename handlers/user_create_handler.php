<?php

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

$service = getContainer()->get(UserService::class);
$cmd = CreateUserCommand::fromPost($_POST);

$errors = $service->validate($_POST);

if (!empty($errors)) {
    setFormErrors($errors);
    setFormData($_POST);
    redirect(url('users', ['tab' => 'create']));
}

$newId = $service->create($cmd);

$pdo = getDB();
auditLog($pdo, 'user', 'create', 'Utilisateur créé : ' . $cmd->prenom . ' ' . $cmd->nom, (int) $newId, 'user', ['username' => $cmd->username, 'role' => $cmd->role]);
setFlash('success', 'Utilisateur ' . e($cmd->prenom . ' ' . $cmd->nom) . ' créé avec succès (ID: ' . $newId . ').');

redirect(url('users'));
