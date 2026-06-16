<?php
/**
 * Report Edit Handler — Application SST DREETS BFC
 *
 * POST handler for editing an existing report.
 * Only the declarant can edit, and only if etat is nouveau or en_cours.
 */

validatePostRequest(url('home'));

// Get report UUID
$reportUuid = trim($_POST['report_uuid'] ?? '');
$report = fetchReportOrRedirect($reportUuid);

$user = $_SESSION['user'];
$userId = (int) $user['id'];

requireReportOwnership($report, $userId, $reportUuid, 'modifier');
requireReportEditable($report, $reportUuid, 'modifié');

$pdo = getDB();

// Gather input
$dateEvenement = trim($_POST['date_evenement'] ?? '');
$heureEvenement = trim($_POST['heure_evenement'] ?? '');
$lieu = trim($_POST['lieu'] ?? '');
$objet = trim($_POST['objet'] ?? '');
$description = trim($_POST['description'] ?? '');
$pourCompte = isset($_POST['pour_compte']) && $_POST['pour_compte'] === '1';
$pourCompteNom = trim($_POST['pour_compte_nom'] ?? '');
$pourComptePrenom = trim($_POST['pour_compte_prenom'] ?? '');
$isConfidential = isset($_POST['is_confidential']) && $_POST['is_confidential'] === '1' ? 1 : 0;
// RAMI structured fields
$natureAuteur = trim($_POST['nature_auteur'] ?? '');
$typeActe = trim($_POST['type_acte'] ?? '');
$ramiFields = validateRamiFields($natureAuteur, $typeActe);
$natureAuteur = $ramiFields['nature_auteur'];
$typeActe = $ramiFields['type_acte'];

// Enforce visibility mode rules
$isConfidential = enforceReportVisibility($isConfidential);

$type = $report['type'];

// Validate
$errors = validateReportFields($dateEvenement, $objet, $description, $lieu, $heureEvenement);

// Validate attachment (optional)
$removeAttachment = isset($_POST['remove_attachment']) && $_POST['remove_attachment'] === '1';
$attachment = validateReportAttachment($errors);
$attachmentBlob = $attachment['blob'];
$attachmentName = $attachment['name'];
$attachmentMime = $attachment['mime'];

// RAMI-specific validation
if ($type === 'rami') {
    $errors = array_merge($errors, validatePourCompte($pourCompte, $pourCompteNom, $pourComptePrenom));
}

// If errors, redirect back with form data
if (!empty($errors)) {
    setFormErrors($errors);
    setFormData($_POST);
    redirect(url('report_edit', ['uuid' => $reportUuid]));
}

// Build update data
$updateData = [
    'objet'             => $objet,
    'description'       => $description,
    'date_evenement'    => $dateEvenement,
    'heure_evenement'   => $heureEvenement ?: null,
    'lieu'              => $lieu ?: null,
    'is_confidential'   => $isConfidential,
];

// Handle attachment update
if ($removeAttachment) {
    $updateData['attachment_blob'] = null;
    $updateData['attachment_name'] = null;
    $updateData['attachment_mime'] = null;
} elseif ($attachmentBlob !== null) {
    $updateData['attachment_blob'] = $attachmentBlob;
    $updateData['attachment_name'] = $attachmentName;
    $updateData['attachment_mime'] = $attachmentMime;
}
// If no new file and no removal request: keep existing attachment (don't include in update)

// RAMI-specific fields
if ($type === 'rami') {
    $updateData['pour_compte_nom'] = $pourCompte ? $pourCompteNom : null;
    $updateData['pour_compte_prenom'] = $pourCompte ? $pourComptePrenom : null;
    $updateData['nature_auteur'] = $natureAuteur ?: null;
    $updateData['type_acte'] = $typeActe ?: null;
} else {
    $updateData['pour_compte_nom'] = null;
    $updateData['pour_compte_prenom'] = null;
    $updateData['nature_auteur'] = null;
    $updateData['type_acte'] = null;
}

// Update the report
$updated = updateReport($pdo, $reportUuid, $updateData, $userId);

if ($updated) {
    auditLog($pdo, 'report', 'edit', 'Signalement modifié : ' . $report['reference'], (int) $report['id'] ?? null, 'report', ['reference' => $report['reference']]);
    setFlash('success', 'Signalement ' . e($report['reference']) . ' modifié avec succès.');
} else {
    setFlash('error', 'Impossible de modifier le signalement. Il a peut-être été traité ou abandonné entre-temps. (uuid=' . e($reportUuid) . ', user_id=' . $userId . ', etat=' . e($report['etat']) . ')');
}

redirect(url('report_view', ['uuid' => $reportUuid]));
