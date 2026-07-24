<?php
/**
 * Settings Tab: Registres — Application SST DREETS BFC
 *
 * Gestion des registres : activer/désactiver, changer couleur/icône, ajouter/supprimer.
 * Access: superviseur only
 */
/** @var string $csrfToken */
use App\Repository\RegistryRepository;
use App\Repository\ReportRepository;

$fmt = new \App\Services\FormattingService();
$http = new \App\Services\HttpService();
$registryRepo = RegistryRepository::instance();
$registres = $registryRepo->findAll();
$themes = RegistryRepository::availableThemes();

// Icons list (20+ choices)
$icons = [
    '📋', '📝', '📊', '📈', '📉', '📑', '📒', '📓',
    '⚠️', '🔴', '🟠', '🟡', '🟢', '🔵', '🟣', '⚪',
    '🚨', '🚑', '🏥', '⚖️', '🔍', '🛡️', '📞', '💬',
    '🔒', '🔓', '✅', '❌', '⭐', '🔔',
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
        $isEnabled = (int) $reg['is_enabled'] === 1;
        $isSystem = (int) $reg['is_system'] === 1;
        $colorTheme = $reg['color_theme'];
        $icon = $reg['icon'];
        $reportCount = ReportRepository::instance()->countActive($regCode);
        ?>
    <div class="card mb-4" style="border-top: 4px solid var(--theme-<?php echo $fmt->e($colorTheme); ?>, #666);">
        <div class="card__title-row">
            <div>
                <h3 class="card__subtitle">
                    <?php echo $fmt->e($icon); ?> <?php echo $fmt->e($regLabel); ?>
                    <span class="badge badge--sm" style="background: var(--theme-<?php echo $fmt->e($colorTheme); ?>, #666); color: white;"><?php echo $fmt->e($regShortLabel); ?></span>
                </h3>
                <p class="text-muted text-small">
                    Code : <code><?php echo $fmt->e($regCode); ?></code>
                    &mdash; <?php echo $reportCount; ?> signalement<?php echo $reportCount !== 1 ? 's' : ''; ?>
                    <?php if ($isSystem): ?>
                        &mdash; <span class="badge badge--sm">Système</span>
                    <?php endif; ?>
                </p>
            </div>
            <div>
                <!-- Toggle enable/disable -->
                <label class="toggle-switch-label">
                    <input type="checkbox" name="registres[<?php echo $regId; ?>][is_enabled]" value="1"
                           class="toggle-switch__input"
                           <?php echo $isEnabled ? 'checked' : ''; ?>
                           <?php echo $isSystem ? 'disabled' : ''; ?>>
                    <span class="toggle-switch" aria-hidden="true"></span>
                    <span>Actif</span>
                </label>
            </div>
        </div>

        <?php if (!$isSystem): ?>
        <!-- Color theme selector -->
        <div class="form-group">
            <label>Couleur du thème</label>
            <div class="flex-row gap-2" style="flex-wrap: wrap;">
                <?php foreach ($themes as $theme): ?>
                <label class="toggle-switch-label" style="gap: 4px;">
                    <input type="radio" name="registres[<?php echo $regId; ?>][color_theme]"
                           value="<?php echo $fmt->e($theme); ?>"
                           <?php echo $colorTheme === $theme ? 'checked' : ''; ?>
                           style="display: none;">
                    <span style="display: inline-block; width: 28px; height: 28px; border-radius: 50%; background: var(--theme-<?php echo $fmt->e($theme); ?>, #666); border: 3px solid <?php echo $colorTheme === $theme ? '#333' : 'transparent'; ?>; cursor: pointer;" title="<?php echo $fmt->e($theme); ?>"></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Icon selector -->
        <div class="form-group">
            <label>Icône</label>
            <div class="flex-row gap-2" style="flex-wrap: wrap;">
                <?php foreach ($icons as $ic): ?>
                <label class="toggle-switch-label" style="gap: 4px;">
                    <input type="radio" name="registres[<?php echo $regId; ?>][icon]"
                           value="<?php echo $fmt->e($ic); ?>"
                           <?php echo $icon === $ic ? 'checked' : ''; ?>
                           style="display: none;">
                    <span style="display: inline-block; width: 32px; height: 32px; text-align: center; line-height: 32px; border-radius: 6px; border: 2px solid <?php echo $icon === $ic ? '#333' : '#ddd'; ?>; cursor: pointer; font-size: 18px;"><?php echo $ic; ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <div class="form-actions">
        <button type="submit" class="btn btn--success">Enregistrer les modifications</button>
        <a href="<?php echo $http->url('settings', ['tab' => 'app']); ?>" class="btn btn--outline">Annuler</a>
    </div>
</form>
