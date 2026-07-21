<?php

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

$userId = (int) ($_POST['user_id'] ?? 0);
if ($userId <= 0) {
    $getId = $_GET['id'] ?? '0';
    $userId = (int) $getId;
}

if ($userId <= 0) {
    setFlash('error', 'Utilisateur introuvable.');
    redirect(url('users'));
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
    try {
        $service->anonymize($userId, currentUserId());
        auditLog($pdo, 'gdpr', 'anonymize', 'Anonymisation RGPD de l\'utilisateur ID ' . $userId, $userId, 'user');
        setFlash('success', 'Données personnelles de l\'utilisateur anonymisées conformément au RGPD.');
    } catch (Throwable) {
        error_log('[SST-DB] anonymizeUser failed for user_id=' . $userId);
        setFlash('error', 'Erreur lors de l\'anonymisation de l\'utilisateur. (user_id=' . $userId . ')');
    }
    redirect(url('user_view', ['id' => $userId]));
}

// Verify user exists
$user = $service->findById($userId);
if ($user === null) {
    setFlash('error', 'Utilisateur introuvable.');
    redirect(url('users'));
}

/** @var array<string, string> $user */

// Validate
$errors = $service->validate($_POST, $userId);

$cmd = UpdateUserCommand::fromPost($_POST);

error_log('[SST-DEBUG] user_edit POST: user_id=' . $userId . ' role=' . $cmd->role . ' site_id=' . $cmd->siteId . ' nom=' . $cmd->nom . ' prenom=' . $cmd->prenom . ' errors=' . json_encode($errors));

// Guard: prevent demoting the last active superviseur
if (is_array($user) && (string) ($user['role'] ?? '') === ROLE_SUPERVISEUR && $cmd->role !== ROLE_SUPERVISEUR) {
    $demoteErrors = $service->canDemote($userId, $cmd->role, $user);
    $errors = array_merge($errors, $demoteErrors);

    if ($cmd->role === ROLE_AGENT && empty($_POST['confirm_demotion'])) {
        $errors['role'] = 'Veuillez confirmer la rétrogradation en cochant la case de confirmation.';
    }
}

if (!empty($errors)) {
    error_log('[SST-DEBUG] user_edit VALIDATION ERRORS: ' . json_encode($errors));
    setFormErrors($errors);
    setFormData($_POST);
    redirect(url('user_edit', ['id' => $userId]));
}

// Update user
try {
    $oldRole = (string) ($user['role'] ?? '');
    $roleChanged = ($cmd->role !== $oldRole);
    $notifyRoleChange = ($roleChanged && !empty($_POST['notify_role_change']) && !empty($cmd->email));

    $service->update($userId, $cmd, currentUserId());

    error_log('[SST-DEBUG] user_edit UPDATE SUCCESS for user_id=' . $userId . ' role=' . $cmd->role);

    auditLog($pdo, 'user', 'edit', 'Utilisateur modifié : ' . $cmd->prenom . ' ' . $cmd->nom, $userId, 'user', ['role' => $cmd->role, 'role_changed' => $roleChanged, 'notified' => $notifyRoleChange]);

    if ($notifyRoleChange) {
        try {
            require_once __DIR__ . '/../src/mail.php';
            notifyRoleChange($pdo, $userId, $oldRole, $cmd->role);
        } catch (Throwable $mailEx) {
            error_log('[SST-MAIL] Role change notification error: ' . $mailEx->getMessage());
        }
    }

    $successMsg = 'Utilisateur ' . e($cmd->prenom . ' ' . $cmd->nom) . ' mis à jour avec succès.';
    if ($notifyRoleChange) {
        $successMsg .= ' Un e-mail de notification a été envoyé à ' . e($cmd->email) . '.';
    } elseif ($roleChanged && empty($cmd->email)) {
        $successMsg .= ' ⚠ Le rôle a changé mais aucun e-mail n\'a été envoyé (adresse manquante).';
    }
    setFlash('success', $successMsg);
} catch (Throwable $e) {
    error_log('[SST-DB] user_edit failed: ' . $e->getMessage());
    error_log('[SST-DEBUG] user_edit UPDATE FAILED for user_id=' . $userId . ' error=' . $e->getMessage());
    setFlash('error', 'Erreur lors de la mise à jour de l\'utilisateur : ' . e($e->getMessage()));
}

redirect(url('users'));
