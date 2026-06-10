<?php
/**
 * Report Create Handler — Application SST DREETS BFC
 *
 * POST handler for creating a new report.
 * Validates input, generates reference, inserts into DB.
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

// Get and validate type
$type = $_POST['type'] ?? '';
if (!in_array($type, ['rsst', 'rami', 'dgi'])) {
    setFlash('error', 'Type de registre invalide.');
    redirect(url('home'));
}

$user = $_SESSION['user'];
$pdo = getDB();

// Gather input
$dateEvenement = trim($_POST['date_evenement'] ?? '');
$heureEvenement = trim($_POST['heure_evenement'] ?? '');
$lieu = trim($_POST['lieu'] ?? '');
$objet = trim($_POST['objet'] ?? '');
$description = trim($_POST['description'] ?? '');
$siteId = (int) ($_POST['site_id'] ?? 0);
$pourCompte = isset($_POST['pour_compte']) && $_POST['pour_compte'] === '1';
$pourCompteNom = trim($_POST['pour_compte_nom'] ?? '');
$pourComptePrenom = trim($_POST['pour_compte_prenom'] ?? '');
$isConfidential = isset($_POST['is_confidential']) && $_POST['is_confidential'] === '1' ? 1 : 0;
// If visibility mode is 'public', force is_confidential to 0
if (agentVisibilityIsPublic()) {
    $isConfidential = 0;
}

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

// Validate site
if ($siteId <= 0) {
    $errors['site_id'] = 'L\'unité départementale est obligatoire.';
} else {
    $site = getSiteById($pdo, $siteId);
    if (!$site) {
        $errors['site_id'] = 'Unité départementale invalide.';
    }
}

// Agent can only create for their own site
if ($siteId !== (int) $user['site_id']) {
    $errors['site_id'] = 'Vous ne pouvez créer un signalement que pour votre ' . getConfig('app_label_unite', 'UR') . '.';
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
    'is_confidential'   => $isConfidential,
];

// RAMI-specific fields
if ($type === 'rami' && $pourCompte) {
    $reportData['pour_compte_de'] = null; // No FK to user, just names
    $reportData['pour_compte_nom'] = $pourCompteNom;
    $reportData['pour_compte_prenom'] = $pourComptePrenom;
} else {
    $reportData['pour_compte_de'] = null;
    $reportData['pour_compte_nom'] = null;
    $reportData['pour_compte_prenom'] = null;
}

// Create the report
try {
    $reference = createReport($pdo, $reportData);
    $newId = getLastInsertId($pdo);

    // Send notifications (non-blocking — errors are logged, not shown to user)
    try {
        require_once __DIR__ . '/../src/mail.php';
        notifyNewReport($pdo, $newId, $type, $siteId);
        if ($type === 'rami' && !empty($pourCompteNom)) {
            notifyPourCompte($pdo, $newId);
        }
    } catch (Exception $mailEx) {
        error_log('[SST-MAIL] Notification error: ' . $mailEx->getMessage());
    }

    setFlash('success', 'Signalement enregistré avec la référence ' . e($reference));
    redirect(url('report_view', ['id' => $newId]));
} catch (Exception $e) {
    setFlash('error', 'Erreur lors de l\'enregistrement du signalement. Veuillez réessayer.');
    setFormData($_POST);
    redirect(url('report_create', ['type' => $type]));
}
