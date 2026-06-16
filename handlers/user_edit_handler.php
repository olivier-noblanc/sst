<?php
/**
 * User Edit Handler — Application SST DREETS BFC
 * 
 * POST handler: update user role/info.
 * Access: superviseur only
 */

validatePostRequest(url('users'), ['superviseur']);

$userId = (int) ($_POST['user_id'] ?? 0);
if ($userId <= 0) {
    $userId = (int) ($_GET['id'] ?? 0);
}

if ($userId <= 0) {
    setFlash('error', 'Utilisateur introuvable.');
    redirect(url('users'));
}

$pdo = getDB();

// Handle GDPR actions (export_data, anonymize)
$action = $_POST['action'] ?? '';
if ($action === 'export_data') {
    $userData = exportUserData($pdo, $userId);
    auditLog($pdo, 'gdpr', 'data_export', 'Export RGPD des données de l\'utilisateur ID ' . $userId, $userId, 'user');

    // Generate JSON export as download
    $filename = 'rgpd_export_user_' . $userId . '_' . date('Y-m-d') . '.json';
    $json = json_encode($userData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    sendFileDownload($json, $filename, 'application/json; charset=utf-8');
}

if ($action === 'anonymize') {
    $success = anonymizeUser($pdo, $userId);
    if ($success) {
        auditLog($pdo, 'gdpr', 'anonymize', 'Anonymisation RGPD de l\'utilisateur ID ' . $userId, $userId, 'user');
        setFlash('success', 'Données personnelles de l\'utilisateur anonymisées conformément au RGPD.');
    } else {
        error_log('[SST-DB] anonymizeUser failed for user_id=' . $userId);
        setFlash('error', 'Erreur lors de l\'anonymisation de l\'utilisateur. (user_id=' . $userId . ')');
    }
    redirect(url('user_view', ['id' => $userId]));
}

// Verify user exists
$user = getUserById($pdo, $userId);
if (!$user) {
    setFlash('error', 'Utilisateur introuvable.');
    redirect(url('users'));
}

// Validate required fields
$errors = validateUserFields($pdo, $_POST, $userId);

$nom = trim($_POST['nom'] ?? '');
$prenom = trim($_POST['prenom'] ?? '');
$username = trim($_POST['username'] ?? '');
$role = trim($_POST['role'] ?? '');
$siteId = (int) ($_POST['site_id'] ?? 0);
$email = trim($_POST['email'] ?? '');

// Guard: prevent demoting the last active superviseur
if ($user['role'] === 'superviseur' && $role !== 'superviseur') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'superviseur' AND is_active = 1");
    $stmt->execute();
    $activeSups = (int) $stmt->fetchColumn();
    if ($activeSups <= 1) {
        $errors['role'] = 'Impossible de rétrograder le dernier superviseur actif. Nommez un autre superviseur d\'abord.';
    }
}

// Note: No password field — auth is via IIS Windows Authentication

if (!empty($errors)) {
    setFormErrors($errors);
    setFormData($_POST);
    redirect(url('user_edit', ['id' => $userId]));
}

// Update user
try {
    $pdo->beginTransaction();

    $oldRole = $user['role'];
    $roleChanged = ($role !== $oldRole);
    $notifyRoleChange = ($roleChanged && !empty($_POST['notify_role_change']) && !empty($email));

    // Update main fields using query function
    updateUser($pdo, $userId, [
        'nom'      => $nom,
        'prenom'   => $prenom,
        'email'    => !empty($email) ? $email : null,
        'username' => $username,
        'role'     => $role,
        'site_id'  => $siteId,
    ]);

    // Note: No password field in current schema — mock auth doesn't use passwords.

    $pdo->commit();

    // Update session if editing self — re-read full user with site info
    if ((int) $_SESSION['user']['id'] === $userId) {
        $freshUser = getUserById($pdo, $userId);
        if ($freshUser) {
            $_SESSION['user'] = $freshUser;
        }
    }

    auditLog($pdo, 'user', 'edit', 'Utilisateur modifié : ' . $prenom . ' ' . $nom, (int) $userId, 'user', ['role' => $role, 'role_changed' => $roleChanged, 'notified' => $notifyRoleChange]);

    // Send email notification for role change (non-blocking — errors are logged, not shown to user)
    if ($notifyRoleChange) {
        try {
            require_once __DIR__ . '/../src/mail.php';
            notifyRoleChange($pdo, $userId, $oldRole, $role);
        } catch (Exception $mailEx) {
            error_log('[SST-MAIL] Role change notification error: ' . $mailEx->getMessage());
        }
    }

    $successMsg = 'Utilisateur ' . e($prenom . ' ' . $nom) . ' mis à jour avec succès.';
    if ($notifyRoleChange) {
        $successMsg .= ' Un e-mail de notification a été envoyé à ' . e($email) . '.';
    } elseif ($roleChanged && empty($email)) {
        $successMsg .= ' ⚠ Le rôle a changé mais aucun e-mail n\'a été envoyé (adresse manquante).';
    }
    setFlash('success', $successMsg);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('[SST-DB] user_edit failed: ' . $e->getMessage());
    setFlash('error', 'Erreur lors de la mise à jour de l\'utilisateur : ' . e($e->getMessage()));
}

redirect(url('users'));
