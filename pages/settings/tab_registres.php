<?php
/**
 * Settings Tab: Registres — Application SST DREETS BFC
 *
 * Gestion complète des registres : CRUD, visibilité, CHSCT, notes légales, réordonnancement.
 * Access: superviseur only
 */
/** @var string $csrfToken */
use App\Repository\RegistryRepository;
use App\Repository\ReportRepository;
use App\Enum\VisibilityMode;

$fmt = new \App\Services\FormattingService();
$http = new \App\Services\HttpService();
$registryRepo = RegistryRepository::instance();
$registres = $registryRepo->findAll();
$themes = RegistryRepository::availableThemes();

$icons = [
    '📋', '📝', '📊', '📈', '📉', '📑', '📒', '📓',
    '⚠️', '🔴', '🟠', '🟡', '🟢', '🔵', '🟣', '⚪',
    '🚨', '🚑', '🏥', '⚖️', '🔍', '🛡️', '📞', '💬',
    '🔒', '🔓', '✅', '❌', '⭐', '🔔',
];

$visibilityModes = [
    VisibilityMode::Confidential->value => 'Confidentiel',
    VisibilityMode::AgentChoice->value  => 'Choix de l\'agent',
    VisibilityMode::Public->value       => 'Public',
];
?>

<h2 class="card__subtitle mb-4">Gestion des registres</h2>
<p class="text-muted text-small mb-4">Activez, désactivez ou personnalisez les registres. Le registre RSST ne peut pas être supprimé (système).</p>

<form method="POST" action="<?php echo $http->url('settings'); ?>">
    <input type="hidden" name="csrf_token" value="<?php echo $fmt->e($csrfToken); ?>">
    <input type="hidden" name="tab" value="registres">

    <?php foreach ($registres as $reg): ?>
    <?php
        $regId = (int) $reg['id'];
        $regCode = $reg['code'];
        $regLabel = $reg['label'];
        $regShortLabel = $reg['short_label'];
        $regDescription = $reg['description'] ?? '';
        $isEnabled = (int) $reg['is_enabled'] === 1;
        $isSystem = (int) $reg['is_system'] === 1;
        $colorTheme = $reg['color_theme'];
        $icon = $reg['icon'];
        $sortOrder = (int) $reg['sort_order'];
        $defaultVisibility = $reg['default_visibility'] ?? VisibilityMode::AgentChoice->value;
        $notifyChsct = (int) ($reg['notify_chsct'] ?? 0) === 1;
        $legalNote = $reg['legal_note'] ?? '';
        $reportCount = ReportRepository::instance()->countActive($regCode);
        ?>
    <div class="card mb-4 card--<?php echo $fmt->e($colorTheme); ?>">
        <div class="card__title-row">
            <div>
                <h3 class="card__subtitle">
                    <?php echo $fmt->e($icon); ?> <?php echo $fmt->e($regLabel); ?>
                    <span class="badge badge--sm badge--<?php echo $fmt->e($colorTheme); ?>"><?php echo $fmt->e($regShortLabel); ?></span>
                </h3>
                <p class="text-muted text-small">
                    Code : <code><?php echo $fmt->e($regCode); ?></code>
                    &mdash; <?php echo $reportCount; ?> signalement<?php echo $reportCount !== 1 ? 's' : ''; ?>
                    <?php if ($isSystem): ?>
                        &mdash; <span class="badge badge--sm">Système</span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="flex-row gap-2">
                <!-- Toggle enable/disable -->
                <label class="toggle-switch-label">
                    <input type="checkbox" name="registres[<?php echo $regId; ?>][is_enabled]" value="1"
                           class="toggle-switch__input"
                           <?php echo $isEnabled ? 'checked' : ''; ?>
                           <?php echo $isSystem ? 'disabled' : ''; ?>>
                    <span class="toggle-switch" aria-hidden="true"></span>
                    <span>Actif</span>
                </label>
                <?php if (!$isSystem): ?>
                <?php
                // Audit #86 — escape the label for JS context. Apostrophe in
                // $regLabel broke the JS confirm() string before (e.g. label
                // "L'UR21" closed the JS string early → syntax error → button silent).
                // Use addslashes to escape ' and " for JS, on top of HTML escaping
                // for the surrounding attribute.
                $confirmLabelJs = addslashes((string) $regLabel);
                ?>
                <button type="submit" name="action" value="delete_<?php echo $regId; ?>"
                        class="btn btn--danger btn--small"
                        onclick="return confirm('Supprimer le registre « <?php echo $fmt->e($confirmLabelJs); ?> » ? Les signalements existants ne seront pas supprimés.')">
                    Supprimer
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Label + Short label -->
        <div class="form-grid">
            <div class="form-group">
                <label for="registres_<?php echo $regId; ?>_label">Libellé complet</label>
                <input type="text" id="registres_<?php echo $regId; ?>_label"
                       name="registres[<?php echo $regId; ?>][label]"
                       value="<?php echo $fmt->e($regLabel); ?>" class="input" required maxlength="100">
            </div>
            <div class="form-group">
                <label for="registres_<?php echo $regId; ?>_short_label">Sigle</label>
                <input type="text" id="registres_<?php echo $regId; ?>_short_label"
                       name="registres[<?php echo $regId; ?>][short_label]"
                       value="<?php echo $fmt->e($regShortLabel); ?>" class="input" required maxlength="10">
            </div>
        </div>

        <!-- Description -->
        <div class="form-group">
            <label for="registres_<?php echo $regId; ?>_description">Description</label>
            <textarea id="registres_<?php echo $regId; ?>_description"
                      name="registres[<?php echo $regId; ?>][description]"
                      class="input" rows="2" maxlength="500"><?php echo $fmt->e($regDescription); ?></textarea>
        </div>

        <!-- Color theme + Icon + Sort order -->
        <div class="form-grid form-grid--3">
            <div class="form-group">
                <label>Couleur</label>
                <?php
                $themeColors = [
                    \App\Enum\ReportType::Rsst->value => '#2E5C8A', \App\Enum\ReportType::Rami->value => '#6C6C6C', \App\Enum\ReportType::Dgi->value => '#b91c1c',
                    'vert' => '#15803D', 'violet' => '#7C3AED', 'orange' => '#C2410C',
                    'teal' => '#0D9488', 'indigo' => '#4338CA', 'rose' => '#BE123C', 'ambre' => '#B45309',
                ];
        ?>
                <input type="hidden" name="registres[<?php echo $regId; ?>][color_theme]" value="<?php echo $fmt->e($colorTheme); ?>">
                <div class="color-picker" data-regid="<?php echo $regId; ?>">
                    <?php foreach ($themes as $theme): ?>
                    <span class="color-dot color-dot--<?php echo $fmt->e($theme); ?><?php echo $colorTheme === $theme ? ' color-dot--active' : ''; ?>"
                          data-theme="<?php echo $fmt->e($theme); ?>"
                          title="<?php echo $fmt->e($theme); ?>"></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-group">
                <label for="registres_<?php echo $regId; ?>_icon">Icône</label>
                <select id="registres_<?php echo $regId; ?>_icon"
                        name="registres[<?php echo $regId; ?>][icon]" class="input">
                    <?php foreach ($icons as $ic): ?>
                    <option value="<?php echo $fmt->e($ic); ?>" <?php echo $icon === $ic ? 'selected' : ''; ?>>
                        <?php echo $ic; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="registres_<?php echo $regId; ?>_sort_order">Ordre</label>
                <input type="number" id="registres_<?php echo $regId; ?>_sort_order"
                       name="registres[<?php echo $regId; ?>][sort_order]"
                       value="<?php echo $sortOrder; ?>" class="input input--small" min="0" max="99">
            </div>
        </div>

        <!-- Visibility + CHSCT notification -->
        <div class="form-grid form-grid--2">
            <div class="form-group">
                <label for="registres_<?php echo $regId; ?>_default_visibility">Visibilité par défaut</label>
                <select id="registres_<?php echo $regId; ?>_default_visibility"
                        name="registres[<?php echo $regId; ?>][default_visibility]" class="input">
                    <?php foreach ($visibilityModes as $value => $label): ?>
                    <option value="<?php echo $fmt->e($value); ?>" <?php echo $defaultVisibility === $value ? 'selected' : ''; ?>>
                        <?php echo $fmt->e($label); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>&nbsp;</label>
                <label class="toggle-switch-label">
                    <input type="checkbox" name="registres[<?php echo $regId; ?>][notify_chsct]" value="1"
                           class="toggle-switch__input" <?php echo $notifyChsct ? 'checked' : ''; ?>>
                    <span class="toggle-switch" aria-hidden="true"></span>
                    <span>Notification CSA/CHSCT (comme DGI)</span>
                </label>
            </div>
        </div>

        <!-- Legal note -->
        <div class="form-group">
            <label for="registres_<?php echo $regId; ?>_legal_note">Note légale (affichée dans le formulaire)</label>
            <textarea id="registres_<?php echo $regId; ?>_legal_note"
                      name="registres[<?php echo $regId; ?>][legal_note]"
                      class="input" rows="2" maxlength="500"><?php echo $fmt->e($legalNote); ?></textarea>
        </div>

        <!-- Hidden fields for unchanged values -->
        <input type="hidden" name="registres[<?php echo $regId; ?>][code]" value="<?php echo $fmt->e($regCode); ?>">
    </div>
    <?php endforeach; ?>

    <!-- Add new registry -->
    <div class="card mb-4 card--dashed">
        <h3 class="card__subtitle mb-2">Ajouter un registre</h3>
        <div class="form-grid form-grid--3">
            <div class="form-group">
                <label for="new_code">Code</label>
                <input type="text" id="new_code" name="new_code" class="input" maxlength="20"
                       placeholder="ex: harcèlement" pattern="[a-z_]+">
            </div>
            <div class="form-group">
                <label for="new_label">Libellé complet</label>
                <input type="text" id="new_label" name="new_label" class="input" maxlength="100"
                       placeholder="Registre de...">
            </div>
            <div class="form-group">
                <label for="new_short_label">Sigle</label>
                <input type="text" id="new_short_label" name="new_short_label" class="input" maxlength="10"
                       placeholder="HARC">
            </div>
        </div>
        <button type="submit" name="action" value="add" class="btn btn--primary mt-2">Ajouter ce registre</button>
    </div>

    <div class="form-actions">
        <button type="submit" name="action" value="save" class="btn btn--success">Enregistrer les modifications</button>
        <a href="<?php echo $http->url('settings', ['tab' => 'app']); ?>" class="btn btn--outline">Annuler</a>
    </div>
</form>

<!-- Registry Fields Management -->
<?php
$fieldRepo = \App\Repository\RegistryFieldRepository::instance();
$enabledRegistries = $registryRepo->findEnabled();
?>
<h2 class="card__subtitle mb-4 mt-6">Champs personnalisés par registre</h2>
<p class="text-muted text-small mb-4">Gérez les champs spécifiques à chaque registre (ex: nature_auteur pour RAMI). Les champs sont affichés dans le formulaire de signalement.</p>

<?php foreach ($enabledRegistries as $reg):
    $regId = (int) $reg['id'];
    $fields = $fieldRepo->findByRegistry($regId);
?>
<div class="card mb-4">
    <h3 class="card__subtitle mb-2"><?php echo $fmt->e((string) $reg['label']); ?> (<?php echo $fmt->e((string) $reg['short_label']); ?>)</h3>

    <?php if (!empty($fields)): ?>
    <table class="table-wrapper" aria-label="Champs du registre <?php echo $fmt->e((string) $reg['short_label']); ?>">
        <thead>
            <tr>
                <th>Code</th>
                <th>Libellé</th>
                <th>Type</th>
                <th>Obligatoire</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($fields as $field): ?>
            <tr>
                <td><code><?php echo $fmt->e((string) $field['field_code']); ?></code></td>
                <td><?php echo $fmt->e((string) $field['label']); ?></td>
                <td><?php echo $fmt->e((string) $field['field_type']); ?></td>
                <td><?php echo (int) $field['is_required'] ? 'Oui' : 'Non'; ?></td>
                <td>
                    <form method="POST" action="<?php echo $http->url('settings'); ?>" class="inline-form">
                        <input type="hidden" name="csrf_token" value="<?php echo $fmt->e($csrfToken); ?>">
                        <input type="hidden" name="tab" value="registres">
                        <input type="hidden" name="field_id" value="<?php echo (int) $field['id']; ?>">
                        <button type="submit" name="action" value="delete_field" class="btn btn--danger btn--sm"
                                onclick="return confirm('Supprimer ce champ ?')">Supprimer</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p class="text-muted">Aucun champ personnalisé pour ce registre.</p>
    <?php endif; ?>

    <!-- Add new field -->
    <div class="card card--dashed mt-4">
        <h4 class="card__subtitle mb-2">Ajouter un champ</h4>
        <form method="POST" action="<?php echo $http->url('settings'); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo $fmt->e($csrfToken); ?>">
            <input type="hidden" name="tab" value="registres">
            <input type="hidden" name="registry_id" value="<?php echo $regId; ?>">
            <div class="form-grid form-grid--4">
                <div class="form-group">
                    <label for="new_field_code_<?php echo $regId; ?>">Code</label>
                    <input type="text" id="new_field_code_<?php echo $regId; ?>" name="new_field_code" class="input" maxlength="50"
                           placeholder="ex: nature_auteur" pattern="[a-z_]+" required>
                </div>
                <div class="form-group">
                    <label for="new_field_label_<?php echo $regId; ?>">Libellé</label>
                    <input type="text" id="new_field_label_<?php echo $regId; ?>" name="new_field_label" class="input" maxlength="100"
                           placeholder="Nature de l'auteur" required>
                </div>
                <div class="form-group">
                    <label for="new_field_type_<?php echo $regId; ?>">Type</label>
                    <select id="new_field_type_<?php echo $regId; ?>" name="new_field_type" class="input">
                        <option value="text">Texte</option>
                        <option value="select">Sélection</option>
                        <option value="textarea">Zone de texte</option>
                        <option value="checkbox">Case à cocher</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="new_field_options_<?php echo $regId; ?>">Options (JSON, pour select)</label>
                    <input type="text" id="new_field_options_<?php echo $regId; ?>" name="new_field_options" class="input"
                           placeholder='{"usager":"Usager","collegue":"Collègue"}'>
                </div>
            </div>
            <div class="form-grid form-grid--2">
                <div class="form-group">
                    <label class="label--checkbox">
                        <input type="checkbox" name="new_field_required" value="1">
                        Obligatoire
                    </label>
                </div>
                <div class="form-group">
                    <label for="new_field_order_<?php echo $regId; ?>">Ordre</label>
                    <input type="number" id="new_field_order_<?php echo $regId; ?>" name="new_field_order" class="input" value="0" min="0">
                </div>
            </div>
            <button type="submit" name="action" value="add_field" class="btn btn--primary mt-2">Ajouter ce champ</button>
        </form>
    </div>
</div>
<?php endforeach; ?>

<script>
document.querySelectorAll('.color-picker').forEach(function(picker) {
    var hidden = picker.previousElementSibling;
    picker.querySelectorAll('.color-dot').forEach(function(dot) {
        dot.addEventListener('click', function() {
            hidden.value = this.dataset.theme;
            picker.querySelectorAll('.color-dot').forEach(function(d) {
                d.classList.remove('color-dot--active');
            });
            this.classList.add('color-dot--active');
        });
    });
});
</script>
