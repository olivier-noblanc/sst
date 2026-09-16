<?php

use App\DTO\FormData;
use App\Services\HttpService;
use App\Services\SessionService;
use App\Enum\UserRole;
use App\Services\NotificationService;
use App\Repository\TransactionManager;

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

$http = new HttpService();
$session = SessionService::getInstance();

$userId = (int) ($_POST['user_id'] ?? 0);
if ($userId <= 0) {
    $getId = $_GET['id'] ?? '0';
    $userId = (int) $getId;
}

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
    // Audit #8 — anonymize() returned false silently when NOT NULL constraint
    // failed on report_responses.user_id. The handler ignored the return value
    // and showed a success flash. Now we test the return + audit log is written
    // only if anonymization actually succeeded.
    $success = $service->anonymize($userId, $sessionUser->id);
    if ($success) {
        auditLog($pdo, 'gdpr', 'anonymize', 'Anonymisation RGPD de l\'utilisateur ID ' . $userId, $userId, 'user');
        $session->setFlash('success', 'Données personnelles de l\'utilisateur anonymisées conformément au RGPD.');
    } else {
        auditLog($pdo, 'gdpr', 'anonymize_failed', 'Échec anonymisation RGPD de l\'utilisateur ID ' . $userId, $userId, 'user');
        $session->setFlash('error', 'L\'anonymisation a échoué. Consultez les logs serveur (probablement une contrainte NOT NULL non encore migrée).');
    }
    $http->redirect($http->url('user_view', ['id' => $userId]));
}

// Verify user exists
$user = $service->findById($userId);
if ($user === null) {
    $session->setFlash('error', 'Utilisateur introuvable.');
    $http->redirect($http->url('users'));
    return;
}

// Validate
$cmd = UpdateUserCommand::fromPost($_POST);

$errors = $service->validate($cmd, $userId);

// Guard: prevent demoting the last active superviseur
if ($user->role === UserRole::Superviseur->value && $cmd->role !== UserRole::Superviseur->value) {
    $demoteErrors = $service->canDemote($userId, $cmd->role, $user->role);
    $errors = array_merge($errors, $demoteErrors);

    if ($cmd->role === UserRole::Agent->value && empty($_POST['confirm_demotion'])) {
        $errors['role'] = 'Veuillez confirmer la rétrogradation en cochant la case de confirmation.';
    }
}

if (!empty($errors)) {
    setFormErrors($errors);
    setFormData(FormData::fromPost($_POST));
    $http->redirect($http->url('user_edit', ['id' => $userId]));
}

// Update user
$oldRole = $user->role;
$roleChanged = ($cmd->role !== $oldRole);
$notifyRoleChange = ($roleChanged && !empty($_POST['notify_role_change']) && !empty($cmd->email));

// Bug #30 / Fiabilisation (council) — chemin UNIQUE de notification : ce
// handler via NotificationService (checkbox notify_role_change respectée).
// Atomicité : la modification du rôle et la mise en file de sa notification
// partagent UNE transaction — un échec d'enqueue annule la modification (pas
// de changement de rôle silencieux sans notification).
$notifications = getContainer()->get(NotificationService::class);
$emailSent = false;
$emailError = '';
try {
    $transactionManager = new TransactionManager($pdo);
    $transactionManager->run(
        function () use ($service, $userId, $cmd, $sessionUser, $notifyRoleChange, $oldRole, $notifications, &$emailSent): void {
            $service->update($userId, $cmd, $sessionUser->id);
            if (!$notifyRoleChange) {
                return;
            }
            // Décision Oracle SMTP — on CONSOMME le verdict bool de
            // notifyRoleChange(). eventKey : identité d'OCCURRENCE générée UNE
            // fois pour cette transition (cycles A→B→A→B sans collision).
            $emailSent = $notifications->notifyRoleChange($userId, $oldRole, $cmd->role, bin2hex(random_bytes(16)));
        },
        fn() => $notifications->flushOutbox(),
    );
} catch (Throwable $e) {
    // @silent-ok: handler boundary — la transaction a été annulée (rôle non
    // modifié, notification non perdue) ; l'échec est surfacé à l'utilisateur,
    // jamais avalé silencieusement.
    $emailError = $e->getMessage();
    error_log('[SST-MAIL] user update/notifyRoleChange failed: ' . $emailError);
    $session->setFlash('error', 'La modification a échoué : ' . e($emailError) . ' Aucune donnée n\'a été enregistrée.');
    $http->redirect($http->url('user_edit', ['id' => $userId]));
}

// Audit log APRÈS l'envoi — reflète l'état réel
auditLog($pdo, 'user', 'edit', 'Utilisateur modifié : ' . $cmd->prenom . ' ' . $cmd->nom, $userId, 'user', [
    'role' => $cmd->role,
    'role_changed' => $roleChanged,
    'notified' => $notifyRoleChange,
    'email_sent' => $emailSent,
    'email_error' => $emailError !== '' ? $emailError : null,
]);

$successMsg = 'Utilisateur ' . e($cmd->prenom . ' ' . $cmd->nom) . ' mis à jour avec succès.';
if ($notifyRoleChange && $emailSent) {
    $successMsg .= ' Un e-mail de notification a été mis en file d\'envoi pour ' . e($cmd->email) . '.';
} elseif ($notifyRoleChange) {
    // $emailSent is false here (if it were true, the if above would have matched)
    $successMsg .= " ⚠ Le rôle a changé mais la notification n'a pas pu être mise en file d'envoi (" . e($emailError) . "). L'utilisateur devra être informé manuellement.";
} elseif ($roleChanged) {
    // $notifyRoleChange is false here, which means $cmd->email is empty
    $successMsg .= " ⚠ Le rôle a changé mais aucun e-mail n'a été envoyé (adresse manquante).";
}
$flashType = 'success';
if ($notifyRoleChange && !$emailSent) {
    $flashType = 'warning';
}
$session->setFlash($flashType, $successMsg);

$http->redirect($http->url('users'));
