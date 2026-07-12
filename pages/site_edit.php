<?php
/**
 * Site Edit Page — Application SST DREETS BFC
 *
 * Edit an existing site's code, name, and department.
 * Access: superviseur only
 */
requireRole([ROLE_SUPERVISEUR]);

$siteId = (int) ($_GET['id'] ?? 0);

if ($siteId <= 0) {
    new \App\Services\SessionService()->setFlash('error', 'Site introuvable.');
    new \App\Services\HttpService()->redirect(new \App\Services\HttpService()->url('settings', ['tab' => 'manage_sites']));
}

$site = \App\Repository\SiteRepository::instance()->findById($siteId);

if (!$site) {
    new \App\Services\SessionService()->setFlash('error', 'Site introuvable.');
    new \App\Services\HttpService()->redirect(new \App\Services\HttpService()->url('settings', ['tab' => 'manage_sites']));
}

// Form data and errors from session
$formErrors = new \App\Services\SessionService()->getFormErrors();
$formData = new \App\Services\SessionService()->getFormData();

// Use formData if available, otherwise use site data
$editCode = $formData['code'] ?? $site['code'];
$editNom = $formData['nom'] ?? $site['nom'];
$editDepartement = $formData['departement'] ?? ($site['departement'] ?? '');

$pageTitle = 'Éditer le site — ' . new \App\Services\FormattingService()->e($site['code'] . ' ' . $site['nom']);
?>

<h1 class="page-title">Éditer le site</h1>


<div class="card">
    <form method="POST" action="<?php echo new \App\Services\HttpService()->url('site_edit', ['id' => $siteId]); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo new \App\Services\FormattingService()->e($csrfToken); ?>">
        <input type="hidden" name="site_id" value="<?php echo new \App\Services\FormattingService()->e((string) $siteId); ?>">

        <div class="form-grid">
            <div class="form-group">
                <label for="code">Code <span class="required">*</span></label>
                <input type="text" name="code" id="code" required maxlength="10" value="<?php echo new \App\Services\FormattingService()->e($editCode); ?>" placeholder="UR21">
                <?php if (isset($formErrors['code'])): ?><span class="form-error" id="err_code"><?php echo new \App\Services\FormattingService()->e($formErrors['code']); ?></span><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="nom">Nom <span class="required">*</span></label>
                <input type="text" name="nom" id="nom" required maxlength="200" value="<?php echo new \App\Services\FormattingService()->e($editNom); ?>" placeholder="UR Côte-d'Or">
                <?php if (isset($formErrors['nom'])): ?><span class="form-error" id="err_nom"><?php echo new \App\Services\FormattingService()->e($formErrors['nom']); ?></span><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="departement">Département</label>
                <input type="text" name="departement" id="departement" maxlength="100" value="<?php echo new \App\Services\FormattingService()->e($editDepartement); ?>" placeholder="Côte-d'Or">
                <?php if (isset($formErrors['departement'])): ?><span class="form-error" id="err_departement"><?php echo new \App\Services\FormattingService()->e($formErrors['departement']); ?></span><?php endif; ?>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn--success">Mettre à jour</button>
            <a href="<?php echo new \App\Services\HttpService()->url('settings', ['tab' => 'manage_sites']); ?>" class="btn btn--secondary">Annuler</a>
        </div>
    </form>
</div>
