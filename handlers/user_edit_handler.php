<?php
/**
 * User Edit Handler — Application SST DREETS BFC
 * 
 * POST handler: update user role/info.
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

$userId = (int) ($_POST['user_id'] ?? 0);
if ($userId <= 0) {
    $userId = (int) ($_GET['id'] ?? 0);
}

if ($userId <= 0) {
    setFlash('error', 'Utilisateur introuvable.');
    redirect(url('users'));
}

$pdo = getDB();

// Verify user exists
$user = getUserById($pdo, $userId);
if (!$user) {
    setFlash('error', 'Utilisateur introuvable.');
    redirect(url('users'));
}

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
} elseif ($username !== $user['username']) {
    // Check if username is unique
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username AND id != :id");
    $stmt->execute([':username' => $username, ':id' => $userId]);
    if ($stmt->fetch()) {
        $errors['username'] = 'Cet identifiant est déjà utilisé.';
    }
}

$role = trim($_POST['role'] ?? '');
if (!in_array($role, ['agent', 'superviseur', 'chsct'])) {
    $errors['role'] = 'Rôle invalide.';
}

// Guard: prevent demoting the last active superviseur
if ($user['role'] === 'superviseur' && $role !== 'superviseur') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'superviseur' AND is_active = 1");
    $stmt->execute();
    $activeSups = (int) $stmt->fetchColumn();
    if ($activeSups <= 1) {
        $errors['role'] = 'Impossible de rétrograder le dernier superviseur actif. Nommez un autre superviseur d\'abord.';
    }
}

$siteId = (int) ($_POST['site_id'] ?? 0);
if ($siteId <= 0) {
    $errors['site_id'] = 'Le site est requis.';
}

$email = trim($_POST['email'] ?? '');
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Adresse email invalide.';
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

    setFlash('success', 'Utilisateur ' . e($prenom . ' ' . $nom) . ' mis à jour avec succès.');
} catch (Exception $e) {
    $pdo->rollBack();
    setFlash('error', 'Erreur lors de la mise à jour de l\'utilisateur.');
}

redirect(url('users'));
