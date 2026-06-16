<?php
/**
 * Report Reopen Handler — Application SST DREETS BFC
 * 
 * POST handler: reopen a report that was traite or abandonne.
 * Access: superviseur or original declarant
 */

validatePostRequest(url('home'));

$reportUuid = trim($_POST['report_uuid'] ?? '');
$motifReouverture = trim($_POST['motif_reouverture'] ?? '');

// Validate motif
if (strlen($motifReouverture) < 10) {
    setFlash('error', 'Le motif de réouverture doit contenir au moins 10 caractères.');
    setFormData($_POST);
    redirect(url('report_reopen', ['uuid' => $reportUuid]));
}

// Get the report
$report = fetchReportOrRedirect($reportUuid);

// Check report is in a reopenable state
if (!in_array($report['etat'], ['traite', 'abandonne'])) {
    setFlash('error', 'Ce signalement ne peut pas être réouvert (état actuel : ' . e(ETAT_LABELS[$report['etat']] ?? $report['etat']) . ').');
    redirect(url('report_view', ['uuid' => $reportUuid]));
}

// Check user is supervisor or the original declarant
$user = currentUser();
$userId = (int) $user['id'];
$userRole = $user['role'] ?? 'agent';
$isDeclarant = ((int) $report['declarant_id'] === $userId);

if (!$isDeclarant && !in_array($userRole, ['superviseur', 'chsct'])) {
    setFlash('error', 'Vous n\'êtes pas autorisé à réouvrir ce signalement.');
    redirect(url('report_view', ['uuid' => $reportUuid]));
}

// Reopen the report: set etat to en_cours
$pdo = getDB();

try {
    $pdo->beginTransaction();

    // Update report state
    $stmt = $pdo->prepare("
        UPDATE reports
        SET etat = 'en_cours',
            updated_at = datetime('now')
        WHERE uuid = :uuid AND etat IN ('traite', 'abandonne')
    ");
    $stmt->execute([':uuid' => $reportUuid]);

    $updated = $stmt->rowCount() > 0;

    if (!$updated) {
        $pdo->rollBack();
        setFlash('error', 'Ce signalement a été modifié entre-temps. Veuillez réessayer.');
        redirect(url('report_view', ['uuid' => $reportUuid]));
    }

    // Insert into response history
    $stmt = $pdo->prepare("
        INSERT INTO report_responses (report_uuid, user_id, reponse, nouvel_etat)
        VALUES (:report_uuid, :user_id, :reponse, :nouvel_etat)
    ");
    $stmt->execute([
        ':report_uuid' => $reportUuid,
        ':user_id'     => $userId,
        ':reponse'     => 'Réouverture du signalement. Motif : ' . $motifReouverture,
        ':nouvel_etat' => 'en_cours',
    ]);

    $pdo->commit();

    // Audit log
    auditLog($pdo, 'report', 'reopen', 'Signalement réouvert : ' . $report['reference'] . ' — Motif : ' . $motifReouverture, null, 'report', ['reference' => $report['reference'], 'motif' => $motifReouverture]);

    // Notify declarant (non-blocking)
    try {
        require_once __DIR__ . '/../src/mail.php';
        $declarant = getUserById($pdo, (int) $report['declarant_id']);
        if ($declarant && !empty($declarant['email']) && (int) $report['declarant_id'] !== $userId) {
            $registryLabel = REGISTRY_SHORT_LABELS[$report['type']] ?? strtoupper($report['type']);
            $subject = "Signalement réouvert $registryLabel — {$report['reference']}";
            $body = "<html><body>";
            $body .= "<h2>Votre signalement a été réouvert</h2>";
            $body .= "<p><strong>Référence :</strong> " . e($report['reference']) . "</p>";
            $body .= "<p><strong>Motif :</strong> " . e($motifReouverture) . "</p>";
            $body .= "<p><a href=\"" . getBaseUrl() . "/" . url('report_view', ['uuid' => $reportUuid]) . "\">Consulter le signalement</a></p>";
            $body .= "</body></html>";
            sendMail($declarant['email'], $subject, $body);
        }
    } catch (Exception $mailEx) {
        error_log('[SST-MAIL] Reopen notification error: ' . $mailEx->getMessage());
    }

    setFlash('success', 'Signalement ' . e($report['reference']) . ' réouvert avec succès.');

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[SST-REOPEN] Transaction failed: ' . $e->getMessage());
    setFlash('error', 'Erreur lors de la réouverture du signalement. Veuillez réessayer.');
}

redirect(url('report_view', ['uuid' => $reportUuid]));
