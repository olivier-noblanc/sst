<?php
/**
 * Report Respond Page — Application SST DREETS BFC
 * 
 * Superviseur responds to a report.
 * Access: superviseur only.
 */
requireRole(['superviseur']);

$pdo = getDB();
$uuid = $_GET['uuid'] ?? '';

if (!isValidUuid($uuid)) {
    setFlash('error', 'Signalement introuvable.');
    redirect(url('home'));
}

$report = getReportByUuid($pdo, $uuid);

if (!$report) {
    setFlash('error', 'Signalement introuvable.');
    redirect(url('home'));
}

// Check if report can be responded to
if (!in_array($report['etat'], ['nouveau', 'en_cours'])) {
    setFlash('error', 'Ce signalement ne peut plus recevoir de réponse (état : ' . e(ETAT_LABELS[$report['etat']] ?? $report['etat']) . ').');
    redirect(url('report_view', ['uuid' => $uuid]));
}

// Get response history
$responses = getReportResponses($pdo, $uuid);

$pageTitle = 'Répondre au signalement — ' . e($report['reference']);
$registryType = $report['type'];
$registryLabel = REGISTRY_SHORT_LABELS[$registryType] ?? strtoupper($registryType);

// Get form errors and data from session
$formErrors = getFormErrors();
$formData = getFormData();
?>

<h1 class="page-title">Répondre au signalement — <span class="badge <?php echo getRegistryBadgeClass($registryType); ?>"><?php echo e($report['reference']); ?></span></h1>

<?php require __DIR__ . '/../templates/alert.php'; ?>

<!-- Report Summary (read-only) -->
<div class="card card--<?php echo e($registryType); ?>">
    <h3 style="margin-bottom:12px;">Résumé du signalement</h3>
    <table class="report-detail__table">
        <tr>
            <th>Référence</th>
            <td><?php echo e($report['reference']); ?></td>
        </tr>
        <tr>
            <th>Registre</th>
            <td><span class="badge <?php echo getRegistryBadgeClass($registryType); ?>"><?php echo e($registryLabel); ?></span></td>
        </tr>
        <tr>
            <th>Date de l'événement</th>
            <td><?php echo e(formatDateFR($report['date_evenement'])); ?></td>
        </tr>
        <tr>
            <th>Déclarant</th>
            <td><?php echo e($report['declarant_prenom'] . ' ' . $report['declarant_nom']); ?></td>
        </tr>
        <tr>
            <th><?php echo e(getConfig('app_label_unite', 'UR')); ?></th>
            <td><?php echo e($report['site_nom'] ?? '—'); ?></td>
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
            <th>État actuel</th>
            <td><span class="badge <?php echo getEtatBadgeClass($report['etat']); ?>"><?php echo e(ETAT_LABELS[$report['etat']] ?? $report['etat']); ?></span></td>
        </tr>
    </table>
</div>

<!-- Previous responses -->
<?php if (!empty($responses)): ?>
<div class="card">
    <h3 style="margin-bottom:12px;">Historique des réponses</h3>
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
                    <td><?php echo e(formatDateTimeFR($resp['created_at'])); ?></td>
                    <td><?php echo e(($resp['prenom'] ?? '') . ' ' . ($resp['nom'] ?? '')); ?></td>
                    <td><span class="badge <?php echo getEtatBadgeClass($resp['nouvel_etat'] ?? ''); ?>"><?php echo e(ETAT_LABELS[$resp['nouvel_etat']] ?? $resp['nouvel_etat'] ?? '—'); ?></span></td>
                    <td><?php echo nl2br(e($resp['reponse'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Response Form -->
<div class="card">
    <h3 style="margin-bottom:16px;">Formuler une réponse</h3>
    <form method="POST" action="<?php echo url('report_respond', ['uuid' => $uuid]); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
        <input type="hidden" name="report_uuid" value="<?php echo e($uuid); ?>">

        <div class="form-group">
            <label for="nouvel_etat">Nouvel état <span class="required">*</span></label>
            <select name="nouvel_etat" id="nouvel_etat" required>
                <option value="en_cours" <?php echo (isset($formData['nouvel_etat']) && $formData['nouvel_etat'] === 'en_cours') ? 'selected' : ''; ?>>En cours</option>
                <option value="traite" <?php echo (isset($formData['nouvel_etat']) && $formData['nouvel_etat'] === 'traite') ? 'selected' : ''; ?>>Traité</option>
            </select>
            <?php if (isset($formErrors['nouvel_etat'])): ?>
                <span class="form-error"><?php echo e($formErrors['nouvel_etat']); ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="reponse">Réponse <span class="required">*</span></label>
            <textarea name="reponse" id="reponse" rows="6" maxlength="5000" required placeholder="Saisissez votre réponse..."><?php echo e($formData['reponse'] ?? ''); ?></textarea>
            <div class="form-hint">Maximum 5000 caractères</div>
            <?php if (isset($formErrors['reponse'])): ?>
                <span class="form-error"><?php echo e($formErrors['reponse']); ?></span>
            <?php endif; ?>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn--primary">Enregistrer les modifications</button>
            <a href="<?php echo url('report_view', ['uuid' => $uuid]); ?>" class="btn btn--secondary">Annuler</a>
        </div>
    </form>
</div>
