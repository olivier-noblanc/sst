<?php

/**
 * Report Abandon Page — Application SST DREETS BFC
 *
 * Shows a confirmation form before abandoning a report (soft delete).
 * URL: index.php?page=report_abandon&uuid={report_uuid}
 * Access: Only the declarant. The current state must allow a transition to
 * Abandonne for the user's role (ReportStateMachine is the authority) —
 * i.e. Nouveau/EnCours/Traite/Reouvert for the Agent role.
 * No JavaScript — pure PHP inline confirmation.
 */
$uuid = $_GET['uuid'] ?? '';
$report = fetchReportOrRedirect($uuid);

// Service instances (created once for the page)
$fmt = new \App\Services\FormattingService();
$http = new \App\Services\HttpService();
$config = getConfigService();

// Access control: only the declarant
$user = new \App\Services\SessionService()->getUserSession();
$userId = $user->id ?? 0;

requireReportOwnership($report, $userId, $uuid, 'abandonner');

// Fiabilisation (audit lifecycle) — la page GET s'ALIGNE sur la matrice
// (autorité) et sur report_abandon_handler.php : Abandonne est atteignable
// depuis Nouveau/EnCours/Traite/Reouvert pour le rôle Agent. L'ancien
// requireReportEditable ([Nouveau, EnCours]) refusait à tort Traite/Reouvert,
// alors que la matrice, le bouton et le handler les autorisaient.
$abandonStateMachine = new \App\Services\ReportStateMachine();
$abandonCurrentState = \App\Enum\ReportState::tryFrom($report->etat);
$abandonRole = \App\Enum\UserRole::tryFrom((string) currentUserRole());
if ($abandonCurrentState === null || $abandonRole === null
    || !$abandonStateMachine->canTransition($abandonCurrentState, \App\Enum\ReportState::Abandonne, $abandonRole)) {
    new \App\Services\SessionService()->setFlash('error', 'Ce signalement ne peut pas être abandonné (état : '
        . $fmt->e(ETAT_LABELS[$report->etat] ?? $report->etat) . ').');
    $http->redirect($http->url('report_view', ['uuid' => $uuid]));
}

$pageTitle = 'Abandonner le signalement — ' . $report->reference;
/** @var string */
$type = $report->type;
$csrfToken = new \App\Services\SessionService()->generateCsrfToken();

?>

<h1 class="page-title">Abandonner le signalement</h1>

<?php echo $fmt->renderBreadcrumb([
    ['url' => $http->url('home'), 'label' => 'Accueil'],
    ['url' => $http->url('report_list', ['type' => $type]), 'label' => getRegistryShortLabel($type)],
    ['url' => $http->url('report_view', ['uuid' => $uuid]), 'label' => $report->reference],
    ['label' => 'Abandonner'],
]); ?>

<?php $registryForTheme = \App\Repository\RegistryRepository::instance()->findByCode($type); ?>
<div class="card card--<?php echo e((string) ($registryForTheme['color_theme'] ?? $type)); ?>">
    <h2 class="card__subtitle">Signalement <?php echo $fmt->e($report->reference); ?></h2>
    <table class="report-detail__table" aria-label="Détails du signalement">
        <tbody>
            <tr>
                <th>Objet</th>
                <td><?php echo $fmt->e($report->objet); ?></td>
            </tr>
            <tr>
                <th>Date de dépôt</th>
                <td><?php echo $fmt->e($fmt->formatDateFR($report->dateEvenement)); ?></td>
            </tr>
            <tr>
                <th>Etat actuel</th>
                <td>
                    <span class="badge <?php echo $fmt->getEtatBadgeClass($report->etat); ?>">
                        <?php echo $fmt->e(ETAT_LABELS[$report->etat] ?? $report->etat); ?>
                    </span>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<!-- Confirmation inline — pas de JavaScript -->
<div class="warning-panel">
    <p>Êtes-vous sûr de vouloir abandonner le signalement <strong><?php echo $fmt->e($report->reference); ?></strong> ?</p>
    <p class="warning-panel__hint">Cette action est irréversible. Le signalement sera marqué comme abandonné.</p>
    <div class="btn-group">
        <form method="POST" action="<?php echo $http->url('report_abandon', ['uuid' => $uuid]); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo $fmt->e($csrfToken); ?>">
            <input type="hidden" name="report_uuid" value="<?php echo $fmt->e($report->uuid); ?>">
            <button type="submit" class="btn btn--danger">Oui, abandonner</button>
        </form>
        <a href="<?php echo $http->url('report_view', ['uuid' => $uuid]); ?>" class="btn btn--secondary">Annuler</a>
    </div>
</div>
