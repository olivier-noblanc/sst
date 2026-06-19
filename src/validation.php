<?php

/**
 * Validation Functions — Application SST DREETS BFC
 *
 * Shared validation logic extracted from handlers.
 * Eliminates duplication between report_create_handler and report_edit_handler.
 *
 * User validation functions (validateUserFields, isLastActiveSuperviseur)
 * have been moved to src/validation_user.php.
 *
 * Before this file, the same validation code was copy-pasted across handlers
 * (~100 lines duplicated between create/edit report).
 */

require_once __DIR__ . '/validation_user.php';

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
 * @param array<string, string> $errors  Reference to the errors array (modified in place)
 * @return array{blob: string|null, name: string|null, mime: string|null}
 */
function validateReportAttachment(array &$errors): array
{
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
 * @return array{nature_auteur: string, type_acte: string}
 */
function validateRamiFields(string $natureAuteur, string $typeActe): array
{
    $allowedNatureAuteur = array_keys(RAMI_NATURE_AUTEUR_LABELS);
    if (!empty($natureAuteur) && !in_array($natureAuteur, $allowedNatureAuteur)) {
        $natureAuteur = '';
    }

    $allowedTypeActe = array_keys(RAMI_TYPE_ACTE_LABELS);
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
function enforceReportVisibility(int $isConfidential): int
{
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
 * @return array<string, string> Validation errors (field => message)
 */
function validateReportFields(string $dateEvenement, string $objet, string $description, string $lieu, string $heureEvenement): array
{
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
 * @return array<string, string> Validation errors (field => message)
 */
function validatePourCompte(bool $pourCompte, string $pourCompteNom, string $pourComptePrenom): array
{
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
// Report Fetch & Access Guards
// ============================================================================

/**
 * Fetch a report by UUID or redirect with an error flash.
 *
 * Combines UUID validation, DB fetch, and null-check into a single call.
 * Before this function, the same 8-line pattern was duplicated in 8 files.
 *
 * @param string $uuid         The report UUID (from $_GET or $_POST)
 * @param string $fallbackUrl  URL to redirect to on failure
 * @return array<string, mixed>  The report data (never returns null — redirects instead)
 */
function fetchReportOrRedirect(string $uuid, string $fallbackUrl = ''): array
{
    if ($fallbackUrl === '') {
        $fallbackUrl = url('home');
    }
    if (!isValidUuid($uuid)) {
        setFlash('error', 'Signalement introuvable.');
        redirect($fallbackUrl);
    }
    $pdo = getDB();
    $report = getReportByUuid($pdo, $uuid);
    if (!$report) {
        setFlash('error', 'Signalement introuvable.');
        redirect($fallbackUrl);
    }
    return $report;
}

/**
 * Verify that the current user owns the report (is the declarant).
 * Redirects to report_view with an error if not the owner.
 *
 * @param array<string, mixed> $report  Report data from DB
 * @param int    $userId  Current user's ID
 * @param string $uuid    Report UUID (for redirect URL)
 * @param string $verb    Verb for the error message ('modifier', 'abandonner', etc.)
 */
function requireReportOwnership(array $report, int $userId, string $uuid, string $verb = 'modifier'): void
{
    if ((int) $report['declarant_id'] !== $userId) {
        setFlash('error', 'Vous ne pouvez ' . $verb . ' que vos propres signalements.');
        redirect(url('report_view', ['uuid' => $uuid]));
    }
}

/**
 * Verify that the report is in an editable state (nouveau or en_cours).
 * Redirects to report_view with an error if not.
 *
 * @param array<string, mixed> $report  Report data from DB
 * @param string $uuid    Report UUID (for redirect URL)
 * @param string $verb    Verb for the error message ('modifié', 'abandonné', etc.)
 */
function requireReportEditable(array $report, string $uuid, string $verb = 'modifié'): void
{
    if (!in_array($report['etat'], [ETAT_NOUVEAU, ETAT_EN_COURS])) {
        setFlash('error', 'Ce signalement ne peut plus être ' . $verb . ' (état : ' . (ETAT_LABELS[$report['etat']] ?? $report['etat']) . ').');
        redirect(url('report_view', ['uuid' => $uuid]));
    }
}
