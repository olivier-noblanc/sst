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
$canAbandon = $isDeclarant && !in_array($report['etat'], ['abandonne', 'traite']);
$canRespondToReport = $canRespond && in_array($report['etat'], ['nouveau', 'en_cours']);
?>

<div class="card <?php echo $cardClass; ?>">
    <div class="report-detail">
        <div class="report-detail__header">
            <h2>Signalement — <?php echo e($report['reference']); ?></h2>
            <div style="display:flex;gap:8px;align-items:center;">
                <span class="badge <?php echo getRegistryBadgeClass($type); ?>"><?php echo e($registryLabel); ?></span>
                <span class="badge <?php echo getEtatBadgeClass($report['etat']); ?>"><?php echo e(ETAT_LABELS[$report['etat']] ?? $report['etat']); ?></span>
                <?php if (!empty($report['is_confidential'])): ?>
                <span class="badge" style="background:#6b7280;">🔒 Confidentiel</span>
                <?php endif; ?>
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
                <?php if (!empty($report['attachment_name'])): ?>
                <tr>
                    <th>Pièce jointe</th>
                    <td>
                        <?php
                        $isImageAttachment = !empty($report['attachment_mime']) && in_array($report['attachment_mime'], ['image/jpeg', 'image/png', 'image/gif']);
                        ?>
                        <?php if ($isImageAttachment): ?>
                            <div style="margin-bottom:8px;">
                                <a href="<?php echo url('report_attachment', ['uuid' => $report['uuid']]); ?>" 
                                   title="<?php echo e($report['attachment_name']); ?> — Télécharger">
                                    <img src="<?php echo url('report_attachment', ['uuid' => $report['uuid'], 'inline' => 1]); ?>" 
                                         alt="<?php echo e($report['attachment_name']); ?>"
                                         style="max-width:100%;max-height:400px;border-radius:6px;border:1px solid #e5e7eb;cursor:pointer;">
                                </a>
                            </div>
                            <a href="<?php echo url('report_attachment', ['uuid' => $report['uuid']]); ?>" 
                               class="btn btn--outline" style="font-size:13px;padding:4px 12px;">
                                ⬇️ <?php echo e($report['attachment_name']); ?>
                            </a>
                        <?php else: ?>
                            <a href="<?php echo url('report_attachment', ['uuid' => $report['uuid']]); ?>" 
                               class="btn btn--outline" style="font-size:13px;padding:4px 12px;">
                                📎 <?php echo e($report['attachment_name']); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
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
        <a href="<?php echo url('report_edit', ['uuid' => $report['uuid']]); ?>" class="btn btn--secondary">Modifier</a>
    <?php endif; ?>

    <?php if ($canRespondToReport): ?>
        <a href="<?php echo url('report_respond', ['uuid' => $report['uuid']]); ?>" class="btn btn--primary">Répondre</a>
    <?php endif; ?>

    <?php if ($canAbandon): ?>
        <?php if (isset($_GET['confirm_abandon'])): ?>
        <span style="font-weight:600;color:var(--dgi-color);">⚠️ Abandonner ce signalement ?</span>
        <form method="POST" action="<?php echo url('report_abandon', ['uuid' => $report['uuid']]); ?>" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
            <input type="hidden" name="report_uuid" value="<?php echo e($report['uuid']); ?>">
            <button type="submit" class="btn btn--danger">Oui, abandonner</button>
        </form>
        <a href="<?php echo url('report_view', ['uuid' => $report['uuid']]); ?>" class="btn btn--secondary">Annuler</a>
        <?php else: ?>
        <a href="<?php echo url('report_view', ['uuid' => $report['uuid'], 'confirm_abandon' => 1]); ?>" class="btn btn--danger">Abandonner le signalement</a>
        <?php endif; ?>
    <?php endif; ?>

    <a href="<?php echo url('report_print', ['uuid' => $report['uuid']]); ?>" class="btn btn--outline">Télécharger en PDF</a>
    <a href="<?php echo url('report_list', ['type' => $type]); ?>" class="btn btn--secondary">Retour à la liste</a>
</div>


