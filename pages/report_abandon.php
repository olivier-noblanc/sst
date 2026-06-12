<?php
/**
 * Report Abandon Page — Application SST DREETS BFC
 *
 * Shows a confirmation form before abandoning a report (soft delete).
 * URL: index.php?page=report_abandon&uuid={report_uuid}
 * Access: Only the declarant, and only if etat is nouveau or en_cours.
 * No JavaScript — pure PHP inline confirmation.
 */
$uuid = $_GET['uuid'] ?? '';

if (!isValidUuid($uuid)) {
    setFlash('error', 'Signalement introuvable.');
    redirect(url('home'));
}

$pdo = getDB();
$report = getReportByUuid($pdo, $uuid);

if (!$report) {
    setFlash('error', 'Signalement introuvable.');
    redirect(url('home'));
}

// Access control: only the declarant
$user = $_SESSION['user'];
$userId = (int) $user['id'];

if ((int) $report['declarant_id'] !== $userId) {
    setFlash('error', 'Vous ne pouvez abandonner que vos propres signalements.');
    redirect(url('report_view', ['uuid' => $uuid]));
}

// Check state: can only abandon if nouveau or en_cours
if (!in_array($report['etat'], ['nouveau', 'en_cours'])) {
    setFlash('error', 'Ce signalement ne peut plus être abandonné (etat : ' . (ETAT_LABELS[$report['etat']] ?? $report['etat']) . ').');
    redirect(url('report_view', ['uuid' => $uuid]));
}

$pageTitle = 'Abandonner le signalement — ' . $report['reference'];
$type = $report['type'];
$csrfToken = generateCsrfToken();

require __DIR__ . '/../templates/alert.php';
?>

<h1 class="page-title">Abandonner le signalement</h1>

<div class="card <?php echo match($type) { 'rsst' => 'card--rsst', 'rami' => 'card--rami', 'dgi' => 'card--dgi', default => 'card--rsst' }; ?>">
    <h2 class="card__subtitle">Signalement <?php echo e($report['reference']); ?></h2>
    <table class="report-detail__table">
        <tbody>
            <tr>
                <th>Objet</th>
                <td><?php echo e($report['objet']); ?></td>
            </tr>
            <tr>
                <th>Date de l'événement</th>
                <td><?php echo e(formatDateFR($report['date_evenement'])); ?></td>
            </tr>
            <tr>
                <th>Etat actuel</th>
                <td>
                    <span class="badge <?php echo getEtatBadgeClass($report['etat']); ?>">
                        <?php echo e(ETAT_LABELS[$report['etat']] ?? $report['etat']); ?>
                    </span>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<!-- Confirmation inline — pas de JavaScript -->
<div class="warning-panel">
    <p>Êtes-vous sûr de vouloir abandonner le signalement <strong><?php echo e($report['reference']); ?></strong> ?</p>
    <p class="warning-panel__hint">Cette action est irréversible. Le signalement sera marqué comme abandonné.</p>
    <div class="btn-group">
        <form method="POST" action="<?php echo url('report_abandon', ['uuid' => $uuid]); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
            <input type="hidden" name="report_uuid" value="<?php echo e($report['uuid']); ?>">
            <button type="submit" class="btn btn--danger">Oui, abandonner</button>
        </form>
        <a href="<?php echo url('report_view', ['uuid' => $uuid]); ?>" class="btn btn--secondary">Annuler</a>
    </div>
</div>
