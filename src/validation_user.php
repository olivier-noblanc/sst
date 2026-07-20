<?php

/**
 * User Validation Functions — Application SST DREETS BFC
 *
 * Shared user validation and superviseur guard logic extracted from handlers.
 * Eliminates duplication between user_create_handler and user_edit_handler.
 *
 * Split from src/validation.php to keep each file under 250 lines.
 *
 * Before this split, the same validation code was copy-pasted across handlers
 * (~40 lines duplicated between create/edit user).
 */

// ============================================================================
// User Validation
// ============================================================================

/**
 * Validate common user fields (nom, prenom, username, role, site_id, email).
 *
 * Shared between user_create_handler and user_edit_handler.
 * Before this function, the same ~40 lines of validation were duplicated.
 *
 * @param PDO    $pdo       Database connection
 * @param array<string, mixed> $input     POST data (nom, prenom, username, role, site_id, email)
 * @param int    $excludeId User ID to exclude from uniqueness check (for edit)
 * @return array<string, string> Validation errors (field => message)
 */
function validateUserFields(PDO $pdo, array $input, int $excludeId = 0): array
{
    $errors = [];

    $nom = trim($input['nom'] ?? '');
    if (empty($nom)) {
        $errors['nom'] = 'Le nom est requis.';
    }

    $prenom = trim($input['prenom'] ?? '');
    if (empty($prenom)) {
        $errors['prenom'] = 'Le prénom est requis.';
    }

    $username = trim($input['username'] ?? '');
    if (empty($username)) {
        $errors['username'] = 'L\'identifiant est requis.';
    } else {
        // Check if username is unique
        if ($excludeId > 0) {
            // Edit mode: check if another user already has this username
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username AND id != :exclude_id AND is_active = 1');
            $stmt->execute([':username' => $username, ':exclude_id' => $excludeId]);
            if ($stmt->fetch() !== false) {
                $errors['username'] = 'Cet identifiant est déjà utilisé';
            }
        } else {
            // Create mode: check if any active user has this username
            if (getUserByUsername($pdo, $username) !== null) {
                $errors['username'] = 'Cet identifiant est déjà utilisé';
            }
        }
    }

    $role = trim($input['role'] ?? '');
    if (\App\Enum\UserRole::tryFrom($role) === null) {
        $errors['role'] = 'Rôle invalide.';
    }

    /** @var int */
    $siteId = $input['site_id'] ?? 0;
    // Skip site validation in noSiteMode (site dropdown is hidden)
    // site_id = 0 is allowed (user has no assigned site yet)
    if (!isNoSiteMode($pdo) && $siteId > 0) {
        $site = getSiteById($pdo, $siteId);
        if ($site === null) {
            $errors['site_id'] = 'Site invalide.';
        }
    }

    $email = trim($input['email'] ?? '');
    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors['email'] = 'Adresse email invalide.';
    }

    return $errors;
}


