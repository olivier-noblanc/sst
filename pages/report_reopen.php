<?php
/**
 * Report Reopen Page — Application SST DREETS BFC
 *
 * Shows a form to reopen a report that was traite or abandonne.
 * URL: index.php?page=report_reopen&uuid={report_uuid}
 * Access: supervisor/chsct only (not declarant — French labor law).
 */
$uuid = $_GET['uuid'] ?? '';
$report = fetchReportOrRedirect($uuid);

// Access control: must be supervisor or CHSCT (P0-3: declarant may NOT reopen)
$user = (new \App\Services\SessionService())->getUserSession();
$userRole = $user['role'] ?? 'agent';

if (!in_array($userRole, [ROLE_SUPERVISEUR, ROLE_CHSCT])) {
    (new \App\Services\SessionService())->setFlash('error', 'Vous n\'êtes pas autorisé à réouvrir ce signalement. Seuls les superviseurs et le CHSCT peuvent réouvrir un signalement.');
    (new \App\Services\HttpService())->redirect((new \App\Services\HttpService())->url('report_view', ['uuid' => $uuid]));
}

// Check report is in a reopenable state
if (!in_array($report['etat'], ['traite', 'abandonne'])) {
    (new \App\Services\SessionService())->setFlash('error', 'Ce signalement ne peut pas être réouvert (état actuel : ' . (new \App\Services\FormattingService())->e(ETAT_LABELS[$report['etat']] ?? $report['etat']) . ').');
    (new \App\Services\HttpService())->redirect((new \App\Services\HttpService())->url('report_view', ['uuid' => $uuid]));
}

$pageTitle = 'Réouvrir le signalement — ' . $report['reference'];
$type = $report['type'];
$csrfToken = (new \App\Services\SessionService())->generateCsrfToken();

// Restore form data if redirected back with errors
$formData = (new \App\Services\SessionService())->getFormData();
$flash = (new \App\Services\SessionService())->getFlash();
?>

<h1 class="page-title">Réouvrir le signalement</h1>

<?php echo (new \App\Services\FormattingService())->renderBreadcrumb([
    ['url' => (new \App\Services\HttpService())->url('home'), 'label' => 'Accueil'],
    ['url' => (new \App\Services\HttpService())->url('report_list', ['type' => $type]), 'label' => REGISTRY_SHORT_LABELS[$type] ?? strtoupper((string) $type)],
    ['url' => (new \App\Services\HttpService())->url('report_view', ['uuid' => $uuid]), 'label' => $report['reference']],
    ['label' => 'Réouvrir'],
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
                <th>État actuel</th>
                <td>
                    <span class="badge <?php echo (new \App\Services\FormattingService())->getEtatBadgeClass($report['etat']); ?>">
                        <?php echo (new \App\Services\FormattingService())->e(ETAT_LABELS[$report['etat']] ?? $report['etat']); ?>
                    </span>
                </td>
            </tr>
        </tbody>
    </table>
</div>

    <?php if ($flash): ?>
    <div class="alert alert--<?php echo (new \App\Services\FormattingService())->e($flash['type']); ?>" role="alert">
        <?php echo (new \App\Services\FormattingService())->e($flash['message']); ?>
    </div>
    <?php endif; ?>

<div class="warning-panel">
    <?php if ($report['type'] === TYPE_DGI): ?>
    <div class="alert alert--danger" role="alert">
        <strong>Attention — Registre DGI</strong><br>
        La réouverture d'un signalement DGI signifie que le danger grave et imminent n'a pas été résolu. 
        Conformément à l'article L4131-2 du Code du travail, le CSE/<?php echo (new \App\Services\FormattingService())->e(getRoleLabelShort('chsct')); ?> sera informé de cette réouverture.
    </div>
    <?php endif; ?>

    <p>Vous êtes sur le point de réouvrir le signalement <strong><?php echo (new \App\Services\FormattingService())->e($report['reference']); ?></strong>.</p>
    <p class="warning-panel__hint">Le signalement repassera à l'état « Réouvert ». Veuillez indiquer le motif de cette réouverture.</p>

    <form method="POST" action="<?php echo (new \App\Services\HttpService())->url('report_reopen', ['uuid' => $uuid]); ?>" class="mt-4">
        <input type="hidden" name="csrf_token" value="<?php echo (new \App\Services\FormattingService())->e($csrfToken); ?>">
        <input type="hidden" name="report_uuid" value="<?php echo (new \App\Services\FormattingService())->e($report['uuid']); ?>">

        <div class="form-group">
            <label for="motif_reouverture">Motif de réouverture <span class="required">*</span></label>
            <textarea id="motif_reouverture" name="motif_reouverture" rows="4" required minlength="10" maxlength="2000" placeholder="Décrivez pourquoi ce signalement doit être réouvert (minimum 10 caractères)"><?php echo (new \App\Services\FormattingService())->e($formData['motif_reouverture'] ?? ''); ?></textarea>
        </div>

        <div class="btn-group">
            <button type="submit" class="btn btn--primary">Réouvrir le signalement</button>
            <a href="<?php echo (new \App\Services\HttpService())->url('report_view', ['uuid' => $uuid]); ?>" class="btn btn--secondary">Annuler</a>
        </div>
    </form>
</div>
