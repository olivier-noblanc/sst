<?php

use App\Enum\UserRole;

/**
 * User Edit Handler — Application SST DREETS BFC
 *
 * POST handler: update user role/info.
 * Access: superviseur only
 */

require_once __DIR__ . '/../src/bootstrap_services.php';

/** @var array<string, string> $_POST */

use App\DTO\UpdateUserCommand;
use App\Services\UserService;

$http = new \App\Services\HttpService();
$session = \App\Services\SessionService::getInstance();

$userId = (int) ($_POST['user_id'] ?? 0);
if ($userId <= 0) {
    $getId = $_GET['id'] ?? '0';
    $userId = (int) $getId;
}

if ($userId <= 0) {
    $session->setFlash('error', 'Utilisateur introuvable.');
    $http->redirect($http->url('users'));
}

$pdo = getDB();
$service = getContainer()->get(UserService::class);

// Handle GDPR actions (export_data, anonymize)
$action = (string) ($_POST['action'] ?? '');
if ($action === 'export_data') {
    $userData = $service->exportData($userId);
    auditLog($pdo, 'gdpr', 'data_export', 'Export RGPD des données de l\'utilisateur ID ' . $userId, $userId, 'user');
    $filename = 'rgpd_export_user_' . $userId . '_' . date('Y-m-d') . '.json';
    $json = json_encode($userData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (is_string($json)) {
        sendFileDownload($json, $filename, 'application/json; charset=utf-8');
    }
}

if ($action === 'anonymize') {
    $service->anonymize($userId, (int)($session->getUserSession()['id'] ?? 0));
    auditLog($pdo, 'gdpr', 'anonymize', 'Anonymisation RGPD de l\'utilisateur ID ' . $userId, $userId, 'user');
    $session->setFlash('success', 'Données personnelles de l\'utilisateur anonymisées conformément au RGPD.');
    $http->redirect($http->url('user_view', ['id' => $userId]));
}

// Verify user exists
$user = $service->findById($userId);
if ($user === null) {
    $session->setFlash('error', 'Utilisateur introuvable.');
    $http->redirect($http->url('users'));
}

/** @var array<string, string> $user */

// Validate
$errors = $service->validate($_POST, $userId);

$cmd = UpdateUserCommand::fromPost($_POST);

// Guard: prevent demoting the last active superviseur
if (is_array($user) && (string) ($user['role'] ?? '') === UserRole::Superviseur->value && $cmd->role !== UserRole::Superviseur->value) {
    $demoteErrors = $service->canDemote($userId, $cmd->role, $user);
    $errors = array_merge($errors, $demoteErrors);

    if ($cmd->role === UserRole::Agent->value && empty($_POST['confirm_demotion'])) {
        $errors['role'] = 'Veuillez confirmer la rétrogradation en cochant la case de confirmation.';
    }
}

if (!empty($errors)) {
    setFormErrors($errors);
    setFormData($_POST);
    $http->redirect($http->url('user_edit', ['id' => $userId]));
}

// Update user
$oldRole = (string) ($user['role'] ?? '');
$roleChanged = ($cmd->role !== $oldRole);
$notifyRoleChange = ($roleChanged && !empty($_POST['notify_role_change']) && !empty($cmd->email));

$service->update($userId, $cmd, (int)($session->getUserSession()['id'] ?? 0));

auditLog($pdo, 'user', 'edit', 'Utilisateur modifié : ' . $cmd->prenom . ' ' . $cmd->nom, $userId, 'user', ['role' => $cmd->role, 'role_changed' => $roleChanged, 'notified' => $notifyRoleChange]);

if ($notifyRoleChange) {
    require_once __DIR__ . '/../src/mail.php';
    notifyRoleChange($pdo, $userId, $oldRole, $cmd->role);
}

$successMsg = 'Utilisateur ' . e($cmd->prenom . ' ' . $cmd->nom) . ' mis à jour avec succès.';
if ($notifyRoleChange) {
    $successMsg .= ' Un e-mail de notification a été envoyé à ' . e($cmd->email) . '.';
} elseif ($roleChanged && empty($cmd->email)) {
    $successMsg .= ' ⚠ Le rôle a changé mais aucun e-mail n\'a été envoyé (adresse manquante).';
}
$session->setFlash('success', $successMsg);

$http->redirect($http->url('users'));
