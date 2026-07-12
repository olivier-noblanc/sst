<?php

/**
 * Report Reopen Handler — Thin controller delegating to ReportService.
 */

use App\DTO\ReopenReportCommand;
use App\Services\ReportService;

$reportUuid = trim((string) ($_POST['report_uuid'] ?? ''));
$motifReouverture = trim((string) ($_POST['motif_reouverture'] ?? ''));

if (mb_strlen($motifReouverture, 'UTF-8') < 10) {
    setFlash('error', 'Le motif de réouverture doit contenir au moins 10 caractères.');
    setFormData($_POST);
    redirect(url('report_reopen', ['uuid' => $reportUuid]));
}

$report = fetchReportOrRedirect($reportUuid);
$userId = currentUserId();

try {
    $cmd = new ReopenReportCommand(motif: $motifReouverture);
    /** @var ReportService $service */
    $service = getContainer()->get(ReportService::class);
    $result = $service->reopen($reportUuid, $cmd, $userId);

    if ($result) {
        auditLog(getDB(), 'report', 'reopen', 'Signalement réouvert : ' . (string) $report['reference'] . ' — Motif : ' . $motifReouverture, null, 'report', ['reference' => $report['reference'], 'motif' => $motifReouverture], $reportUuid);

        // Notify declarant + linked agents (non-blocking)
        try {
            require_once __DIR__ . '/../src/mail.php';
            $pdo = getDB();
            $registryLabel = REGISTRY_SHORT_LABELS[(string) ($report['type'] ?? '')] ?? strtoupper((string) ($report['type'] ?? ''));
            $declarant = getUserById($pdo, (int) ($report['declarant_id'] ?? 0));
            if ($declarant && !empty($declarant['email']) && (int) ($report['declarant_id'] ?? 0) !== $userId) {
                $subject = "Signalement réouvert $registryLabel — {$report['reference']}";
                $body = '<html><body>';
                $body .= '<h2>Votre signalement a été réouvert</h2>';
                $body .= '<p><strong>Référence :</strong> ' . e((string) $report['reference']) . '</p>';
                $body .= '<p><strong>Motif :</strong> ' . e($motifReouverture) . '</p>';
                $body .= '<p><a href="' . getBaseUrl() . '/' . url('report_view', ['uuid' => $reportUuid]) . '">Consulter le signalement</a></p>';
                $body .= '</body></html>';
                sendMail($declarant['email'], $subject, $body);
            }
            $linkedAgents = getLinkedAgents($pdo, $reportUuid);
            foreach ($linkedAgents as $linkedAgent) {
                if (!empty($linkedAgent['email']) && $linkedAgent['email'] !== ($declarant['email'] ?? '')) {
                    $linkedSubject = "Signalement réouvert $registryLabel — {$report['reference']}";
                    $linkedBody = buildEmailBody(
                        'Signalement réouvert',
                        '<p>Bonjour ' . e((string) ($linkedAgent['prenom'] ?? '')) . ',</p>'
                        . '<p>Le signalement <strong>' . e((string) $report['reference']) . '</strong> auquel vous êtes rattaché(e) a été réouvert.</p>'
                        . '<p><strong>Motif :</strong> ' . e($motifReouverture) . '</p>'
                        . '<p><a href="' . getBaseUrl() . '/' . url('report_view', ['uuid' => $reportUuid]) . '">Consulter le signalement</a></p>'
                    );
                    sendMail($linkedAgent['email'], $linkedSubject, $linkedBody);
                }
            }
        } catch (Throwable $mailEx) {
            error_log('[SST-MAIL] Reopen notification error: ' . $mailEx->getMessage());
        }

        setFlash('success', 'Signalement ' . e((string) $report['reference']) . ' réouvert avec succès.');
    } else {
        setFlash('error', 'Ce signalement a été modifié entre-temps. Veuillez réessayer.');
    }
} catch (RuntimeException $e) {
    setFlash('error', e($e->getMessage()));
    redirect(url('report_view', ['uuid' => $reportUuid]));
} catch (Exception $e) {
    error_log('[SST-REOPEN] Transaction failed: ' . $e->getMessage());
    setFlash('error', 'Erreur lors de la réouverture du signalement. Veuillez réessayer.');
}

redirect(url('report_view', ['uuid' => $reportUuid]));
