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
            if ($stmt->fetch()) {
                $errors['username'] = 'Cet identifiant est déjà utilisé';
            }
        } else {
            // Create mode: check if any active user has this username
            if (getUserByUsername($pdo, $username)) {
                $errors['username'] = 'Cet identifiant est déjà utilisé';
            }
        }
    }

    $role = trim($input['role'] ?? '');
    if (!in_array($role, [ROLE_AGENT, ROLE_SUPERVISEUR, ROLE_CHSCT])) {
        $errors['role'] = 'Rôle invalide.';
    }

    $siteId = (int) ($input['site_id'] ?? 0);
    // Skip site validation in noSiteMode (site dropdown is hidden)
    if (!isNoSiteMode($pdo)) {
        if ($siteId <= 0) {
            $errors['site_id'] = 'Le site est requis.';
        } else {
            $site = getSiteById($pdo, $siteId);
            if (!$site) {
                $errors['site_id'] = 'Site invalide.';
            }
        }
    }

    $email = trim($input['email'] ?? '');
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Adresse email invalide.';
    }

    return $errors;
}

// ============================================================================
// Superviseur Guards
// ============================================================================

/**
 * Check if a user is the last active superviseur in the system.
 *
 * Used to prevent demoting or deactivating the last superviseur,
 * which would lock everyone out of admin functions.
 *
 * @param PDO $pdo  Database connection
 * @return bool     True if there is only one active superviseur
 */
function isLastActiveSuperviseur(PDO $pdo): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = '" . ROLE_SUPERVISEUR . "' AND is_active = 1");
    $stmt->execute();
    return (int) $stmt->fetchColumn() <= 1;
}
