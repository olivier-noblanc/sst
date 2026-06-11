<?php
/**
 * Report Respond Handler — Application SST DREETS BFC
 * 
 * POST handler: save supervisor response + update report state.
 * Access: superviseur only
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('home'));
}

// Validate CSRF token
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Erreur de sécurité. Veuillez réessayer.');
    redirect(url('home'));
}

// Check role
if (!hasRole('superviseur')) {
    setFlash('error', 'Vous n\'avez pas les permissions nécessaires.');
    redirect(url('home'));
}

$reportId = (int) ($_POST['report_id'] ?? 0);
if ($reportId <= 0) {
    // Try from GET
    $reportId = (int) ($_GET['id'] ?? 0);
}

if ($reportId <= 0) {
    setFlash('error', 'Signalement introuvable.');
    redirect(url('home'));
}

$pdo = getDB();

// Validate nouvel_etat
$nouvelEtat = trim($_POST['nouvel_etat'] ?? '');
if (!in_array($nouvelEtat, ['en_cours', 'traite'])) {
    setFlash('error', 'L\'état sélectionné n\'est pas valide.');
    setFormData($_POST);
    redirect(url('report_respond', ['uuid' => getReportById($pdo, $reportId)['uuid']]));
}

// Validate reponse
$reponse = trim($_POST['reponse'] ?? '');
if (empty($reponse)) {
    setFlash('error', 'La réponse ne peut pas être vide.');
    setFormErrors(['reponse' => 'La réponse ne peut pas être vide.']);
    setFormData($_POST);
    redirect(url('report_respond', ['uuid' => getReportById($pdo, $reportId)['uuid']]));
}

if (strlen($reponse) > 5000) {
    setFlash('error', 'La réponse ne doit pas dépasser 5000 caractères.');
    setFormErrors(['reponse' => 'Maximum 5000 caractères.']);
    setFormData($_POST);
    redirect(url('report_respond', ['uuid' => getReportById($pdo, $reportId)['uuid']]));
}

// Get the report
$report = getReportById($pdo, $reportId);
if (!$report) {
    setFlash('error', 'Signalement introuvable.');
    redirect(url('home'));
}

// Verify report state
if (!in_array($report['etat'], ['nouveau', 'en_cours'])) {
    setFlash('error', 'Ce signalement ne peut plus recevoir de réponse.');
    redirect(url('report_view', ['uuid' => getReportById($pdo, $reportId)['uuid']]));
}

// Save response
$userId = (int) $_SESSION['user']['id'];
$success = respondToReport($pdo, $reportId, $userId, $reponse, $nouvelEtat);

if ($success) {
    // Notify declarant about the response (non-blocking — errors are logged, not shown to user)
    try {
        require_once __DIR__ . '/../src/mail.php';
        notifyReportResponse($pdo, $reportId, $userId);
    } catch (Exception $mailEx) {
        error_log('[SST-MAIL] Notification error: ' . $mailEx->getMessage());
    }

    setFlash('success', 'Réponse enregistrée pour le signalement ' . e($report['reference']) . '.');
} else {
    setFlash('error', 'Erreur lors de l\'enregistrement de la réponse. Le signalement a peut-être déjà été traité.');
}

redirect(url('report_view', ['uuid' => getReportById($pdo, $reportId)['uuid']]));
