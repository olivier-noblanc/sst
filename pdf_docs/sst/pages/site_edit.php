<?php
/**
 * Site Edit Page — Application SST DREETS BFC
 *
 * Edit an existing site's code, name, and department.
 * Access: superviseur only
 */
requireRole(['superviseur']);

$pdo = getDB();
$siteId = (int) ($_GET['id'] ?? 0);

if ($siteId <= 0) {
    setFlash('error', 'Site introuvable.');
    redirect(url('settings', ['tab' => 'manage_sites']));
}

$site = getSiteById($pdo, $siteId);

if (!$site) {
    setFlash('error', 'Site introuvable.');
    redirect(url('settings', ['tab' => 'manage_sites']));
}

// Form data and errors from session
$formErrors = getFormErrors();
$formData = getFormData();

// Use formData if available, otherwise use site data
$editCode = $formData['code'] ?? $site['code'];
$editNom = $formData['nom'] ?? $site['nom'];
$editDepartement = $formData['departement'] ?? ($site['departement'] ?? '');

$pageTitle = 'Éditer le site — ' . e($site['code'] . ' ' . $site['nom']);
?>

<h1 class="page-title">Éditer le site</h1>

<?php require __DIR__ . '/../templates/alert.php'; ?>

<div class="card">
    <form method="POST" action="<?php echo url('site_edit', ['id' => $siteId]); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
        <input type="hidden" name="site_id" value="<?php echo $siteId; ?>">

        <div class="form-grid">
            <div class="form-group">
                <label for="code">Code <span class="required">*</span></label>
                <input type="text" name="code" id="code" required maxlength="10" value="<?php echo e($editCode); ?>" placeholder="UR21">
                <?php if (isset($formErrors['code'])): ?><span class="form-error"><?php echo e($formErrors['code']); ?></span><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="nom">Nom <span class="required">*</span></label>
                <input type="text" name="nom" id="nom" required maxlength="200" value="<?php echo e($editNom); ?>" placeholder="UR Côte-d'Or">
                <?php if (isset($formErrors['nom'])): ?><span class="form-error"><?php echo e($formErrors['nom']); ?></span><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="departement">Département</label>
                <input type="text" name="departement" id="departement" maxlength="100" value="<?php echo e($editDepartement); ?>" placeholder="Côte-d'Or">
                <?php if (isset($formErrors['departement'])): ?><span class="form-error"><?php echo e($formErrors['departement']); ?></span><?php endif; ?>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn--success">Mettre à jour</button>
            <a href="<?php echo url('settings', ['tab' => 'manage_sites']); ?>" class="btn btn--secondary">Annuler</a>
        </div>
    </form>
</div>
