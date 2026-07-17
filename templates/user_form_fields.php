<?php
/**
 * User Form Fields Template — Application SST DREETS BFC
 *
 * Shared form fields for user creation and editing.
 * Eliminates ~45 lines of duplicated HTML between users.php and user_edit.php.
 *
 * Required variables:
 *   $formErrors   — Array of field validation errors
 *   $formData     — Array of submitted form data for repopulation
 *   $sites        — Array of sites for the dropdown
 *   $editNom      — Pre-filled nom value
 *   $editPrenom   — Pre-filled prenom value
 *   $editEmail    — Pre-filled email value
 *   $editUsername  — Pre-filled username value
 *   $editRole     — Pre-selected role value
 *   $editSiteId   — Pre-selected site_id value
 *   $usernameHint — Hint text for username field (optional, default: 'Identifiant de connexion Windows')
 */

/** @var array<string, string> $formErrors */
/** @var string $editNom */
/** @var string $editPrenom */
/** @var string $editEmail */
/** @var string $editUsername */
/** @var string $editRole */
/** @var int|string $editSiteId */
/** @var list<array{id: int|string, nom: string}> $sites */
/** @var string $usernameHint */

if (!isset($usernameHint)) {
    $usernameHint = 'Identifiant de connexion Windows (ex: jean.martin)';
}
?>

<div class="form-grid">
    <div class="form-group">
        <label for="nom">Nom <span class="required">*</span></label>
        <input type="text" name="nom" id="nom" required minlength="2" maxlength="100" value="<?php echo e($editNom); ?>"
               <?php echo isset($formErrors['nom']) ? 'aria-describedby="err_nom" aria-invalid="true"' : ''; ?>>
        <?php if (isset($formErrors['nom'])): ?><span class="form-error" id="err_nom"><?php echo e($formErrors['nom']); ?></span><?php endif; ?>
    </div>
    <div class="form-group">
        <label for="prenom">Prénom <span class="required">*</span></label>
        <input type="text" name="prenom" id="prenom" required minlength="2" maxlength="100" value="<?php echo e($editPrenom); ?>"
               <?php echo isset($formErrors['prenom']) ? 'aria-describedby="err_prenom" aria-invalid="true"' : ''; ?>>
        <?php if (isset($formErrors['prenom'])): ?><span class="form-error" id="err_prenom"><?php echo e($formErrors['prenom']); ?></span><?php endif; ?>
    </div>
    <div class="form-group">
        <label for="email">Email</label>
        <input type="email" name="email" id="email" maxlength="200" value="<?php echo e($editEmail); ?>"
               <?php echo isset($formErrors['email']) ? 'aria-describedby="err_email" aria-invalid="true"' : ''; ?>>
        <?php if (isset($formErrors['email'])): ?><span class="form-error" id="err_email"><?php echo e($formErrors['email']); ?></span><?php endif; ?>
    </div>
    <div class="form-group">
        <label for="username">Identifiant <span class="required">*</span></label>
        <input type="text" name="username" id="username" required minlength="2" maxlength="100" value="<?php echo e($editUsername); ?>"
               pattern="[a-zA-Z0-9.\-]+" title="Lettres, chiffres, points et tirets uniquement"
               autocomplete="username"
               aria-describedby="hint_username"
               <?php echo isset($formErrors['username']) ? 'aria-describedby="err_username" aria-invalid="true"' : ''; ?>>
        <div class="form-hint" id="hint_username"><?php echo e($usernameHint); ?></div>
        <?php if (isset($formErrors['username'])): ?><span class="form-error" id="err_username"><?php echo e($formErrors['username']); ?></span><?php endif; ?>
    </div>
    <div class="form-group">
        <label for="role">Rôle <span class="required">*</span></label>
        <select name="role" id="role" required
                <?php echo isset($formErrors['role']) ? 'aria-describedby="err_role" aria-invalid="true"' : ''; ?>>
            <?php foreach (ROLE_LABELS as $key => $label): ?>
            <option value="<?php echo e($key); ?>" <?php echo $editRole === $key ? 'selected' : ''; ?>><?php echo e($label); ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($formErrors['role'])): ?><span class="form-error" id="err_role"><?php echo e($formErrors['role']); ?></span><?php endif; ?>
    </div>
    <?php
    $noSiteMode = isNoSiteMode(getDB());
    if (!$noSiteMode):
    ?>
    <div class="form-group">
        <label for="site_id"><?php echo e(getConfig('app_label_unite', 'UR')); ?> <span class="required">*</span></label>
        <select name="site_id" id="site_id"
                <?php echo isset($formErrors['site_id']) ? 'aria-describedby="err_site_id" aria-invalid="true"' : ''; ?>>
            <option value="0">— Aucun —</option>
            <?php foreach ($sites as $site): ?>
            <option value="<?php echo (int) $site['id']; ?>" <?php echo (int) $editSiteId === (int) $site['id'] ? 'selected' : ''; ?>><?php echo e($site['nom']); ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($formErrors['site_id'])): ?><span class="form-error" id="err_site_id"><?php echo e($formErrors['site_id']); ?></span><?php endif; ?>
    </div>
    <?php else: ?>
    <input type="hidden" name="site_id" value="">
    <?php endif; ?>
</div>
