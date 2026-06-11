<?php
/**
 * Report Edit Handler — Application SST DREETS BFC
 *
 * POST handler for editing an existing report.
 * Only the declarant can edit, and only if etat is nouveau or en_cours.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('home'));
}

// Validate CSRF token
$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCsrfToken($csrfToken)) {
    setFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');
    redirect(url('home'));
}

// Get report ID
$reportId = (int) ($_POST['report_id'] ?? 0);
if ($reportId <= 0) {
    setFlash('error', 'Signalement introuvable.');
    redirect(url('home'));
}

$pdo = getDB();
$report = getReportById($pdo, $reportId);

if (!$report) {
    setFlash('error', 'Signalement introuvable.');
    redirect(url('home'));
}

$user = $_SESSION['user'];
$userId = (int) $user['id'];

// Ownership check
if ((int) $report['declarant_id'] !== $userId) {
    setFlash('error', 'Vous ne pouvez modifier que vos propres signalements.');
    redirect(url('report_view', ['uuid' => getReportById($pdo, $reportId)['uuid']]));
}

// State check (re-check from DB, not form)
if (!in_array($report['etat'], ['nouveau', 'en_cours'])) {
    setFlash('error', 'Ce signalement ne peut plus être modifié (état : ' . (ETAT_LABELS[$report['etat']] ?? $report['etat']) . ').');
    redirect(url('report_view', ['uuid' => getReportById($pdo, $reportId)['uuid']]));
}

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
// Enforce visibility mode rules:
// - 'public' mode → force is_confidential to 0 (all reports are public)
// - 'confidential' mode → force is_confidential to 1 (all reports are confidential)
// - 'agent_choice' mode → use the agent's choice
if (reportVisibilityIsPublic()) {
    $isConfidential = 0;
} elseif (reportVisibilityIsConfidential()) {
    $isConfidential = 1;
}

$type = $report['type'];

// Validate
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

// Validate lieu length
if (!empty($lieu) && strlen($lieu) > MAX_LIEU_LENGTH) {
    $errors['lieu'] = 'Le lieu ne doit pas dépasser ' . MAX_LIEU_LENGTH . ' caractères.';
}

// Validate heure format (HH:MM)
if (!empty($heureEvenement) && !preg_match('/^\d{2}:\d{2}$/', $heureEvenement)) {
    $errors['heure_evenement'] = 'Format d\'heure invalide (HH:MM attendu).';
}

// RAMI-specific validation
if ($type === 'rami' && $pourCompte) {
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

// If errors, redirect back with form data
if (!empty($errors)) {
    setFormErrors($errors);
    setFormData($_POST);
    redirect(url('report_edit', ['uuid' => getReportById($pdo, $reportId)['uuid']]));
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

// RAMI-specific fields
if ($type === 'rami') {
    $updateData['pour_compte_nom'] = $pourCompte ? $pourCompteNom : null;
    $updateData['pour_compte_prenom'] = $pourCompte ? $pourComptePrenom : null;
} else {
    $updateData['pour_compte_nom'] = null;
    $updateData['pour_compte_prenom'] = null;
}

// Update the report
$updated = updateReport($pdo, $reportId, $updateData, $userId);

if ($updated) {
    setFlash('success', 'Signalement ' . e($report['reference']) . ' modifié avec succès.');
} else {
    setFlash('error', 'Impossible de modifier le signalement. Il a peut-être été traité ou abandonné entre-temps.');
}

redirect(url('report_view', ['uuid' => getReportById($pdo, $reportId)['uuid']]));
