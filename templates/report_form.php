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

$user = currentUser() ?? [];
$noSiteMode = isNoSiteMode(getDB());

/** @var string $type */
/** @var string $action */
/** @var string $csrfToken */
/** @var list<array<string, mixed>> $sites */
/** @var bool $isEdit */
/** @var array<string, mixed>|null $report */
/** @var array<string, string> $formErrors */
/** @var array<string, mixed> $user */

// Determine values: prefer form data (on validation error), then report data, then defaults
/** @var array<string, mixed> $formData */
$val = function(string $field, string $default = '') use ($formData, $report, $isEdit): string {
    if (isset($formData[$field]) && $formData[$field] !== '') {
        $v = $formData[$field];
        return $v;
    }
    if ($isEdit && $report !== null && isset($report[$field])) {
        $v = $report[$field];
        return $v;
    }
    return $default;
};

$registryLabel = REGISTRY_SHORT_LABELS[$type] ?? strtoupper($type);
$registryFullLabel = (string) (REGISTRY_LABELS[$type] ?? $type);

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
    : 'btn--primary'; // blue for create mode (main action)
?>
<div class="card <?php echo $cardClass; ?>">
    <?php echo renderBreadcrumb([
        ['url' => url('home'), 'label' => 'Accueil'],
        ['url' => url('report_list', ['type' => $type]), 'label' => $registryLabel],
        ['label' => $isEdit ? 'Modifier' : 'Nouveau signalement'],
    ]); ?>
    <h2 class="mb-4">
        <?php echo $isEdit ? 'Modifier le signalement' : 'Signaler un événement'; ?> — <?php echo e($registryFullLabel); ?>
    </h2>
    <?php
    $preamble = getConfig('app_report_preamble', '');
    if (!empty($preamble)):
    ?>
    <div class="alert alert--info" role="note" style="white-space: pre-line;"><?php echo e($preamble); ?></div>
    <?php endif; ?>
    <div class="alert alert--info form-encouragement" role="note">
        💡 <strong>Remplissez les champs marqués d'une étoile <span class="required">*</span>, les autres sont optionnels.</strong>
    </div>
    <form method="POST" action="<?php echo e($action); ?>" enctype="multipart/form-data">
        <input type="hidden" name="type" value="<?php echo e($type); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
        <?php if ($isEdit): ?>
            <input type="hidden" name="report_uuid" value="<?php /** @var string $uuid */ $uuid = $report['uuid'] ?? ''; echo e($uuid); ?>">
        <?php endif; ?>
        <?php if (!empty($formErrors)): ?>
        <?php require __DIR__ . '/form_error_summary.php'; ?>
        <?php endif; ?>
        <div class="form-grid">
            <div class="form-group">
                <label for="date_evenement">Date de l'événement <span class="required">*</span></label>
                <input type="date" id="date_evenement" name="date_evenement"
                       value="<?php echo e($val('date_evenement', todayISO())); ?>"
                       required max="<?php echo todayISO(); ?>"
                       autocomplete="off"
                       <?php echo isset($formErrors['date_evenement']) ? 'aria-describedby="err_date_evenement" aria-invalid="true"' : ''; ?>>
                <?php if (isset($formErrors['date_evenement'])): ?>
                    <span class="form-error" id="err_date_evenement"><?php echo e($formErrors['date_evenement']); ?></span>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="heure_depot">Heure du dépôt</label>
                <input type="time" id="heure_depot" name="heure_evenement"
                       value="<?php echo e($val('heure_evenement', nowTime())); ?>"
                       readonly
                       autocomplete="off">
                <span class="form-hint">Rempli automatiquement au moment du dépôt.</span>
            </div>
            <div class="form-group">
                <label for="lieu"><?php echo $type === 'dgi' ? 'Lieu / Mesures de protection' : 'Lieu'; ?></label>
                <input type="text" id="lieu" name="lieu"
                       value="<?php echo e($val('lieu')); ?>"
                       maxlength="200"
                       autocomplete="off"
                       placeholder="Ex : Bâtiment B, 2e étage, couloir principal"
                       aria-describedby="hint_lieu">
                <span class="form-hint" id="hint_lieu">200 caractères max.<?php echo $type === 'dgi' ? ' Indiquez le lieu et les mesures de protection mises en place.' : ''; ?></span>
            </div>
            <div class="form-group">
                <label for="pole">Pôle</label>
                <input type="text" id="pole" name="pole"
                       value="<?php echo e($val('pole')); ?>"
                       maxlength="200"
                       autocomplete="off"
                       placeholder="Ex : Pôle Administratif">
            </div>
            <div class="form-group">
                <label for="service_affectation">Service d'affectation</label>
                <input type="text" id="service_affectation" name="service_affectation"
                       value="<?php echo e($val('service_affectation')); ?>"
                       maxlength="200"
                       autocomplete="off"
                       placeholder="Ex : Service juridique">
            </div>
            <div class="form-group">
                <label for="telephone_mobile">Numéro de téléphone mobile</label>
                <input type="tel" id="telephone_mobile" name="telephone_mobile"
                       value="<?php echo e($val('telephone_mobile')); ?>"
                       maxlength="20"
                       autocomplete="off"
                       placeholder="Ex : 06 12 34 56 78">
            </div>
            <div class="form-group">
                <label for="objet">Objet <span class="required">*</span></label>
                <input type="text" id="objet" name="objet"
                       value="<?php echo e($val('objet')); ?>"
                       minlength="3" maxlength="100" required
                       autocomplete="off"
                       placeholder="Ex : Escalier cassé au 2e étage"
                       <?php echo isset($formErrors['objet']) ? 'aria-describedby="err_objet" aria-invalid="true"' : 'aria-describedby="hint_objet"'; ?>>
                <span class="form-hint" id="hint_objet">100 caractères max.</span>
                <?php if (isset($formErrors['objet'])): ?>
                    <span class="form-error" id="err_objet"><?php echo e($formErrors['objet']); ?></span>
                <?php endif; ?>
            </div>
            <div class="form-group form-grid__full">
                <label for="description">Description <span class="required">*</span></label>
                <textarea id="description" name="description" rows="8" minlength="10" maxlength="20000" required
                          placeholder="Ex : La rampe est desserrée au 2e étage du bâtiment B. Quelqu'un pourrait tomber."
                          <?php echo isset($formErrors['description']) ? 'aria-describedby="err_description char_count_description" aria-invalid="true"' : 'aria-describedby="hint_description char_count_description"'; ?>><?php echo e($val('description')); ?></textarea>
                <span class="form-hint" id="hint_description">20 000 caractères max.</span>
                <?php
                $descLen = mb_strlen($val('description'));
                $counterClass = $descLen > 19000 ? 'char-counter char-counter--warning' : 'char-counter';
                ?>
                <span class="<?php echo $counterClass; ?>" id="char_count_description" aria-live="polite"><?php echo number_format($descLen, 0, '', ' '); ?>/20 000</span>
                <?php if (isset($formErrors['description'])): ?>
                    <span class="form-error" id="err_description"><?php echo e($formErrors['description']); ?></span>
                <?php endif; ?>
            </div>
            <div class="form-group form-grid__full">
                <label for="attachment">Pièce jointe</label>
                <div class="file-upload-wrapper">
                    <input type="file" id="attachment" name="attachment"
                           accept=".jpg,.jpeg,.png,.gif,.pdf"
                           title="Joindre un document"
                           class="file-upload-wrapper__input"
                           <?php echo isset($formErrors['attachment']) ? 'aria-describedby="err_attachment" aria-invalid="true"' : 'aria-describedby="hint_attachment"'; ?>>
                    <label for="attachment" class="file-upload-wrapper__label btn btn--secondary">
                        📎 Joindre un document (optionnel)
                    </label>
                    <span class="file-upload-wrapper__filename" id="file_chosen_name">Aucun fichier sélectionné</span>
                </div>
                <span class="form-hint" id="hint_attachment">Image (JPG, PNG, GIF) ou PDF — 10 Mo max.</span>
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
            <?php if (!$isEdit && !$noSiteMode): ?>
            <div class="form-group">
                <label for="site_id">Votre unité de rattachement <span class="required">*</span></label>
                <select id="site_id" name="site_id" required
                        <?php echo isset($formErrors['site_id']) ? 'aria-describedby="err_site_id" aria-invalid="true"' : 'aria-describedby="hint_site_id"'; ?>>
                    <?php foreach ($sites as $site): ?>
                        <option value="<?php echo e($site['id'] ?? ''); ?>"
                            <?php
                        $siteIdVal = $user['site_id'] ?? '';
                        $siteIdFromForm = $val('site_id', $siteIdVal);
                        $siteIdRaw = $site['id'] ?? '0';
                        echo ((int)$siteIdFromForm === (int)$siteIdRaw) ? 'selected' : '';
                        ?>>
                            <?php echo e(($site['code'] ?? '') . ' — ' . ($site['nom'] ?? '')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="form-hint" id="hint_site_id">Sélectionnez votre <?php echo e(getConfig('app_label_unite', 'UR')); ?> (unité de rattachement).</span>
                <?php if (isset($formErrors['site_id'])): ?>
                    <span class="form-error" id="err_site_id"><?php echo e($formErrors['site_id']); ?></span>
                <?php endif; ?>
            </div>
            <?php elseif (!$isEdit && $noSiteMode): ?>
            <input type="hidden" name="site_id" value="">
            <?php endif; ?>
            <div class="form-group">
                <label for="site_text">Site</label>
                <input type="text" id="site_text" name="site_text"
                       value="<?php echo e($val('site_text')); ?>"
                       maxlength="200"
                       autocomplete="off"
                       list="site_text_list"
                       placeholder="Nom du site (optionnel)">
                <datalist id="site_text_list">
                    <?php foreach ($sites as $site): ?>
                        <option value="<?php echo e($site['nom'] ?? ''); ?>">
                    <?php endforeach; ?>
                </datalist>
                <span class="form-hint">Nom du site en texte libre (optionnel).</span>
            </div>
            <?php if (reportVisibilityIsAgentChoice($type)): ?>
            <div class="form-group form-grid__full confidential-toggle" id="confidential-toggle">
                <label class="label--checkbox">
                    <input type="checkbox" name="is_confidential" id="is_confidential" value="1"
                           class="confidential-toggle__input"
                           <?php echo $val('is_confidential', '0') === '1' ? 'checked' : ''; ?>>
                    Signalement confidentiel
                </label>
                <div class="confidential-toggle__details">
                    <?php if ((new \App\Services\AccessService())->getChsctReportScope() === 'all'): ?>
                    <span class="form-hint form-hint--lg">Si coché, ce signalement ne sera visible que par vous, les superviseurs et les membres du <?php echo e(getRoleLabelShort('chsct')); ?>. Décochez pour le rendre visible par tous les agents de votre <?php echo e(getConfig('app_label_unite', 'UR')); ?>.</span>
                    <?php else: ?>
                    <span class="form-hint form-hint--lg">Si coché, ce signalement ne sera visible que par vous et les superviseurs. Les membres du <?php echo e(getRoleLabelShort('chsct')); ?> ne le verront que si vous cochez également la case de consentement ci-dessous. Décochez pour le rendre visible par tous les agents de votre <?php echo e(getConfig('app_label_unite', 'UR')); ?>.</span>
                    <?php endif; ?>
                    <!-- Warning visible uniquement quand la case est décochée — CSS :has(), pas de JavaScript -->
                    <div class="confidential-warning">
                        &#9888; <strong>Attention :</strong> ce signalement sera visible par tous les agents de votre <?php echo e(getConfig('app_label_unite', 'UR')); ?>, y compris son objet et sa description.
                    </div>
                </div>
            </div>
            <?php elseif (reportVisibilityIsConfidential($type)): ?>
            <input type="hidden" name="is_confidential" value="1">
            <div class="form-group form-grid__full">
                <span class="badge badge--confidential">&#128274; Confidentiel</span>
                <?php if ((new \App\Services\AccessService())->getChsctReportScope() === 'all'): ?>
                <span class="form-hint">Le mode de visibilité est « Confidentiel » : votre signalement n'est visible que par vous, les superviseurs et les membres du <?php echo e(getRoleLabelShort('chsct')); ?>.</span>
                <?php else: ?>
                <span class="form-hint">Le mode de visibilité est « Confidentiel » : votre signalement n'est visible que par vous et les superviseurs. Les membres du <?php echo e(getRoleLabelShort('chsct')); ?> ne le verront que si vous cochez la case de consentement lors de la création.</span>
                <?php endif; ?>
            </div>
            <?php elseif (reportVisibilityIsPublic($type)): ?>
            <input type="hidden" name="is_confidential" value="0">
            <?php endif; ?>
            <!-- Consent: transmission to union representatives -->
            <div class="form-group form-grid__full">
                <label class="label--checkbox">
                    <input type="checkbox" name="consent_syndicat" id="consent_syndicat" value="1"
                           <?php echo ((bool) $val('consent_syndicat') || ($isEdit && !empty($report['consent_syndicat'] ?? ''))) ? 'checked' : ''; ?>>
                    J'accepte que mon signalement soit transmis aux organisations syndicales représentatives au sein de la DREETS
                </label>
            </div>
            <div class="form-group">
                <label for="declarant_nom">Déclarant — Nom</label>
                <input type="text" id="declarant_nom" value="<?php echo e($user['nom'] ?? ''); ?>" readonly tabindex="-1" aria-readonly="true">
            </div>
            <div class="form-group">
                <label for="declarant_prenom">Déclarant — Prénom</label>
                <input type="text" id="declarant_prenom" value="<?php echo e($user['prenom'] ?? ''); ?>" readonly tabindex="-1" aria-readonly="true">
            </div>
            <?php if ($type === 'rami'): require __DIR__ . '/report_form_rami.php'; endif; ?>
        </div>
        <?php require __DIR__ . '/report_form_linked_agents.php'; ?>

        <div class="form-actions">
            <button type="submit" class="btn <?php echo $submitBtnClass; ?>">
                <?php echo $isEdit ? 'Enregistrer' : 'Envoyer le signalement'; ?>
            </button>
            <a href="<?php echo $isEdit && $report !== null ? url('report_view', ['uuid' => $report['uuid'] ?? '']) : url('home'); ?>"
               class="btn btn--secondary" title="Supprimer le formulaire et revenir à la page précédente">Annuler</a>
        </div>
    </form>

    <!-- File upload: update displayed filename when a file is chosen -->
    <script>
    (function() {
        var input = document.getElementById('attachment'), nameEl = document.getElementById('file_chosen_name');
        if (input && nameEl) input.addEventListener('change', function() {
            if (this.files && this.files.length > 0) {
                nameEl.textContent = this.files[0].name; nameEl.style.fontStyle = 'normal'; nameEl.style.color = '';
            } else { nameEl.textContent = 'Aucun fichier sélectionné'; nameEl.style.fontStyle = 'italic'; }
        });
    })();
    </script>
</div>
