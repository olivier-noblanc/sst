<?php
/**
 * Validation Functions — Application SST DREETS BFC
 *
 * Shared validation logic extracted from handlers.
 * Eliminates duplication between report_create_handler and report_edit_handler,
 * and between user_create_handler and user_edit_handler.
 *
 * Before this file, the same validation code was copy-pasted across handlers
 * (~100 lines duplicated between create/edit report, ~40 lines between create/edit user).
 */

// ============================================================================
// Report Validation
// ============================================================================

/**
 * Validate and process an uploaded attachment.
 *
 * Handles the file upload, checks size and MIME type, and returns the
 * attachment data or adds errors to the $errors array.
 * Shared between report_create_handler and report_edit_handler.
 *
 * @param array $errors  Reference to the errors array (modified in place)
 * @return array ['blob' => string|null, 'name' => string|null, 'mime' => string|null]
 */
function validateReportAttachment(array &$errors): array {
    $attachmentBlob = null;
    $attachmentName = null;
    $attachmentMime = null;

    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['attachment'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors['attachment'] = 'Erreur lors du téléchargement du fichier.';
        } elseif ($file['size'] > MAX_ATTACHMENT_SIZE) {
            $errors['attachment'] = 'Le fichier ne doit pas dépasser 10 Mo.';
        } else {
            try {
                $mime = getMimeType($file['tmp_name']);
                if (!in_array($mime, ALLOWED_ATTACHMENT_MIMES)) {
                    $errors['attachment'] = 'Type de fichier non autorisé. Formats acceptés : JPG, PNG, GIF, PDF.';
                } else {
                    $attachmentBlob = file_get_contents($file['tmp_name']);
                    $attachmentName = basename($file['name']);
                    $attachmentMime = $mime;
                }
            } catch (\RuntimeException $ex) {
                $errors['attachment'] = $ex->getMessage();
            }
        }
    }

    return ['blob' => $attachmentBlob, 'name' => $attachmentName, 'mime' => $attachmentMime];
}

/**
 * Validate and sanitize RAMI structured fields (nature_auteur, type_acte).
 *
 * These fields are optional but must be one of the allowed values if provided.
 * Shared between report_create_handler and report_edit_handler.
 *
 * @param string $natureAuteur  Raw input for nature_auteur
 * @param string $typeActe      Raw input for type_acte
 * @return array ['nature_auteur' => string, 'type_acte' => string]
 */
function validateRamiFields(string $natureAuteur, string $typeActe): array {
    $allowedNatureAuteur = ['usager', 'collegue', 'hierarchie', 'tiers'];
    if (!empty($natureAuteur) && !in_array($natureAuteur, $allowedNatureAuteur)) {
        $natureAuteur = '';
    }

    $allowedTypeActe = ['verbal', 'physique', 'moral', 'sexiste', 'autre'];
    if (!empty($typeActe) && !in_array($typeActe, $allowedTypeActe)) {
        $typeActe = '';
    }

    return ['nature_auteur' => $natureAuteur, 'type_acte' => $typeActe];
}

/**
 * Enforce report visibility mode rules on the is_confidential field.
 *
 * - 'public' mode: force is_confidential to 0 (all reports are public)
 * - 'confidential' mode: force is_confidential to 1 (all reports are confidential)
 * - 'agent_choice' mode: keep the agent's selection
 *
 * Shared between report_create_handler and report_edit_handler.
 *
 * @param int $isConfidential  The agent's choice (0 or 1)
 * @return int  The enforced confidentiality value
 */
function enforceReportVisibility(int $isConfidential): int {
    if (reportVisibilityIsPublic()) {
        return 0;
    }
    if (reportVisibilityIsConfidential()) {
        return 1;
    }
    // agent_choice: keep the agent's selection
    return $isConfidential;
}

/**
 * Validate common report fields (date, objet, description, lieu, heure).
 *
 * Shared between report_create_handler and report_edit_handler.
 * Before this function, the same ~30 lines of validation were duplicated.
 *
 * @param string $dateEvenement  Event date (YYYY-MM-DD)
 * @param string $objet          Report subject
 * @param string $description    Report description
 * @param string $lieu           Location (optional)
 * @param string $heureEvenement Event time (HH:MM, optional)
 * @return array Validation errors (field => message)
 */
function validateReportFields(string $dateEvenement, string $objet, string $description, string $lieu, string $heureEvenement): array {
    $errors = [];

    if (empty($dateEvenement)) {
        $errors['date_evenement'] = 'La date de l\'événement est obligatoire.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateEvenement)) {
        $errors['date_evenement'] = 'Format de date invalide.';
    } elseif ($dateEvenement > date('Y-m-d')) {
        $errors['date_evenement'] = 'La date ne peut pas être dans le futur.';
    }

    if (empty($objet)) {
        $errors['objet'] = 'L\'objet est obligatoire.';
    } elseif (strlen($objet) > MAX_OBJECT_LENGTH) {
        $errors['objet'] = 'L\'objet ne doit pas dépasser ' . MAX_OBJECT_LENGTH . ' caractères.';
    }

    if (empty($description)) {
        $errors['description'] = 'La description est obligatoire.';
    } elseif (strlen($description) > MAX_DESCRIPTION_LENGTH) {
        $errors['description'] = 'La description ne doit pas dépasser ' . MAX_DESCRIPTION_LENGTH . ' caractères.';
    }

    if (!empty($lieu) && strlen($lieu) > MAX_LIEU_LENGTH) {
        $errors['lieu'] = 'Le lieu ne doit pas dépasser ' . MAX_LIEU_LENGTH . ' caractères.';
    }

    if (!empty($heureEvenement) && !preg_match('/^\d{2}:\d{2}$/', $heureEvenement)) {
        $errors['heure_evenement'] = 'Format d\'heure invalide (HH:MM attendu).';
    }

    return $errors;
}

/**
 * Validate RAMI "pour compte" fields (when an agent reports on behalf of another).
 *
 * @param bool   $pourCompte       Whether the "pour compte" checkbox is checked
 * @param string $pourCompteNom    The other agent's last name
 * @param string $pourComptePrenom The other agent's first name
 * @return array Validation errors (field => message)
 */
function validatePourCompte(bool $pourCompte, string $pourCompteNom, string $pourComptePrenom): array {
    $errors = [];

    if ($pourCompte) {
        if (empty($pourCompteNom)) {
            $errors['pour_compte_nom'] = 'Le nom de l\'agent est obligatoire si vous signalez pour le compte d\'un autre agent.';
        } elseif (strlen($pourCompteNom) > 100) {
            $errors['pour_compte_nom'] = 'Le nom ne doit pas dépasser 100 caractères.';
        }
        if (empty($pourComptePrenom)) {
            $errors['pour_compte_prenom'] = 'Le prénom de l\'agent est obligatoire si vous signalez pour le compte d\'un autre agent.';
        } elseif (strlen($pourComptePrenom) > 100) {
            $errors['pour_compte_prenom'] = 'Le prénom ne doit pas dépasser 100 caractères.';
        }
    }

    return $errors;
}

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
 * @param array  $input     POST data (nom, prenom, username, role, site_id, email)
 * @param int    $excludeId User ID to exclude from uniqueness check (for edit)
 * @return array Validation errors (field => message)
 */
function validateUserFields(PDO $pdo, array $input, int $excludeId = 0): array {
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
            // Edit mode: exclude current user from uniqueness check
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username AND id != :id");
            $stmt->execute([':username' => $username, ':id' => $excludeId]);
        } else {
            // Create mode: check against all users
            $existing = getUserByUsername($pdo, $username);
            $stmt = null; // avoid unused variable
            if ($existing) {
                $errors['username'] = 'Cet identifiant est déjà utilisé.';
            }
        }
        if (isset($stmt) && $stmt && $stmt->fetch()) {
            $errors['username'] = 'Cet identifiant est déjà utilisé.';
        }
    }

    $role = trim($input['role'] ?? '');
    if (!in_array($role, ['agent', 'superviseur', 'chsct'])) {
        $errors['role'] = 'Rôle invalide.';
    }

    $siteId = (int) ($input['site_id'] ?? 0);
    if ($siteId <= 0) {
        $errors['site_id'] = 'Le site est requis.';
    } else {
        $site = getSiteById($pdo, $siteId);
        if (!$site) {
            $errors['site_id'] = 'Site invalide.';
        }
    }

    $email = trim($input['email'] ?? '');
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Adresse email invalide.';
    }

    return $errors;
}
