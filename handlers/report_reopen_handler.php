<?php
/**
 * Report Reopen Handler — Application SST DREETS BFC
 * 
 * POST handler: reopen a report that was traite or abandonne.
 * Access: superviseur or CHSCT only (not declarant — French labor law)
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

// Check user is supervisor or CHSCT (P0-3: declarant may NOT reopen)
$user = currentUser();
$userId = (int) $user['id'];
$userRole = $user['role'] ?? 'agent';

if (!in_array($userRole, [ROLE_SUPERVISEUR, ROLE_CHSCT])) {
    setFlash('error', 'Vous n\'êtes pas autorisé à réouvrir ce signalement. Seuls les superviseurs et le CHSCT peuvent réouvrir un signalement.');
    redirect(url('report_view', ['uuid' => $reportUuid]));
}

// Reopen the report: set etat to 'reouvert' (P0-1)
$pdo = getDB();

try {
    $pdo->beginTransaction();

    // Insert state history BEFORE changing state
    $stmt = $pdo->prepare("
        INSERT INTO report_state_history (report_uuid, etat_precedent, etat_suivant, user_id, motif)
        VALUES (:report_uuid, :etat_precedent, :etat_suivant, :user_id, :motif)
    ");
    $stmt->execute([
        ':report_uuid'    => $reportUuid,
        ':etat_precedent' => $report['etat'],
        ':etat_suivant'   => ETAT_REOUVERT,
        ':user_id'        => $userId,
        ':motif'          => $motifReouverture,
    ]);

    // Update report state (P0-1: use 'reouvert' instead of 'en_cours')
    $stmt = $pdo->prepare("
        UPDATE reports
        SET etat = :nouvel_etat,
            updated_at = datetime('now')
        WHERE uuid = :uuid AND etat IN ('traite', 'abandonne')
    ");
    $stmt->execute([':uuid' => $reportUuid, ':nouvel_etat' => ETAT_REOUVERT]);

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
        ':nouvel_etat' => ETAT_REOUVERT,
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
