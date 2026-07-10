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
$report = fetchReportOrRedirect($uuid);

// Access control: only the declarant
$user = (new \App\Services\SessionService())->getUserSession();
$userId = (int) $user['id'];

requireReportOwnership($report, $userId, $uuid, 'abandonner');
requireReportEditable($report, $uuid, 'abandonné');

$pageTitle = 'Abandonner le signalement — ' . $report['reference'];
$type = $report['type'];
$csrfToken = (new \App\Services\SessionService())->generateCsrfToken();

?>

<h1 class="page-title">Abandonner le signalement</h1>

<?php echo (new \App\Services\FormattingService())->renderBreadcrumb([
    ['url' => (new \App\Services\HttpService())->url('home'), 'label' => 'Accueil'],
    ['url' => (new \App\Services\HttpService())->url('report_list', ['type' => $type]), 'label' => REGISTRY_SHORT_LABELS[$type] ?? strtoupper((string) $type)],
    ['url' => (new \App\Services\HttpService())->url('report_view', ['uuid' => $uuid]), 'label' => $report['reference']],
    ['label' => 'Abandonner'],
]); ?>

<div class="card <?php echo match ($type) {
    'rsst' => 'card--rsst', 'rami' => 'card--rami', 'dgi' => 'card--dgi', default => 'card--rsst'
}; ?>">
    <h2 class="card__subtitle">Signalement <?php echo (new \App\Services\FormattingService())->e($report['reference']); ?></h2>
    <table class="report-detail__table" aria-label="Détails du signalement">
        <tbody>
            <tr>
                <th>Objet</th>
                <td><?php echo (new \App\Services\FormattingService())->e($report['objet']); ?></td>
            </tr>
            <tr>
                <th>Date de l'événement</th>
                <td><?php echo (new \App\Services\FormattingService())->e((new \App\Services\FormattingService())->formatDateFR($report['date_evenement'])); ?></td>
            </tr>
            <tr>
                <th>Etat actuel</th>
                <td>
                    <span class="badge <?php echo (new \App\Services\FormattingService())->getEtatBadgeClass($report['etat']); ?>">
                        <?php echo (new \App\Services\FormattingService())->e(ETAT_LABELS[$report['etat']] ?? $report['etat']); ?>
                    </span>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<!-- Confirmation inline — pas de JavaScript -->
<div class="warning-panel">
    <p>Êtes-vous sûr de vouloir abandonner le signalement <strong><?php echo (new \App\Services\FormattingService())->e($report['reference']); ?></strong> ?</p>
    <p class="warning-panel__hint">Cette action est irréversible. Le signalement sera marqué comme abandonné.</p>
    <div class="btn-group">
        <form method="POST" action="<?php echo (new \App\Services\HttpService())->url('report_abandon', ['uuid' => $uuid]); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo (new \App\Services\FormattingService())->e($csrfToken); ?>">
            <input type="hidden" name="report_uuid" value="<?php echo (new \App\Services\FormattingService())->e($report['uuid']); ?>">
            <button type="submit" class="btn btn--danger">Oui, abandonner</button>
        </form>
        <a href="<?php echo (new \App\Services\HttpService())->url('report_view', ['uuid' => $uuid]); ?>" class="btn btn--secondary">Annuler</a>
    </div>
</div>
