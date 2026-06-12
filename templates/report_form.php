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
    <h2 class="mb-4">
        <?php echo $isEdit ? 'Modifier le signalement' : 'Inscrire un signalement'; ?> — <?php echo e($registryFullLabel); ?>
    </h2>

    <form method="POST" action="<?php echo e($action); ?>" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="type" value="<?php echo e($type); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
        <?php if ($isEdit): ?>
            <input type="hidden" name="report_uuid" value="<?php echo e($report['uuid'] ?? ''); ?>">
        <?php endif; ?>

        <div class="form-grid">
            <div class="form-group">
                <label for="date_evenement">Date de l'événement <span class="required">*</span></label>
                <input type="date" id="date_evenement" name="date_evenement"
                       value="<?php echo e($val('date_evenement', todayISO())); ?>"
                       required max="<?php echo todayISO(); ?>"
                       <?php echo isset($formErrors['date_evenement']) ? 'aria-describedby="err_date_evenement" aria-invalid="true"' : ''; ?>>
                <?php if (isset($formErrors['date_evenement'])): ?>
                    <span class="form-error" id="err_date_evenement"><?php echo e($formErrors['date_evenement']); ?></span>
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
                       placeholder="Résumé du signalement"
                       <?php echo isset($formErrors['objet']) ? 'aria-describedby="err_objet" aria-invalid="true"' : ''; ?>>
                <span class="form-hint">100 caractères max.</span>
                <?php if (isset($formErrors['objet'])): ?>
                    <span class="form-error" id="err_objet"><?php echo e($formErrors['objet']); ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group form-grid__full">
                <label for="description">Description <span class="required">*</span></label>
                <textarea id="description" name="description" rows="8" maxlength="20000" required
                          placeholder="Décrivez le signalement en détail..."
                          <?php echo isset($formErrors['description']) ? 'aria-describedby="err_description" aria-invalid="true"' : ''; ?>><?php echo e($val('description')); ?></textarea>
                <span class="form-hint">20 000 caractères max.</span>
                <?php if (isset($formErrors['description'])): ?>
                    <span class="form-error" id="err_description"><?php echo e($formErrors['description']); ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group form-grid__full">
                <label for="attachment">Pièce jointe</label>
                <input type="file" id="attachment" name="attachment"
                       accept=".jpg,.jpeg,.png,.gif,.pdf"
                       <?php echo isset($formErrors['attachment']) ? 'aria-describedby="err_attachment" aria-invalid="true"' : ''; ?>>
                <span class="form-hint">Image (JPG, PNG, GIF) ou PDF — 10 Mo max. Optionnel.</span>
                <?php if ($isEdit && !empty($report['attachment_name'])): ?>
                    <div class="attachment-preview">
                        <span class="badge badge--confidential">&#128206; <?php echo e($report['attachment_name']); ?></span>
                        <label class="attachment-remove-label">
                            <input type="checkbox" name="remove_attachment" value="1"> Supprimer la pièce jointe actuelle
                        </label>
                    </div>
                <?php endif; ?>
                <?php if (isset($formErrors['attachment'])): ?>
                    <span class="form-error" id="err_attachment"><?php echo e($formErrors['attachment']); ?></span>
                <?php endif; ?>
            </div>

            <?php if (!$isEdit): ?>
            <div class="form-group">
                <label for="site_id"><?php echo e(getConfig('app_label_unite', 'UR')); ?> <span class="required">*</span></label>
                <select id="site_id" name="site_id" required
                        <?php echo isset($formErrors['site_id']) ? 'aria-describedby="err_site_id" aria-invalid="true"' : ''; ?>>
                    <?php foreach ($sites as $site): ?>
                        <option value="<?php echo e($site['id']); ?>"
                            <?php echo ((int)$val('site_id', (string)$user['site_id']) === (int)$site['id']) ? 'selected' : ''; ?>>
                            <?php echo e($site['code'] . ' — ' . $site['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($formErrors['site_id'])): ?>
                    <span class="form-error" id="err_site_id"><?php echo e($formErrors['site_id']); ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (reportVisibilityIsAgentChoice()): ?>
            <div class="form-group form-grid__full confidential-toggle" id="confidential-toggle">
                <label class="label--checkbox">
                    <input type="checkbox" name="is_confidential" id="is_confidential" value="1"
                           class="confidential-toggle__input"
                           <?php echo $val('is_confidential', '1') === '1' ? 'checked' : ''; ?>>
                    Signalement confidentiel
                </label>
                <span class="form-hint">Si coché, ce signalement ne sera visible que par vous, les superviseurs et les membres du CHSCT. Décochez pour le rendre visible par tous les agents de votre <?php echo e(getConfig('app_label_unite', 'UR')); ?>.</span>
                <!-- Warning visible uniquement quand la case est décochée — CSS :has(), pas de JavaScript -->
                <div class="confidential-warning">
                    &#9888; <strong>Attention :</strong> ce signalement sera visible par tous les agents de votre <?php echo e(getConfig('app_label_unite', 'UR')); ?>, y compris son objet et sa description.
                </div>
            </div>
            <?php elseif (reportVisibilityIsConfidential()): ?>
            <input type="hidden" name="is_confidential" value="1">
            <div class="form-group form-grid__full">
                <span class="badge badge--confidential">&#128274; Confidentiel</span>
                <span class="form-hint">Le mode de visibilité est « Confidentiel » : votre signalement n'est visible que par vous, les superviseurs et les membres du CHSCT.</span>
            </div>
            <?php elseif (reportVisibilityIsPublic()): ?>
            <input type="hidden" name="is_confidential" value="0">
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
            <div class="form-group form-grid__full">
                <label class="label--checkbox">
                    <input type="checkbox" name="pour_compte" id="pour_compte" value="1"
                           <?php echo ($val('pour_compte') || ($isEdit && !empty($report['pour_compte_nom']))) ? 'checked' : ''; ?>>
                    Signaler pour le compte d'un autre agent
                </label>
            </div>

            <div id="pour_compte_fields" class="form-group form-grid__full pour-compte-fields">
                <div class="flex-row">
                    <div>
                        <label for="pour_compte_nom">Nom de l'agent</label>
                        <input type="text" id="pour_compte_nom" name="pour_compte_nom"
                               value="<?php echo e($val('pour_compte_nom')); ?>"
                               <?php echo isset($formErrors['pour_compte_nom']) ? 'aria-describedby="err_pour_compte_nom" aria-invalid="true"' : ''; ?>>
                    </div>
                    <div>
                        <label for="pour_compte_prenom">Prénom de l'agent</label>
                        <input type="text" id="pour_compte_prenom" name="pour_compte_prenom"
                               value="<?php echo e($val('pour_compte_prenom')); ?>">
                    </div>
                </div>
                <?php if (isset($formErrors['pour_compte_nom'])): ?>
                    <span class="form-error" id="err_pour_compte_nom"><?php echo e($formErrors['pour_compte_nom']); ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn <?php echo $submitBtnClass; ?>">
                <?php echo $isEdit ? 'Enregistrer' : 'Valider son signalement'; ?>
            </button>
            <a href="<?php echo $isEdit && $report ? url('report_view', ['uuid' => $report['uuid']]) : url('home'); ?>"
               class="btn btn--secondary">Annuler</a>
        </div>
    </form>
</div>
