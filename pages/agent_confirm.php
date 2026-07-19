<?php
/**
 * Agent Confirmation Page — Application SST DREETS BFC
 *
 * Displays a confirmation button for an agent to confirm their link to a report.
 * URL: index.php?page=agent_confirm&token=xxx
 *
 * NOTE: This page is included by the router with header/sidebar/footer.
 * The router has already started the session, loaded config/database/helpers/queries,
 * and checked authentication. We do NOT need to re-require or re-start session.
 */

$token = trim($_GET['token'] ?? '');

if (empty($token)) {
    echo '<div class="card card--spaced"><h1 class="page-title">Lien invalide</h1><p>Ce lien de confirmation est invalide ou incomplet.</p></div>';
    return;
}

$invite = \App\Repository\ReportRepository::instance()->getAgentInviteByToken($token);

if ($invite === null) {
    echo '<div class="card card--spaced"><h1 class="page-title">Invitation déjà traitée</h1><p>Cette invitation a déjà été confirmée ou a expiré. Si vous venez de cliquer, votre rattachement est déjà actif.</p><a href="' . new \App\Services\HttpService()->url('home') . '" class="btn btn--primary">Retour à l\'accueil</a></div>';
    return;
}

// Get report info
$report = \App\Repository\ReportRepository::instance()->findById($invite['report_uuid']);
if ($report === null) {
    echo '<div class="card card--spaced"><h1 class="page-title">Signalement introuvable</h1><p>Le signalement associé à cette invitation n\'existe plus.</p></div>';
    return;
}

$pageTitle = 'Confirmer mon rattachement';
?>

<h1 class="page-title">Confirmer mon rattachement</h1>

<div class="card card--spaced card--narrow-center">
    <p>Vous avez été rattaché(e) au signalement <strong><?php echo new \App\Services\FormattingService()->e($report['reference']); ?></strong> par le déclarant.</p>

    <table class="table table--compact table--spaced">
        <tr>
            <th>Référence</th>
            <td><?php echo new \App\Services\FormattingService()->e($report['reference']); ?></td>
        </tr>
        <tr>
            <th>Registre</th>
            <td><?php echo new \App\Services\FormattingService()->e(REGISTRY_LABELS[$report['type']] ?? $report['type']); ?></td>
        </tr>
        <tr>
            <th>Objet</th>
            <td><?php echo new \App\Services\FormattingService()->e($report['objet']); ?></td>
        </tr>
        <tr>
            <th>Date</th>
            <td><?php echo new \App\Services\FormattingService()->e(new \App\Services\FormattingService()->formatDateFR($report['date_evenement'])); ?></td>
        </tr>
    </table>

    <p>En confirmant, vous serez rattaché(e) à ce signalement et pourrez en suivre le traitement.</p>

    <form method="POST" action="<?php echo new \App\Services\HttpService()->url('agent_confirm'); ?>">
        <input type="hidden" name="token" value="<?php echo new \App\Services\FormattingService()->e($token); ?>">
        <div class="form-actions form-actions--center">
            <button type="submit" class="btn btn--primary btn--lg">
                ✅ Confirmer mon rattachement
            </button>
        </div>
    </form>

    <p class="text-muted text-small text-center text-top-spaced">
        Si vous ne souhaitez pas être rattaché(e), fermez simplement cette page. Aucune action ne sera effectuée.
    </p>
</div>
