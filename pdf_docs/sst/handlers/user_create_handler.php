<?php
/**
 * User Create Handler — Application SST DREETS BFC
 * 
 * POST handler: create a new user.
 * Access: superviseur only
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('users'));
}

// Validate CSRF token
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Erreur de sécurité. Veuillez réessayer.');
    redirect(url('users'));
}

// Check role
if (!hasRole('superviseur')) {
    setFlash('error', 'Vous n\'avez pas les permissions nécessaires.');
    redirect(url('home'));
}

$pdo = getDB();

// Validate required fields
$errors = [];

$nom = trim($_POST['nom'] ?? '');
if (empty($nom)) {
    $errors['nom'] = 'Le nom est requis.';
}

$prenom = trim($_POST['prenom'] ?? '');
if (empty($prenom)) {
    $errors['prenom'] = 'Le prénom est requis.';
}

$username = trim($_POST['username'] ?? '');
if (empty($username)) {
    $errors['username'] = 'L\'identifiant est requis.';
} else {
    // Check if username is unique
    $existing = getUserByUsername($pdo, $username);
    if ($existing) {
        $errors['username'] = 'Cet identifiant est déjà utilisé.';
    }
}

$role = trim($_POST['role'] ?? '');
if (!in_array($role, ['agent', 'manager', 'superviseur', 'chsct'])) {
    $errors['role'] = 'Rôle invalide.';
}

$siteId = (int) ($_POST['site_id'] ?? 0);
if ($siteId <= 0) {
    $errors['site_id'] = 'Le site est requis.';
}

$email = trim($_POST['email'] ?? '');
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Adresse email invalide.';
}

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

    setFlash('success', 'Utilisateur ' . e($prenom . ' ' . $nom) . ' créé avec succès (ID: ' . $newId . ').');
} catch (Exception $e) {
    setFlash('error', 'Erreur lors de la création de l\'utilisateur. Veuillez réessayer.');
    setFormData($_POST);
    redirect(url('users', ['tab' => 'create']));
}

redirect(url('users'));
