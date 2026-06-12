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

$reportUuid = trim($_POST['report_uuid'] ?? '');
if ($reportUuid === '' || !isValidUuid($reportUuid)) {
    setFlash('error', 'Signalement introuvable.');
    redirect(url('home'));
}

$pdo = getDB();

// Validate nouvel_etat
$nouvelEtat = trim($_POST['nouvel_etat'] ?? '');
if (!in_array($nouvelEtat, ['en_cours', 'traite'])) {
    setFlash('error', 'L\'état sélectionné n\'est pas valide.');
    setFormData($_POST);
    redirect(url('report_respond', ['uuid' => $reportUuid]));
}

// Validate reponse
$reponse = trim($_POST['reponse'] ?? '');
if (empty($reponse)) {
    setFlash('error', 'La réponse ne peut pas être vide.');
    setFormErrors(['reponse' => 'La réponse ne peut pas être vide.']);
    setFormData($_POST);
    redirect(url('report_respond', ['uuid' => $reportUuid]));
}

if (strlen($reponse) > 5000) {
    setFlash('error', 'La réponse ne doit pas dépasser 5000 caractères.');
    setFormErrors(['reponse' => 'Maximum 5000 caractères.']);
    setFormData($_POST);
    redirect(url('report_respond', ['uuid' => $reportUuid]));
}

// Get the report
$report = getReportByUuid($pdo, $reportUuid);
if (!$report) {
    setFlash('error', 'Signalement introuvable.');
    redirect(url('home'));
}

// Verify report state
if (!in_array($report['etat'], ['nouveau', 'en_cours'])) {
    setFlash('error', 'Ce signalement ne peut plus recevoir de réponse.');
    redirect(url('report_view', ['uuid' => $reportUuid]));
}

// Save response
$userId = (int) $_SESSION['user']['id'];
$result = respondToReport($pdo, $reportUuid, $userId, $reponse, $nouvelEtat);

if ($result === true) {
    // Audit log
    require_once __DIR__ . '/../src/audit.php';
    auditLog($pdo, 'report', 'respond', 'Réponse au signalement ' . $report['reference'] . ' — état : ' . $nouvelEtat, (int) ($report['id'] ?? 0), 'report', ['reference' => $report['reference'], 'nouvel_etat' => $nouvelEtat]);

    // Notify declarant about the response (non-blocking — errors are logged, not shown to user)
    try {
        require_once __DIR__ . '/../src/mail.php';
        notifyReportResponse($pdo, $reportUuid, $userId);
    } catch (Exception $mailEx) {
        error_log('[SST-MAIL] Notification error: ' . $mailEx->getMessage());
    }

    setFlash('success', 'Réponse enregistrée pour le signalement ' . e($report['reference']) . '.');
} else {
    // $result is either 'concurrent' (report was modified by another session)
    // or 'error' (database constraint or other failure)
    if ($result === 'concurrent') {
        setFlash('error', 'Ce signalement a été modifié par un autre superviseur pendant votre saisie. Veuillez recommencer.');
    } else {
        setFlash('error', 'Erreur lors de l\'enregistrement de la réponse. Veuillez réessayer ou contacter l\'administrateur si le problème persiste.');
    }
}

redirect(url('report_view', ['uuid' => $reportUuid]));
