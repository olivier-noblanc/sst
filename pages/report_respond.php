<?php
/**
 * Report Respond Page — Application SST DREETS BFC
 *
 * Superviseur responds to a report.
 * Access: superviseur only.
 */
requireRole([ROLE_SUPERVISEUR]);

// Service instances (created once for the page)
$fmt = new \App\Services\FormattingService();
$http = new \App\Services\HttpService();
$config = \App\Services\ConfigService::getInstance();
$session = new \App\Services\SessionService();

$uuid = $_GET['uuid'] ?? '';
$report = fetchReportOrRedirect($uuid);

// Check if report can be responded to
requireReportEditable($report, $uuid, 'répondu');

$pdo = getContainer()->get(\PDO::class);
$noSiteMode = $config->isNoSiteMode();

// Get response history
$responses = \App\Repository\ReportRepository::instance()->getResponses($uuid);

$pageTitle = 'Répondre au signalement — ' . $fmt->e($report['reference']);
$registryType = $report['type'];
$registryLabel = REGISTRY_SHORT_LABELS[$registryType] ?? strtoupper((string) $registryType);

// Get form errors and data from session
$formErrors = $session->getFormErrors();
$formData = $session->getFormData();
?>

<h1 class="page-title">Répondre au signalement — <span class="badge <?php echo $fmt->getRegistryBadgeClass($registryType); ?>"><?php echo $fmt->e($report['reference']); ?></span></h1>

<?php echo $fmt->renderBreadcrumb([
    ['url' => $http->url('home'), 'label' => 'Accueil'],
    ['url' => $http->url('report_list', ['type' => $registryType]), 'label' => $registryLabel],
    ['url' => $http->url('report_view', ['uuid' => $uuid]), 'label' => $report['reference']],
    ['label' => 'Répondre'],
]); ?>


<!-- Report Summary (read-only) -->
<div class="card card--<?php echo $fmt->e($registryType); ?>">
    <h3 class="card__subtitle">Résumé du signalement</h3>
    <table class="report-detail__table" aria-label="Détails du signalement">
        <tr>
            <th>Référence</th>
            <td><?php echo $fmt->e($report['reference']); ?></td>
        </tr>
        <tr>
            <th>Registre</th>
            <td><span class="badge <?php echo $fmt->getRegistryBadgeClass($registryType); ?>"><?php echo $fmt->e($registryLabel); ?></span></td>
        </tr>
        <tr>
            <th>Date de l'événement</th>
            <td><?php echo $fmt->e($fmt->formatDateFR($report['date_evenement'])); ?></td>
        </tr>
        <tr>
            <th>Déclarant</th>
            <td><?php echo $fmt->e($report['declarant_prenom'] . ' ' . $report['declarant_nom']); ?></td>
        </tr>
        <?php if (!$noSiteMode): ?>
        <tr>
            <th><?php echo $fmt->e($config->get('app_label_unite', 'UR')); ?></th>
            <td><?php echo $fmt->e($report['site_nom'] ?? '—'); ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <th><?php echo $type === 'dgi' ? 'Lieu / Mesures de protection' : 'Lieu'; ?></th>
            <td><?php echo $fmt->e($report['lieu'] ?? '—'); ?></td>
        </tr>
        <?php if (!empty($report['pole'])): ?>
        <tr>
            <th>Pôle</th>
            <td><?php echo $fmt->e($report['pole']); ?></td>
        </tr>
        <?php endif; ?>
        <?php if (!empty($report['service_affectation'])): ?>
        <tr>
            <th>Service d'affectation</th>
            <td><?php echo $fmt->e($report['service_affectation']); ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <th>Objet</th>
            <td><?php echo $fmt->e($report['objet']); ?></td>
        </tr>
        <tr>
            <th>Description</th>
            <td><?php echo nl2br($fmt->e($report['description'])); ?></td>
        </tr>
        <tr>
            <th>État actuel</th>
            <td><span class="badge <?php echo $fmt->getEtatBadgeClass($report['etat']); ?>"><?php echo $fmt->e(ETAT_LABELS[$report['etat']] ?? $report['etat']); ?></span></td>
        </tr>
    </table>
</div>

<!-- Previous responses -->
<?php if (!empty($responses)): ?>
<div class="card">
    <h3 class="card__subtitle">Historique des réponses</h3>
    <div class="table-wrapper">
        <table aria-label="Formulaire de réponse">
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
                    <td><?php echo $fmt->e($fmt->formatDateTimeFR($resp['created_at'])); ?></td>
                    <td><?php echo $fmt->e(($resp['prenom'] ?? '') . ' ' . ($resp['nom'] ?? '')); ?></td>
                    <td><span class="badge <?php echo $fmt->getEtatBadgeClass($resp['nouvel_etat'] ?? ''); ?>"><?php echo $fmt->e(ETAT_LABELS[$resp['nouvel_etat']] ?? $resp['nouvel_etat'] ?? '—'); ?></span></td>
                    <td><?php echo nl2br($fmt->e($resp['reponse'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Response Form -->
<div class="card">
    <h3 class="card__title">Formuler une réponse</h3>
    <form method="POST" action="<?php echo $http->url('report_respond', ['uuid' => $uuid]); ?>" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo $fmt->e($csrfToken); ?>">
        <input type="hidden" name="report_uuid" value="<?php echo $fmt->e($uuid); ?>">

        <div class="form-group">
            <label for="nouvel_etat">Nouvel état <span class="required">*</span></label>
            <select name="nouvel_etat" id="nouvel_etat" required
                    <?php echo isset($formErrors['nouvel_etat']) ? 'aria-describedby="err_nouvel_etat" aria-invalid="true"' : ''; ?>>
                <option value="en_cours" <?php echo (isset($formData['nouvel_etat']) && $formData['nouvel_etat'] === 'en_cours') ? 'selected' : ''; ?>>En cours</option>
                <option value="traite" <?php echo (isset($formData['nouvel_etat']) && $formData['nouvel_etat'] === 'traite') ? 'selected' : ''; ?>>Traité</option>
            </select>
            <?php if (isset($formErrors['nouvel_etat'])): ?>
                <span class="form-error" id="err_nouvel_etat"><?php echo $fmt->e($formErrors['nouvel_etat']); ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="reponse">Réponse <span class="required">*</span></label>
            <textarea name="reponse" id="reponse" rows="6" maxlength="5000" required placeholder="Saisissez votre réponse..." aria-describedby="hint_reponse"><?php echo $fmt->e($formData['reponse'] ?? ''); ?></textarea>
            <div class="form-hint" id="hint_reponse">Maximum 5000 caractères</div>
            <?php if (isset($formErrors['reponse'])): ?>
                <span class="form-error" id="err_reponse"><?php echo $fmt->e($formErrors['reponse']); ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="response_attachment">Pièce jointe (optionnel)</label>
            <div class="file-upload-wrapper">
                <input type="file" id="response_attachment" name="response_attachment"
                       accept=".jpg,.jpeg,.png,.gif,.pdf"
                       title="Joindre un document à la réponse"
                       class="file-upload-wrapper__input"
                       aria-describedby="hint_response_attachment">
                <label for="response_attachment" class="file-upload-wrapper__label btn btn--secondary">
                    📎 Joindre un document (optionnel)
                </label>
                <span class="file-upload-wrapper__filename" id="resp_file_chosen_name">Aucun fichier sélectionné</span>
            </div>
            <span class="form-hint" id="hint_response_attachment">Image (JPG, PNG, GIF) ou PDF — 10 Mo max.</span>
            <?php if (isset($formErrors['response_attachment'])): ?>
                <span class="form-error"><?php echo $fmt->e($formErrors['response_attachment']); ?></span>
            <?php endif; ?>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn--primary">Enregistrer les modifications</button>
            <a href="<?php echo $http->url('report_view', ['uuid' => $uuid]); ?>" class="btn btn--secondary">Annuler</a>
        </div>
    </form>
</div>

<script>
(function() {
    var input = document.getElementById('response_attachment'), nameEl = document.getElementById('resp_file_chosen_name');
    if (input && nameEl) input.addEventListener('change', function() {
        if (this.files && this.files.length > 0) {
            nameEl.textContent = this.files[0].name; nameEl.style.fontStyle = 'normal'; nameEl.style.color = '';
        } else { nameEl.textContent = 'Aucun fichier sélectionné'; nameEl.style.fontStyle = 'italic'; }
    });
})();
</script>
