<?php

use App\Enum\ReportState;
use App\Enum\ReportType;

/**
 * Report Reopen Page — Application SST DREETS BFC
 *
 * Shows a form to reopen a report that was traite or abandonne.
 * URL: index.php?page=report_reopen&uuid={report_uuid}
 * Access: supervisor/chsct only (not declarant — French labor law).
 */
$uuid = $_GET['uuid'] ?? '';
$report = fetchReportOrRedirect($uuid);

// Service instances (created once for the page)
$fmt = new \App\Services\FormattingService();
$http = new \App\Services\HttpService();
$config = \App\Services\ConfigService::getInstance();

// Access control: must be supervisor or CHSCT (P0-3: declarant may NOT reopen)
$user = new \App\Services\SessionService()->getUserSession();
$userRole = $user['role'] ?? 'agent';

if (!in_array($userRole, [\App\Enum\UserRole::Superviseur->value], true)) {
    new \App\Services\SessionService()->setFlash('error', 'Vous n\'êtes pas autorisé à réouvrir ce signalement. Seuls les superviseurs peuvent réouvrir un signalement.');
    $http->redirect($http->url('report_view', ['uuid' => $uuid]));
}

// Check report is in a reopenable state
if (!in_array($report['etat'], [ReportState::Traite->value, ReportState::Abandonne->value], true)) {
    new \App\Services\SessionService()->setFlash('error', 'Ce signalement ne peut pas être réouvert (état actuel : ' . $fmt->e(ETAT_LABELS[$report['etat']] ?? $report['etat']) . ').');
    $http->redirect($http->url('report_view', ['uuid' => $uuid]));
}

$pageTitle = 'Réouvrir le signalement — ' . $report['reference'];
$type = $report['type'];
$csrfToken = new \App\Services\SessionService()->generateCsrfToken();
$typeStr = (string) $type;

// Restore form data if redirected back with errors
$formData = new \App\Services\SessionService()->getFormData();
$flash = new \App\Services\SessionService()->getFlash();
?>

<h1 class="page-title">Réouvrir le signalement</h1>

<?php echo $fmt->renderBreadcrumb([
    ['url' => $http->url('home'), 'label' => 'Accueil'],
    ['url' => $http->url('report_list', ['type' => $type]), 'label' => getRegistryShortLabel($typeStr)],
    ['url' => $http->url('report_view', ['uuid' => $uuid]), 'label' => $report['reference']],
    ['label' => 'Réouvrir'],
]); ?>

<?php $registryForTheme = \App\Repository\RegistryRepository::instance()->findByCode($type); ?>
<div class="card card--<?php echo e((string) ($registryForTheme["color_theme"] ?? $type)); ?>">
    ReportType::Rsst->value => 'card--rsst', ReportType::Rami->value => 'card--rami', ReportType::Dgi->value => 'card--dgi', default => 'card--rsst'
}; ?>">
    <h2 class="card__subtitle">Signalement <?php echo $fmt->e($report['reference']); ?></h2>
    <table class="report-detail__table" aria-label="Détails du signalement">
        <tbody>
            <tr>
                <th>Objet</th>
                <td><?php echo $fmt->e($report['objet']); ?></td>
            </tr>
            <tr>
                <th>Date de l'événement</th>
                <td><?php echo $fmt->e($fmt->formatDateFR($report['date_evenement'])); ?></td>
            </tr>
            <tr>
                <th>État actuel</th>
                <td>
                    <span class="badge <?php echo $fmt->getEtatBadgeClass($report['etat']); ?>">
                        <?php echo $fmt->e(ETAT_LABELS[$report['etat']] ?? $report['etat']); ?>
                    </span>
                </td>
            </tr>
        </tbody>
    </table>
</div>

    <?php if ($flash !== null): ?>
    <div class="alert alert--<?php echo $fmt->e($flash['type']); ?>" role="alert">
        <?php echo $fmt->e($flash['message']); ?>
    </div>
    <?php endif; ?>

<div class="warning-panel">
    <?php if ($report['type'] === \App\Enum\ReportType::Dgi->value): ?>
    <div class="alert alert--danger" role="alert">
        <strong>Attention — Registre DGI</strong><br>
        La réouverture d'un signalement DGI signifie que le danger grave et imminent n'a pas été résolu. 
        Conformément à l'article L4131-2 du Code du travail, le CSE/<?php echo $fmt->e(getRoleLabelShort('chsct')); ?> sera informé de cette réouverture.
    </div>
    <?php endif; ?>

    <p>Vous êtes sur le point de réouvrir le signalement <strong><?php echo $fmt->e($report['reference']); ?></strong>.</p>
    <p class="warning-panel__hint">Le signalement repassera à l'état « Réouvert ». Veuillez indiquer le motif de cette réouverture.</p>

    <form method="POST" action="<?php echo $http->url('report_reopen', ['uuid' => $uuid]); ?>" class="mt-4">
        <input type="hidden" name="csrf_token" value="<?php echo $fmt->e($csrfToken); ?>">
        <input type="hidden" name="report_uuid" value="<?php echo $fmt->e($report['uuid']); ?>">

        <div class="form-group">
            <label for="motif_reouverture">Motif de réouverture <span class="required">*</span></label>
            <textarea id="motif_reouverture" name="motif_reouverture" rows="4" required minlength="10" maxlength="2000" placeholder="Décrivez pourquoi ce signalement doit être réouvert (minimum 10 caractères)"><?php echo $fmt->e($formData['motif_reouverture'] ?? ''); ?></textarea>
        </div>

        <div class="btn-group">
            <button type="submit" class="btn btn--primary">Réouvrir le signalement</button>
            <a href="<?php echo $http->url('report_view', ['uuid' => $uuid]); ?>" class="btn btn--secondary">Annuler</a>
        </div>
    </form>
</div>
