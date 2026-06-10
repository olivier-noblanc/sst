<?php
/**
 * Report Form Template — Application SST DREETS BFC
 * 
 * Shared form for creating and editing reports (RSST, RAMI, DGI).
 * 
 * Required variables:
 *   $type        — Registry type: 'rsst', 'rami', 'dgi'
 *   $action      — Form action URL
 *   $isEdit      — Whether this is an edit form (bool)
 *   $report      — Existing report data for edit (array or null)
 *   $csrfToken   — CSRF token value
 *   $sites       — Array of sites for the dropdown
 *   $formErrors  — Array of field errors
 *   $formData    — Array of submitted form data for repopulation
 */
if (!isset($isEdit)) $isEdit = false;
if (!isset($report)) $report = null;
if (!isset($formErrors)) $formErrors = getFormErrors();
if (!isset($formData)) $formData = getFormData();

$user = $_SESSION['user'] ?? [];

// Determine values: prefer form data (on validation error), then report data, then defaults
$val = function(string $field, string $default = '') use ($formData, $report, $isEdit) {
    if (isset($formData[$field]) && $formData[$field] !== '') {
        return $formData[$field];
    }
    if ($isEdit && $report && isset($report[$field])) {
        return $report[$field];
    }
    return $default;
};

$registryLabel = REGISTRY_SHORT_LABELS[$type] ?? strtoupper($type);
$registryFullLabel = REGISTRY_LABELS[$type] ?? $type;

// Determine card accent class
$cardClass = match($type) {
    'rsst' => 'card--rsst',
    'rami' => 'card--rami',
    'dgi'  => 'card--dgi',
    default => 'card--rsst',
};

// Determine submit button class based on mode and registry type
$submitBtnClass = $isEdit
    ? match($type) { 'rsst' => 'btn--rsst', 'rami' => 'btn--rami', 'dgi' => 'btn--dgi', default => 'btn--primary' }
    : 'btn--warning'; // orange/coral for create mode
?>
<div class="card <?php echo $cardClass; ?>">
    <h2 style="margin-bottom:16px;">
        <?php echo $isEdit ? 'Modifier le signalement' : 'Inscrire un signalement'; ?> — <?php echo e($registryFullLabel); ?>
    </h2>

    <form method="POST" action="<?php echo e($action); ?>" novalidate>
        <input type="hidden" name="type" value="<?php echo e($type); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
        <?php if ($isEdit): ?>
            <input type="hidden" name="report_id" value="<?php echo e($report['id'] ?? ''); ?>">
        <?php endif; ?>

        <div class="form-grid">
            <div class="form-group">
                <label for="date_evenement">Date de l'événement <span class="required">*</span></label>
                <input type="date" id="date_evenement" name="date_evenement"
                       value="<?php echo e($val('date_evenement', todayISO())); ?>"
                       required max="<?php echo todayISO(); ?>">
                <?php if (isset($formErrors['date_evenement'])): ?>
                    <span class="form-error"><?php echo e($formErrors['date_evenement']); ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="heure_evenement">Heure de l'événement</label>
                <input type="time" id="heure_evenement" name="heure_evenement"
                       value="<?php echo e($val('heure_evenement', nowTime())); ?>">
            </div>

            <div class="form-group">
                <label for="lieu">Lieu</label>
                <input type="text" id="lieu" name="lieu"
                       value="<?php echo e($val('lieu')); ?>"
                       maxlength="200"
                       placeholder="Ex: Bureau 204, UR25">
                <span class="form-hint">200 caractères max.</span>
            </div>

            <div class="form-group">
                <label for="objet">Objet <span class="required">*</span></label>
                <input type="text" id="objet" name="objet"
                       value="<?php echo e($val('objet')); ?>"
                       maxlength="100" required
                       placeholder="Résumé du signalement">
                <span class="form-hint">100 caractères max.</span>
                <?php if (isset($formErrors['objet'])): ?>
                    <span class="form-error"><?php echo e($formErrors['objet']); ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group" style="grid-column: 1 / -1;">
                <label for="description">Description <span class="required">*</span></label>
                <textarea id="description" name="description" rows="8" maxlength="5000" required
                          placeholder="Décrivez le signalement en détail..."><?php echo e($val('description')); ?></textarea>
                <span class="form-hint">5000 caractères max.</span>
                <?php if (isset($formErrors['description'])): ?>
                    <span class="form-error"><?php echo e($formErrors['description']); ?></span>
                <?php endif; ?>
            </div>

            <?php if (!$isEdit): ?>
            <div class="form-group">
                <label for="site_id"><?php echo e(getConfig('app_label_unite', 'UR')); ?> <span class="required">*</span></label>
                <select id="site_id" name="site_id" required>
                    <?php foreach ($sites as $site): ?>
                        <option value="<?php echo e($site['id']); ?>"
                            <?php echo ((int)$val('site_id', (string)$user['site_id']) === (int)$site['id']) ? 'selected' : ''; ?>>
                            <?php echo e($site['code'] . ' — ' . $site['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($formErrors['site_id'])): ?>
                    <span class="form-error"><?php echo e($formErrors['site_id']); ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (reportVisibilityIsAgentChoice()): ?>
            <div class="form-group" style="grid-column: 1 / -1;">
                <label>
                    <input type="checkbox" name="is_confidential" id="is_confidential" value="1"
                           <?php echo $val('is_confidential', '1') === '1' ? 'checked' : ''; ?>>
                    Signalement confidentiel
                </label>
                <span class="form-hint">Si coché, ce signalement ne sera visible que par vous, les superviseurs et les membres du CHSCT. Décochez pour le rendre visible par tous les agents de votre <?php echo e(getConfig('app_label_unite', 'UR')); ?>.</span>
            </div>
            <?php elseif (reportVisibilityIsConfidential()): ?>
            <input type="hidden" name="is_confidential" value="1">
            <div class="form-group" style="grid-column: 1 / -1;">
                <span class="badge" style="background:#6b7280;">🔒 Confidentiel</span>
                <span class="form-hint">Le mode de visibilité est « Confidentiel » : votre signalement n'est visible que par vous, les superviseurs et les membres du CHSCT.</span>
            </div>
            <?php elseif (reportVisibilityIsPublic()): ?>
            <input type="hidden" name="is_confidential" value="0">
            <?php elseif ($isEdit && !empty($report['is_confidential'])): ?>
            <div class="form-group" style="grid-column: 1 / -1;">
                <span class="badge" style="background:#6b7280;">🔒 Confidentiel</span>
                <span class="form-hint">Ce signalement est confidentiel.</span>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="declarant_nom">Déclarant — Nom</label>
                <input type="text" id="declarant_nom" value="<?php echo e($user['nom'] ?? ''); ?>" readonly>
            </div>

            <div class="form-group">
                <label for="declarant_prenom">Déclarant — Prénom</label>
                <input type="text" id="declarant_prenom" value="<?php echo e($user['prenom'] ?? ''); ?>" readonly>
            </div>

            <?php if ($type === 'rami'): ?>
            <div class="form-group" style="grid-column: 1 / -1;">
                <label>
                    <input type="checkbox" name="pour_compte" id="pour_compte" value="1"
                           <?php echo ($val('pour_compte') || ($isEdit && !empty($report['pour_compte_nom']))) ? 'checked' : ''; ?>>
                    Signaler pour le compte d'un autre agent
                </label>
            </div>

            <div id="pour_compte_fields" class="form-group" style="grid-column: 1 / -1;">
                <div style="display:flex;gap:12px;flex-wrap:wrap;">
                    <div style="flex:1;min-width:200px;">
                        <label for="pour_compte_nom">Nom de l'agent</label>
                        <input type="text" id="pour_compte_nom" name="pour_compte_nom"
                               value="<?php echo e($val('pour_compte_nom')); ?>">
                    </div>
                    <div style="flex:1;min-width:200px;">
                        <label for="pour_compte_prenom">Prénom de l'agent</label>
                        <input type="text" id="pour_compte_prenom" name="pour_compte_prenom"
                               value="<?php echo e($val('pour_compte_prenom')); ?>">
                    </div>
                </div>
                <?php if (isset($formErrors['pour_compte_nom'])): ?>
                    <span class="form-error"><?php echo e($formErrors['pour_compte_nom']); ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn <?php echo $submitBtnClass; ?>">
                <?php echo $isEdit ? 'Enregistrer' : 'Valider son signalement'; ?>
            </button>
            <a href="<?php echo $isEdit && $report ? url('report_view', ['id' => $report['id']]) : url('home'); ?>"
               class="btn btn--secondary">Annuler</a>
        </div>
    </form>
</div>

<?php if ($type === 'rami'): ?>
<style>
/* Toggle "pour le compte de" fields with CSS only — no JavaScript */
.form-grid:not(:has(#pour_compte:checked)) #pour_compte_fields { display: none; }
.form-grid:has(#pour_compte:checked) #pour_compte_fields { display: block; }
</style>
<?php endif; ?>
