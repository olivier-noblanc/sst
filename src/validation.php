<?php

use App\Enum\ReportType;
use App\DTO\ReportData;
use App\Services\HttpService;
use App\Services\SessionService;
use App\Enum\ReportState;
use App\Repository\ReportRepository;

/**
 * Validation Functions — Application SST DREETS BFC
 *
 * Shared validation logic extracted from handlers.
 * Eliminates duplication between report_create_handler and report_edit_handler.
 *
 * Before this file, the same validation code was copy-pasted across handlers
 * (~100 lines duplicated between create/edit report).
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
 * @param array<string, string> $errors  Reference to the errors array (modified in place)
 * @return array{blob: string|null, name: string|null, mime: string|null}
 */
function validateReportAttachment(array &$errors, string $fieldName = 'attachment'): array
{
    $attachmentBlob = null;
    $attachmentName = null;
    $attachmentMime = null;

    /** @var array{error: int, name: string, tmp_name: string, size: int}|null $file */
    $file = $_FILES[$fieldName] ?? null;
    if ($file !== null && $file['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors['attachment'] = 'Erreur lors du téléchargement du fichier.';
        } elseif ($file['size'] > MAX_ATTACHMENT_SIZE) {
            $errors['attachment'] = 'Le fichier ne doit pas dépasser 10 Mo.';
        } else {
            try {
                $mime = getMimeType($file['tmp_name']);
                if (!in_array($mime, ALLOWED_ATTACHMENT_MIMES, true)) {
                    $errors['attachment'] = 'Type de fichier non autorisé. Formats acceptés : JPG, PNG, GIF, PDF.';
                } else {
                    $attachmentBlob = file_get_contents($file['tmp_name']);
                    if ($attachmentBlob === false) {
                        $errors['attachment'] = 'Erreur lors de la lecture du fichier.';
                        $attachmentBlob = null;
                    } else {
                        /** @var string */
                        $fileName = $file['name'] ?? '';
                        $attachmentName = basename($fileName);
                        $attachmentMime = $mime;
                    }
                }
            } catch (RuntimeException $ex) {
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
    $allowedNatureAuteur = getRegistryFieldKeys(ReportType::Rami->value, 'nature_auteur');
    if (!empty($natureAuteur) && !in_array($natureAuteur, $allowedNatureAuteur, true)) {
        $natureAuteur = '';
    }

    $allowedTypeActe = getRegistryFieldKeys(ReportType::Rami->value, 'type_acte');
    if (!empty($typeActe) && !in_array($typeActe, $allowedTypeActe, true)) {
        $typeActe = '';
    }

    return ['nature_auteur' => $natureAuteur, 'type_acte' => $typeActe];
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
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateEvenement) !== 1) {
        $errors['date_evenement'] = 'Format de date invalide.';
    } elseif (!checkdate((int) substr($dateEvenement, 5, 2), (int) substr($dateEvenement, 8, 2), (int) substr($dateEvenement, 0, 4))) {
        // Audit #88 — Avant ce fix, on testait juste le format YYYY-MM-DD via regex.
        // '2025-11-45' matchait la regex mais est une date invalide (jour 45 n'existe
        // pas). checkdate() valide réellement jour/mois/année.
        $errors['date_evenement'] = 'Date invalide (jour/mois/année hors plage).';
    } elseif ($dateEvenement > date('Y-m-d')) {
        $errors['date_evenement'] = 'La date ne peut pas être dans le futur.';
    }

    if (empty($objet)) {
        $errors['objet'] = 'L\'objet est obligatoire.';
    } elseif (mb_strlen($objet, 'UTF-8') > MAX_OBJECT_LENGTH) {
        $errors['objet'] = 'L\'objet ne doit pas dépasser ' . MAX_OBJECT_LENGTH . ' caractères.';
    }

    if (empty($description)) {
        $errors['description'] = 'La description est obligatoire.';
    } elseif (mb_strlen($description, 'UTF-8') > MAX_DESCRIPTION_LENGTH) {
        $errors['description'] = 'La description ne doit pas dépasser ' . MAX_DESCRIPTION_LENGTH . ' caractères.';
    }

    if (!empty($lieu) && mb_strlen($lieu, 'UTF-8') > MAX_LIEU_LENGTH) {
        $errors['lieu'] = 'Le lieu ne doit pas dépasser ' . MAX_LIEU_LENGTH . ' caractères.';
    }

    if (!empty($heureEvenement) && preg_match('/^\d{2}:\d{2}$/', $heureEvenement) !== 1) {
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
        } elseif (mb_strlen($pourCompteNom, 'UTF-8') > 100) {
            $errors['pour_compte_nom'] = 'Le nom ne doit pas dépasser 100 caractères.';
        }
        if (empty($pourComptePrenom)) {
            $errors['pour_compte_prenom'] = 'Le prénom de l\'agent est obligatoire si vous signalez pour le compte d\'un autre agent.';
        } elseif (mb_strlen($pourComptePrenom, 'UTF-8') > 100) {
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
 * @return ReportData The report data (never returns null — redirects instead)
 */
function fetchReportOrRedirect(string $uuid, string $fallbackUrl = ''): ReportData
{
    if ($fallbackUrl === '') {
        $fallbackUrl = new HttpService()->url('home');
    }
    if (!isValidUuid($uuid)) {
        SessionService::getInstance()->setFlash('error', 'Signalement introuvable.');
        new HttpService()->redirect($fallbackUrl);
    }
    $pdo = getDB();
    $report = ReportRepository::instance()->findById($uuid);
    if ($report === null) {
        SessionService::getInstance()->setFlash('error', 'Signalement introuvable.');
        new HttpService()->redirect($fallbackUrl);
    }
    assert($report !== null);
    return $report;
}

/**
 * Verify that the current user owns the report (is the declarant).
 * Redirects to report_view with an error if not the owner.
 */
function requireReportOwnership(ReportData $report, int $userId, string $uuid, string $verb = 'modifier'): void
{
    if ($report->declarantId !== $userId) {
        SessionService::getInstance()->setFlash('error', 'Vous ne pouvez ' . $verb . ' que vos propres signalements.');
        new HttpService()->redirect(new HttpService()->url('report_view', ['uuid' => $uuid]));
    }
}

/**
 * Verify that the report is in an editable state (nouveau or en_cours).
 * Redirects to report_view with an error if not.
 */
function requireReportEditable(ReportData $report, string $uuid, string $verb = 'modifié'): void
{
    if (!in_array($report->etat, [ReportState::Nouveau->value, ReportState::EnCours->value], true)) {
        SessionService::getInstance()->setFlash('error', 'Ce signalement ne peut plus être ' . $verb . ' (état : ' . (ETAT_LABELS[$report->etat] ?? $report->etat) . ').');
        new HttpService()->redirect(new HttpService()->url('report_view', ['uuid' => $uuid]));
    }
}

/**
 * Verify that the report is in a respondable state (nouveau, en_cours, or reouvert).
 * Stricter than requireReportEditable: 'reouvert' is respondable but not editable.
 *
 * Audit #4 — workflow réouvrir→répondre cassé. Avant ce fix, requireReportEditable
 * était appelé dans report_respond.php et rejetait l'état 'reouvert'. Mais
 * canRespondToReport (AccessService.php:200) accepte Reouvert. Inconsistance :
 * un superviseur pouvait réouvrir un signalement, mais ne pouvait plus y répondre.
 *
 * @param string $verb  Verb used in the error message (default: 'répondu')
 */
function requireReportRespondable(ReportData $report, string $uuid, string $verb = 'répondu'): void
{
    if (!in_array($report->etat, [ReportState::Nouveau->value, ReportState::EnCours->value, ReportState::Reouvert->value], true)) {
        SessionService::getInstance()->setFlash('error', 'Ce signalement ne peut plus être ' . $verb . ' (état : ' . (ETAT_LABELS[$report->etat] ?? $report->etat) . ').');
        new HttpService()->redirect(new HttpService()->url('report_view', ['uuid' => $uuid]));
    }
}
