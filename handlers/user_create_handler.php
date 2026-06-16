<?php
/**
 * User Create Handler — Application SST DREETS BFC
 * 
 * POST handler: create a new user.
 * Access: superviseur only
 */

validatePostRequest(url('users'), [ROLE_SUPERVISEUR]);

$pdo = getDB();

// Validate required fields
$errors = validateUserFields($pdo, $_POST);

$nom = trim($_POST['nom'] ?? '');
$prenom = trim($_POST['prenom'] ?? '');
$username = trim($_POST['username'] ?? '');
$role = trim($_POST['role'] ?? '');
$siteId = (int) ($_POST['site_id'] ?? 0);
$email = trim($_POST['email'] ?? '');

if (!empty($errors)) {
    setFormErrors($errors);
    setFormData($_POST);
    redirect(url('users', ['tab' => 'create']));
}

// Create user
try {
    $newId = createUser($pdo, [
        'nom'      => $nom,
        'prenom'   => $prenom,
        'email'    => !empty($email) ? $email : null,
        'username' => $username,
        'role'     => $role,
        'site_id'  => $siteId,
    ]);

    auditLog($pdo, 'user', 'create', 'Utilisateur créé : ' . $prenom . ' ' . $nom, (int) $newId, 'user', ['username' => $username, 'role' => $role]);
    setFlash('success', 'Utilisateur ' . e($prenom . ' ' . $nom) . ' créé avec succès (ID: ' . $newId . ').');
} catch (Exception $e) {
    error_log('[SST-DB] user_create failed: ' . $e->getMessage());
    setFlash('error', 'Erreur lors de la création de l\'utilisateur : ' . e($e->getMessage()));
    setFormData($_POST);
    redirect(url('users', ['tab' => 'create']));
}

redirect(url('users'));
