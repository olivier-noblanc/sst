<?php
/**
 * Report Card Template — Application SST DREETS BFC
 * 
 * Shared display for a single report view.
 * 
 * Required variables:
 *   $report    — Report data array with joined site and respondent info
 *   $responses — Array of response history entries
 */
if (!isset($report) || !$report) {
    return;
}

$type = $report['type'] ?? 'rsst';
$cardClass = match($type) {
    'rsst' => 'card--rsst',
    'rami' => 'card--rami',
    'dgi'  => 'card--dgi',
    default => 'card--rsst',
};

$registryLabel = REGISTRY_SHORT_LABELS[$type] ?? strtoupper($type);
$user = $_SESSION['user'] ?? [];
$userRole = $user['role'] ?? 'agent';
$userSiteId = (int) ($user['site_id'] ?? 0);
$userId = (int) ($user['id'] ?? 0);
$isDeclarant = ((int) $report['declarant_id'] === $userId);
$canRespond = in_array($userRole, ['superviseur']);
$canEdit = $isDeclarant && in_array($report['etat'], ['nouveau', 'en_cours']);
$canAbandon = in_array($userRole, ['superviseur']) && !in_array($report['etat'], ['abandonne', 'traite']);
$canRespondToReport = $canRespond && in_array($report['etat'], ['nouveau', 'en_cours']);
?>

<div class="card <?php echo $cardClass; ?>">
    <div class="report-detail">
        <div class="report-detail__header">
            <h2>Signalement — <?php echo e($report['reference']); ?></h2>
            <div style="display:flex;gap:8px;align-items:center;">
                <span class="badge <?php echo getRegistryBadgeClass($type); ?>"><?php echo e($registryLabel); ?></span>
                <span class="badge <?php echo getEtatBadgeClass($report['etat']); ?>"><?php echo e(ETAT_LABELS[$report['etat']] ?? $report['etat']); ?></span>
            </div>
        </div>

        <table class="report-detail__table">
            <tbody>
                <tr>
                    <th>Référence</th>
                    <td><?php echo e($report['reference']); ?></td>
                </tr>
                <tr>
                    <th>Date de l'événement</th>
                    <td><?php echo formatDateFR($report['date_evenement']); ?></td>
                </tr>
                <tr>
                    <th>Heure de l'événement</th>
                    <td><?php echo e($report['heure_evenement'] ?? '—'); ?></td>
                </tr>
                <tr>
                    <th>Lieu</th>
                    <td><?php echo e($report['lieu'] ?? '—'); ?></td>
                </tr>
                <tr>
                    <th>Objet</th>
                    <td><?php echo e($report['objet']); ?></td>
                </tr>
                <tr>
                    <th>Description</th>
                    <td><?php echo nl2br(e($report['description'])); ?></td>
                </tr>
                <tr>
                    <th>Déclarant</th>
                    <td><?php echo e($report['declarant_prenom'] . ' ' . $report['declarant_nom']); ?></td>
                </tr>
                <tr>
                    <th><?php echo e(getConfig('app_label_unite', 'UR')); ?></th>
                    <td><?php echo e($report['site_nom'] ?? '—'); ?> (<?php echo e($report['site_code'] ?? '—'); ?>)</td>
                </tr>
                <?php if ($type === 'rami' && !empty($report['pour_compte_nom'])): ?>
                <tr>
                    <th>Déclaré pour le compte de</th>
                    <td><?php echo e(($report['pour_compte_prenom'] ?? '') . ' ' . $report['pour_compte_nom']); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th>Date de création</th>
                    <td><?php echo formatDateTimeFR($report['created_at']); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php if (!empty($report['reponse'])): ?>
<div class="card">
    <h3>Réponse</h3>
    <div class="report-response">
        <p><?php echo nl2br(e($report['reponse'])); ?></p>
        <div class="report-response__meta">
            <strong>Répondant :</strong> <?php echo e(($report['repondant_prenom'] ?? '') . ' ' . ($report['repondant_nom'] ?? '')); ?>
            &nbsp;—&nbsp;
            <strong>Date :</strong> <?php echo formatDateTimeFR($report['date_reponse']); ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($responses)): ?>
<div class="card">
    <h3>Historique des réponses</h3>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Répondant</th>
                    <th>Nouvel état</th>
                    <th>Réponse</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($responses as $resp): ?>
                <tr>
                    <td><?php echo formatDateTimeFR($resp['created_at']); ?></td>
                    <td><?php echo e(($resp['prenom'] ?? '') . ' ' . ($resp['nom'] ?? '')); ?></td>
                    <td>
                        <?php if (!empty($resp['nouvel_etat'])): ?>
                            <span class="badge <?php echo getEtatBadgeClass($resp['nouvel_etat']); ?>">
                                <?php echo e(ETAT_LABELS[$resp['nouvel_etat']] ?? $resp['nouvel_etat']); ?>
                            </span>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?php echo nl2br(e($resp['reponse'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="form-actions">
    <?php if ($canEdit): ?>
        <a href="<?php echo url('report_edit', ['id' => $report['id']]); ?>" class="btn btn--secondary">Modifier</a>
    <?php endif; ?>

    <?php if ($canRespondToReport): ?>
        <a href="<?php echo url('report_respond', ['id' => $report['id']]); ?>" class="btn btn--primary">Répondre</a>
    <?php endif; ?>

    <?php if ($canAbandon): ?>
        <a href="#" onclick="document.getElementById('abandon-form').style.display='block';return false;" class="btn btn--danger">Abandonner le signalement</a>
    <?php endif; ?>

    <a href="<?php echo url('report_print', ['id' => $report['id']]); ?>" class="btn btn--outline" target="_blank">Imprimer la fiche</a>
    <a href="<?php echo url('report_list', ['type' => $type]); ?>" class="btn btn--secondary">Retour à la liste</a>
</div>

<?php if ($canAbandon): ?>
<div id="abandon-form" style="display:none;">
    <?php require __DIR__ . '/confirm_dialog.php'; ?>
</div>
<?php endif; ?>
