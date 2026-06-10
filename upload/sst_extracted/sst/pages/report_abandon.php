<?php
/**
 * Report Abandon Page — Application SST DREETS BFC
 *
 * Shows a confirmation dialog before abandoning a report (soft delete).
 * URL: index.php?page=report_abandon&id={report_id}
 * Access: Only the declarant, and only if etat is nouveau or en_cours.
 */
$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    setFlash('error', 'Signalement introuvable.');
    redirect(url('home'));
}

$pdo = getDB();
$report = getReportById($pdo, $id);

if (!$report) {
    setFlash('error', 'Signalement introuvable.');
    redirect(url('home'));
}

// Access control: only the superviseur can abandon a report
$user = $_SESSION['user'];
$userRole = $user['role'];

if (!in_array($userRole, ['superviseur'])) {
    setFlash('error', 'Seul un superviseur peut abandonner un signalement.');
    redirect(url('report_view', ['id' => $id]));
}

// Check state: can only abandon if nouveau or en_cours
if (!in_array($report['etat'], ['nouveau', 'en_cours'])) {
    setFlash('error', 'Ce signalement ne peut plus être abandonné (état : ' . (ETAT_LABELS[$report['etat']] ?? $report['etat']) . ').');
    redirect(url('report_view', ['id' => $id]));
}

$pageTitle = 'Abandonner le signalement — ' . $report['reference'];
$type = $report['type'];

require __DIR__ . '/../templates/alert.php';
?>

<h1 class="page-title">Abandonner le signalement</h1>

<div class="card <?php echo match($type) { 'rsst' => 'card--rsst', 'rami' => 'card--rami', 'dgi' => 'card--dgi', default => 'card--rsst' }; ?>">
    <h2 style="margin-bottom:12px;">Signalement <?php echo e($report['reference']); ?></h2>
    <table class="report-detail__table">
        <tbody>
            <tr>
                <th>Objet</th>
                <td><?php echo e($report['objet']); ?></td>
            </tr>
            <tr>
                <th>Date de l'événement</th>
                <td><?php echo formatDateFR($report['date_evenement']); ?></td>
            </tr>
            <tr>
                <th>État actuel</th>
                <td>
                    <span class="badge <?php echo getEtatBadgeClass($report['etat']); ?>">
                        <?php echo e(ETAT_LABELS[$report['etat']] ?? $report['etat']); ?>
                    </span>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../templates/confirm_dialog.php'; ?>
