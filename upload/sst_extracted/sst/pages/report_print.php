<?php
/**
 * Report Print Page — Application SST DREETS BFC
 *
 * Print-friendly view of a single report. No header/sidebar.
 * Auto-triggers window.print() via JavaScript.
 * URL: index.php?page=report_print&id={report_id}
 *
 * NOTE: This page is included by the router BEFORE header/sidebar.
 * The router has already started the session, loaded config/database/helpers/queries,
 * and checked authentication. We do NOT need to re-require or re-start session.
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

// Access control: confidentiality check
// Le signalement est confidentiel : seuls le déclarant, les superviseurs,
// les managers et les membres CHSCT peuvent y accéder.
if (!canAccessReport($report)) {
    setFlash('error', 'Vous n\'avez pas accès à ce signalement.');
    redirect(url('home'));
}

// Get response history
$responses = getReportResponses($pdo, $id);

$type = $report['type'] ?? 'rsst';
$registryLabel = REGISTRY_LABELS[$type] ?? strtoupper($type);
$registryShortLabel = REGISTRY_SHORT_LABELS[$type] ?? strtoupper($type);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signalement <?php echo e($report['reference']); ?> — Impression</title>
    <link rel="stylesheet" href="<?php echo assetUrl('css/style.css'); ?>">
</head>
<body>
<div class="print-view">
    <div class="print-view__header">
        <strong><?php echo e(getConfig('app_nom_complet', 'DREETS Bourgogne-Franche-Comté')); ?></strong>
        <div class="print-view__title">Signalement — <?php echo e($report['reference']); ?></div>
        <div>
            <span class="badge <?php echo getRegistryBadgeClass($type); ?>"><?php echo e($registryShortLabel); ?></span>
            <span class="badge <?php echo getEtatBadgeClass($report['etat']); ?>"><?php echo e(ETAT_LABELS[$report['etat']] ?? $report['etat']); ?></span>
        </div>
    </div>

    <div class="print-view__field">
        <div class="print-view__label">Référence</div>
        <div class="print-view__value"><?php echo e($report['reference']); ?></div>
    </div>

    <div class="print-view__field">
        <div class="print-view__label">Registre</div>
        <div class="print-view__value"><?php echo e($registryLabel); ?></div>
    </div>

    <div class="print-view__field">
        <div class="print-view__label">Date de l'événement</div>
        <div class="print-view__value"><?php echo formatDateFR($report['date_evenement']); ?></div>
    </div>

    <div class="print-view__field">
        <div class="print-view__label">Heure de l'événement</div>
        <div class="print-view__value"><?php echo e($report['heure_evenement'] ?? '—'); ?></div>
    </div>

    <div class="print-view__field">
        <div class="print-view__label">Lieu</div>
        <div class="print-view__value"><?php echo e($report['lieu'] ?? '—'); ?></div>
    </div>

    <div class="print-view__field">
        <div class="print-view__label">Objet</div>
        <div class="print-view__value"><?php echo e($report['objet']); ?></div>
    </div>

    <div class="print-view__field">
        <div class="print-view__label">Description</div>
        <div class="print-view__value"><?php echo nl2br(e($report['description'])); ?></div>
    </div>

    <div class="print-view__field">
        <div class="print-view__label">Déclarant</div>
        <div class="print-view__value"><?php echo e($report['declarant_prenom'] . ' ' . $report['declarant_nom']); ?></div>
    </div>

    <div class="print-view__field">
        <div class="print-view__label"><?php echo e(getConfig('app_label_unite', 'UR')); ?></div>
        <div class="print-view__value"><?php echo e($report['site_nom'] ?? '—'); ?> (<?php echo e($report['site_code'] ?? '—'); ?>)</div>
    </div>

    <?php if ($type === 'rami' && !empty($report['pour_compte_nom'])): ?>
    <div class="print-view__field">
        <div class="print-view__label">Déclaré pour le compte de</div>
        <div class="print-view__value"><?php echo e(($report['pour_compte_prenom'] ?? '') . ' ' . $report['pour_compte_nom']); ?></div>
    </div>
    <?php endif; ?>

    <div class="print-view__field">
        <div class="print-view__label">Date de création</div>
        <div class="print-view__value"><?php echo formatDateTimeFR($report['created_at']); ?></div>
    </div>

    <div class="print-view__field">
        <div class="print-view__label">État</div>
        <div class="print-view__value">
            <span class="badge <?php echo getEtatBadgeClass($report['etat']); ?>">
                <?php echo e(ETAT_LABELS[$report['etat']] ?? $report['etat']); ?>
            </span>
        </div>
    </div>

    <?php if (!empty($report['reponse'])): ?>
    <hr style="margin:24px 0;border:none;border-top:1px solid #ccc;">
    <h3>Réponse</h3>
    <div class="print-view__field">
        <div class="print-view__value" style="background:var(--grey-50);padding:12px;border-radius:4px;border-left:4px solid var(--state-traite);">
            <?php echo nl2br(e($report['reponse'])); ?>
        </div>
    </div>
    <div class="print-view__field">
        <div class="print-view__label">Répondant</div>
        <div class="print-view__value"><?php echo e(($report['repondant_prenom'] ?? '') . ' ' . ($report['repondant_nom'] ?? '')); ?></div>
    </div>
    <div class="print-view__field">
        <div class="print-view__label">Date de réponse</div>
        <div class="print-view__value"><?php echo formatDateTimeFR($report['date_reponse']); ?></div>
    </div>
    <?php endif; ?>

    <?php if (!empty($responses)): ?>
    <hr style="margin:24px 0;border:none;border-top:1px solid #ccc;">
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
    <?php endif; ?>

    <hr style="margin:24px 0;border:none;border-top:1px solid #ccc;">
    <div class="print-hint">
        Utilisez Ctrl+P pour imprimer ce document
    </div>
    <div style="text-align:center;color:var(--grey-500);font-size:12px;margin-top:8px;">
        Document généré le <?php echo formatDateFR(date('Y-m-d')); ?> — <?php echo e(getConfig('app_nom_organisation', 'DREETS BFC')); ?>
    </div>
</div>

<script>
// Auto-trigger print dialog after a short delay
window.addEventListener('load', function() {
    setTimeout(function() { window.print(); }, 500);
});
</script>
</body>
</html>
