<?php
/**
 * Export Page — Application SST DREETS BFC
 *
 * CSV data export with filters.
 * Access: superviseur, chsct
 */
requireRole([ROLE_SUPERVISEUR, ROLE_CHSCT]);

$pdo = getDB();
$noSiteMode = isNoSiteMode($pdo);

// Get sites and users for filter dropdowns
$sites = getAllSites($pdo);
$users = getAllUsers($pdo);

// Get filter values from session (for sticky form after errors)
$formErrors = getFormErrors();
$formData = getFormData();

$pageTitle = 'Export des données';

$ramiEnabled = isRegistryEnabled(TYPE_RAMI);
$dgiEnabled = isRegistryEnabled(TYPE_DGI);
?>

<h1 class="page-title">Export des données</h1>


<div class="card">
    <p class="text-muted mb-4">
        Sélectionnez les critères de filtrage pour exporter les données en format CSV (séparateur point-virgule, compatible Excel).
    </p>

    <form method="POST" action="<?php echo url('export'); ?>" id="exportForm">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">

        <div class="form-grid">
            <!-- Registre -->
            <div class="form-group">
                <label for="type">Registre</label>
                <div class="btn-group--inline items-center">
                    <select name="type" id="type">
                        <option value="" <?php echo empty($formData['type']) ? 'selected' : ''; ?>>— Choisir —</option>
                        <option value="rsst" <?php echo ($formData['type'] ?? '') === 'rsst' ? 'selected' : ''; ?>>RSST</option>
                        <?php if ($ramiEnabled): ?><option value="rami" <?php echo ($formData['type'] ?? '') === 'rami' ? 'selected' : ''; ?>>RAMI</option><?php endif; ?>
                        <?php if ($dgiEnabled): ?><option value="dgi"  <?php echo ($formData['type'] ?? '') === 'dgi' ? 'selected' : ''; ?>>DGI</option><?php endif; ?>
                    </select>
                    <label class="label--checkbox">
                        <input type="checkbox" name="all_registries" id="all_registries" value="1"
                               <?php echo !empty($formData['all_registries']) ? 'checked' : ''; ?>>
                        Tous les registres
                    </label>
                </div>
            </div>

            <!-- Site -->
            <?php if (!$noSiteMode): ?>
            <div class="form-group">
                <label for="site_id"><?php echo e(getConfig('app_label_unite', 'UR')); ?></label>
                <div class="btn-group--inline items-center">
                    <select name="site_id" id="site_id">
                        <option value="" <?php echo empty($formData['site_id']) ? 'selected' : ''; ?>>— Choisir —</option>
                        <?php foreach ($sites as $site): ?>
                        <option value="<?php echo (int) $site['id']; ?>" <?php echo ($formData['site_id'] ?? '') == $site['id'] ? 'selected' : ''; ?>><?php echo e($site['nom']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label class="label--checkbox">
                        <input type="checkbox" name="all_sites" id="all_sites" value="1"
                               <?php echo !empty($formData['all_sites']) ? 'checked' : ''; ?>>
                        Tous les sites
                    </label>
                </div>
            </div>
            <?php endif; ?>

            <!-- Agent -->
            <div class="form-group">
                <label for="declarant_id">Agent</label>
                <div class="btn-group--inline items-center">
                    <select name="declarant_id" id="declarant_id">
                        <option value="" <?php echo empty($formData['declarant_id']) ? 'selected' : ''; ?>>— Choisir —</option>
                        <?php foreach ($users as $u): ?>
                        <option value="<?php echo (int) $u['id']; ?>" <?php echo ($formData['declarant_id'] ?? '') == $u['id'] ? 'selected' : ''; ?>><?php echo e($u['prenom'] . ' ' . $u['nom']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label class="label--checkbox">
                        <input type="checkbox" name="all_agents" id="all_agents" value="1"
                               <?php echo !empty($formData['all_agents']) ? 'checked' : ''; ?>>
                        Tous les agents
                    </label>
                </div>
            </div>

            <!-- Date range -->
            <div class="form-group">
                <label>Période</label>
                <div class="date-range">
                    <input type="date" name="date_from" id="date_from" value="<?php echo e($formData['date_from'] ?? ''); ?>" max="<?php echo todayISO(); ?>" placeholder="Début" aria-describedby="hint_date_range" class="flex-1">
                    <span>&agrave;</span>
                    <input type="date" name="date_to" id="date_to" value="<?php echo e($formData['date_to'] ?? ''); ?>" max="<?php echo todayISO(); ?>" placeholder="Fin" aria-describedby="hint_date_range" class="flex-1">
                </div>
                <div class="form-hint" id="hint_date_range">Laissez vide pour aucune restriction de date</div>
            </div>
        </div>

        <!-- État filter -->
        <div class="form-group">
            <label>États à inclure</label>
            <div class="checkbox-group">
                <?php foreach (ETAT_LABELS as $key => $label): ?>
                <label class="label--checkbox">
                    <input type="checkbox" name="etats[]" value="<?php echo e($key); ?>"
                           <?php echo (empty($formData['etats']) || in_array($key, $formData['etats'] ?? [])) ? 'checked' : ''; ?>>
                    <span class="badge <?php echo getEtatBadgeClass($key); ?> badge--sm"><?php echo e($label); ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn--primary">&#x1F4E5; Exporter en CSV</button>
            <a href="<?php echo url('export'); ?>" class="btn btn--outline">Réinitialiser</a>
        </div>
    </form>
</div>
