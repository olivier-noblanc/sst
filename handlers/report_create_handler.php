<?php

/**
 * Report Create Handler — Application SST DREETS BFC
 *
 * POST handler for creating a new report.
 * Validates input, generates reference, inserts into DB.
 */

validatePostRequest(url('home'));

// Get and validate type
$type = $_POST['type'] ?? '';
if (!in_array($type, [TYPE_RSST, TYPE_RAMI, TYPE_DGI])) {
    setFlash('error', 'Type de registre invalide.');
    redirect(url('home'));
}

// Block creation in disabled registries
if (!isRegistryEnabled($type)) {
    setFlash('error', 'Ce registre est désactivé.');
    redirect(url('home'));
}

$user = currentUser();
$pdo = getDB();

// Gather input
$dateEvenement = trim($_POST['date_evenement'] ?? '');
$heureEvenement = nowTime(); // Auto-filled at deposit time, field is readonly
$lieu = trim($_POST['lieu'] ?? '');
$siteText = trim($_POST['site_text'] ?? '');
$pole = trim($_POST['pole'] ?? '');
$serviceAffectation = trim($_POST['service_affectation'] ?? '');
$telephoneMobile = trim($_POST['telephone_mobile'] ?? '');
$objet = trim($_POST['objet'] ?? '');
$description = trim($_POST['description'] ?? '');
$siteId = (int) ($_POST['site_id'] ?? 0);
$pourCompte = isset($_POST['pour_compte']) && $_POST['pour_compte'] === '1';
$pourCompteNom = trim($_POST['pour_compte_nom'] ?? '');
$pourComptePrenom = trim($_POST['pour_compte_prenom'] ?? '');
$isConfidential = isset($_POST['is_confidential']) && $_POST['is_confidential'] === '1' ? 1 : 0;
$consentSyndicat = isset($_POST['consent_syndicat']) && $_POST['consent_syndicat'] === '1' ? 1 : 0;
// RAMI structured fields
$natureAuteur = trim($_POST['nature_auteur'] ?? '');
$typeActe = trim($_POST['type_acte'] ?? '');
$ramiFields = validateRamiFields($natureAuteur, $typeActe);
$natureAuteur = $ramiFields['nature_auteur'];
$typeActe = $ramiFields['type_acte'];

// Enforce visibility mode rules
$isConfidential = enforceReportVisibility($isConfidential);

// Validate
$errors = validateReportFields($dateEvenement, $objet, $description, $lieu, $heureEvenement);

// Validate attachment (optional)
$attachment = validateReportAttachment($errors);
$attachmentBlob = $attachment['blob'];
$attachmentName = $attachment['name'];
$attachmentMime = $attachment['mime'];

// Validate site (skip in noSiteMode — site dropdown is hidden)
$noSiteMode = isNoSiteMode($pdo);
if (!$noSiteMode) {
    if ($siteId <= 0) {
        $errors['site_id'] = 'L\'unité départementale est obligatoire.';
    } else {
        $site = getSiteById($pdo, $siteId);
        if (!$site) {
            $errors['site_id'] = 'Unité départementale invalide.';
        }
    }

    // Agent can only create for their own site; superviseurs/chsct can create for any site
    if (!canSeeAllSites() && $siteId !== (int) $user['site_id']) {
        $errors['site_id'] = 'Vous ne pouvez créer un signalement que pour votre ' . getConfig('app_label_unite', 'UR') . '.';
    }
}

// RAMI-specific validation
if ($type === TYPE_RAMI) {
    $errors = array_merge($errors, validatePourCompte($pourCompte, $pourCompteNom, $pourComptePrenom));
}

// Validate linked agent emails (domain must match declarant's domain)
$linkedEmailsRaw = trim($_POST['linked_emails'] ?? '');
if (!empty($linkedEmailsRaw)) {
    $declarantEmail = $user['email'] ?? '';
    $emailDomain = '';
    if ($declarantEmail && str_contains((string) $declarantEmail, '@')) {
        $emailDomain = substr((string) $declarantEmail, strrpos((string) $declarantEmail, '@') + 1);
    }
    $linkedEmailsList = array_map(trim(...), explode(',', $linkedEmailsRaw));
    foreach ($linkedEmailsList as $idx => $em) {
        if (empty($em)) {
            continue;
        }
        if (!filter_var($em, FILTER_VALIDATE_EMAIL)) {
            $errors['linked_emails'] = 'Adresse e-mail invalide : ' . e($em);
            break;
        }
        if ($emailDomain) {
            $emDomain = substr($em, strrpos($em, '@') + 1);
            if (strtolower($emDomain) !== strtolower($emailDomain)) {
                $errors['linked_emails'] = 'Seul le domaine @' . e($emailDomain) . ' est autorisé. Adresse refusée : ' . e($em);
                break;
            }
        }
    }
}

// If errors, redirect back with form data
if (!empty($errors)) {
    setFormErrors($errors);
    setFormData($_POST);
    redirect(url('report_create', ['type' => $type]));
}

// Build report data
$reportData = [
    'type'              => $type,
    'objet'             => $objet,
    'description'       => $description,
    'date_evenement'    => $dateEvenement,
    'heure_evenement'   => $heureEvenement ?: null,
    'lieu'              => $lieu ?: null,
    'declarant_id'      => (int) $user['id'],
    'declarant_nom'     => $user['nom'],
    'declarant_prenom'  => $user['prenom'],
    'site_id'           => $siteId,
    'site_text'         => $siteText ?: null,
    'pole'              => $pole ?: null,
    'service_affectation' => $serviceAffectation ?: null,
    'telephone_mobile'  => $telephoneMobile ?: null,
    'is_confidential'   => $isConfidential,
    'consent_syndicat'  => $consentSyndicat,
    'attachment_blob'  => $attachmentBlob,
    'attachment_name'  => $attachmentName,
    'attachment_mime'  => $attachmentMime,
];

// RAMI-specific fields
if ($type === TYPE_RAMI && $pourCompte) {
    $reportData['pour_compte_de'] = null; // No FK to user, just names
    $reportData['pour_compte_nom'] = $pourCompteNom;
    $reportData['pour_compte_prenom'] = $pourComptePrenom;
} else {
    $reportData['pour_compte_de'] = null;
    $reportData['pour_compte_nom'] = null;
    $reportData['pour_compte_prenom'] = null;
}

// RAMI structured fields (only for RAMI type)
if ($type === TYPE_RAMI) {
    $reportData['nature_auteur'] = $natureAuteur ?: null;
    $reportData['type_acte'] = $typeActe ?: null;
} else {
    $reportData['nature_auteur'] = null;
    $reportData['type_acte'] = null;
}

// Create the report
try {
    $newUuid = createReport($pdo, $reportData);

    // Fetch the new report to get the reference for display
    $newReport = getReportByUuid($pdo, $newUuid);

    // Audit log
    auditLog($pdo, 'report', 'create', 'Signalement créé : ' . $newReport['reference'], (int) $newReport['id'], 'report', ['reference' => $newReport['reference'], 'type' => $type, 'site_id' => $siteId]);

    // Send notifications (non-blocking — errors are logged, not shown to user)
    try {
        require_once __DIR__ . '/../src/mail.php';
        notifyNewReport($pdo, $newUuid, $type, $siteId);
        if ($type === TYPE_RAMI && !empty($pourCompteNom)) {
            notifyPourCompte($pdo, $newUuid);
        }
        // Send invite emails to linked agents
        $linkedEmailsRaw = trim($_POST['linked_emails'] ?? '');
        if (!empty($linkedEmailsRaw)) {
            $linkedEmails = array_map(trim(...), explode(',', $linkedEmailsRaw));
            $linkedEmails = array_filter($linkedEmails, fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL));
            if (!empty($linkedEmails)) {
                sendAgentInviteEmails($pdo, $newUuid, $linkedEmails);
            }
        }
    } catch (Exception $mailEx) {
        error_log('[SST-MAIL] Notification error: ' . $mailEx->getMessage());
    }

    setFlash('success', 'Signalement enregistré avec la référence ' . e($newReport['reference']));
    $_SESSION['report_created'] = true;
    redirect(url('report_view', ['uuid' => $newUuid]));
} catch (Exception $e) {
    error_log('[SST-DB] report_create failed: ' . $e->getMessage());
    setFlash('error', 'Erreur lors de l\'enregistrement du signalement : ' . e($e->getMessage()));
    setFormData($_POST);
    redirect(url('report_create', ['type' => $type]));
}
