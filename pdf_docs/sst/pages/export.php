<?php
/**
 * Export Page — Application SST DREETS BFC
 * 
 * CSV data export with filters.
 * Access: superviseur, manager, chsct
 */
requireRole(['superviseur', 'manager', 'chsct']);

$pdo = getDB();

// Get sites and users for filter dropdowns
$sites = getAllSites($pdo);
$users = getAllUsers($pdo);

// Get filter values from session (for sticky form after errors)
$formErrors = getFormErrors();
$formData = getFormData();

$pageTitle = 'Export des données';
?>

<h1 class="page-title">Export des données</h1>

<?php require __DIR__ . '/../templates/alert.php'; ?>

<div class="card">
    <p class="mb-4" style="color:var(--grey-600);">
        Sélectionnez les critères de filtrage pour exporter les données en format CSV (séparateur point-virgule, compatible Excel).
    </p>

    <form method="POST" action="<?php echo url('export'); ?>" id="exportForm">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">

        <div class="form-grid">
            <!-- Registre -->
            <div class="form-group">
                <label for="type">Registre</label>
                <div style="display:flex;align-items:center;gap:8px;">
                    <select name="type" id="type" <?php echo !empty($formData['all_registries']) ? 'disabled' : ''; ?>>
                        <option value="" <?php echo empty($formData['type']) ? 'selected' : ''; ?>>— Choisir —</option>
                        <option value="rsst" <?php echo ($formData['type'] ?? '') === 'rsst' ? 'selected' : ''; ?>>RSST</option>
                        <option value="rami" <?php echo ($formData['type'] ?? '') === 'rami' ? 'selected' : ''; ?>>RAMI</option>
                        <option value="dgi"  <?php echo ($formData['type'] ?? '') === 'dgi' ? 'selected' : ''; ?>>DGI</option>
                    </select>
                    <label style="font-weight:normal;white-space:nowrap;display:flex;align-items:center;gap:4px;font-size:13px;">
                        <input type="checkbox" name="all_registries" id="all_registries" value="1"
                               <?php echo !empty($formData['all_registries']) ? 'checked' : ''; ?>
                               onchange="document.getElementById('type').disabled=this.checked;if(this.checked)document.getElementById('type').selectedIndex=0;">
                        Tous les registres
                    </label>
                </div>
            </div>

            <!-- Site -->
            <div class="form-group">
                <label for="site_id">Site</label>
                <div style="display:flex;align-items:center;gap:8px;">
                    <select name="site_id" id="site_id" <?php echo !empty($formData['all_sites']) ? 'disabled' : ''; ?>>
                        <option value="" <?php echo empty($formData['site_id']) ? 'selected' : ''; ?>>— Choisir —</option>
                        <?php foreach ($sites as $site): ?>
                        <option value="<?php echo (int) $site['id']; ?>" <?php echo ($formData['site_id'] ?? '') == $site['id'] ? 'selected' : ''; ?>><?php echo e($site['nom']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label style="font-weight:normal;white-space:nowrap;display:flex;align-items:center;gap:4px;font-size:13px;">
                        <input type="checkbox" name="all_sites" id="all_sites" value="1"
                               <?php echo !empty($formData['all_sites']) ? 'checked' : ''; ?>
                               onchange="document.getElementById('site_id').disabled=this.checked;if(this.checked)document.getElementById('site_id').selectedIndex=0;">
                        Tous les sites
                    </label>
                </div>
            </div>

            <!-- Agent -->
            <div class="form-group">
                <label for="declarant_id">Agent</label>
                <div style="display:flex;align-items:center;gap:8px;">
                    <select name="declarant_id" id="declarant_id" <?php echo !empty($formData['all_agents']) ? 'disabled' : ''; ?>>
                        <option value="" <?php echo empty($formData['declarant_id']) ? 'selected' : ''; ?>>— Choisir —</option>
                        <?php foreach ($users as $u): ?>
                        <option value="<?php echo (int) $u['id']; ?>" <?php echo ($formData['declarant_id'] ?? '') == $u['id'] ? 'selected' : ''; ?>><?php echo e($u['prenom'] . ' ' . $u['nom']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label style="font-weight:normal;white-space:nowrap;display:flex;align-items:center;gap:4px;font-size:13px;">
                        <input type="checkbox" name="all_agents" id="all_agents" value="1"
                               <?php echo !empty($formData['all_agents']) ? 'checked' : ''; ?>
                               onchange="document.getElementById('declarant_id').disabled=this.checked;if(this.checked)document.getElementById('declarant_id').selectedIndex=0;">
                        Tous les agents
                    </label>
                </div>
            </div>

            <!-- Date range -->
            <div class="form-group">
                <label>Période</label>
                <div style="display:flex;gap:8px;align-items:center;">
                    <input type="date" name="date_from" id="date_from" value="<?php echo e($formData['date_from'] ?? ''); ?>" placeholder="Début" style="flex:1;">
                    <span style="color:var(--grey-500);">à</span>
                    <input type="date" name="date_to" id="date_to" value="<?php echo e($formData['date_to'] ?? ''); ?>" placeholder="Fin" style="flex:1;">
                </div>
                <div class="form-hint">Laissez vide pour aucune restriction de date</div>
            </div>
        </div>

        <!-- État filter -->
        <div class="form-group">
            <label>États à inclure</label>
            <div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:4px;">
                <?php foreach (ETAT_LABELS as $key => $label): ?>
                <label style="font-weight:normal;display:flex;align-items:center;gap:4px;font-size:13px;">
                    <input type="checkbox" name="etats[]" value="<?php echo e($key); ?>"
                           <?php echo (empty($formData['etats']) || in_array($key, $formData['etats'] ?? [])) ? 'checked' : ''; ?>>
                    <span class="badge <?php echo getEtatBadgeClass($key); ?>" style="font-size:11px;"><?php echo e($label); ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn--primary">📥 Exporter en CSV</button>
            <a href="<?php echo url('export'); ?>" class="btn btn--outline">Réinitialiser</a>
        </div>
    </form>
</div>
