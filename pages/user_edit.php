<?php
/**
 * User Edit Page — Application SST DREETS BFC
 * 
 * Edit user profile/role.
 * Access: superviseur only
 */
requireRole(['superviseur']);

$pdo = getDB();
$userId = (int) ($_GET['id'] ?? 0);

if ($userId <= 0) {
    setFlash('error', 'Utilisateur introuvable.');
    redirect(url('users'));
}

$user = getUserById($pdo, $userId);

if (!$user) {
    setFlash('error', 'Utilisateur introuvable.');
    redirect(url('users'));
}

// Get sites for dropdown
$sites = getAllSites($pdo);

// Form data and errors from session
$formErrors = getFormErrors();
$formData = getFormData();

// Use formData if available, otherwise use user data
$editNom = $formData['nom'] ?? $user['nom'];
$editPrenom = $formData['prenom'] ?? $user['prenom'];
$editEmail = $formData['email'] ?? $user['email'];
$editUsername = $formData['username'] ?? $user['username'];
$editRole = $formData['role'] ?? $user['role'];
$editSiteId = $formData['site_id'] ?? $user['site_id'];

$pageTitle = 'Éditer l\'utilisateur — ' . e($user['prenom'] . ' ' . $user['nom']);
?>

<h1 class="page-title">Éditer l'utilisateur</h1>

<?php require __DIR__ . '/../templates/alert.php'; ?>

<div class="card">
    <form method="POST" action="<?php echo url('user_edit', ['id' => $userId]); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
        <input type="hidden" name="user_id" value="<?php echo e((string)$userId); ?>">

        <div class="form-grid">
            <div class="form-group">
                <label for="nom">Nom <span class="required">*</span></label>
                <input type="text" name="nom" id="nom" required maxlength="100" value="<?php echo e($editNom); ?>">
                <?php if (isset($formErrors['nom'])): ?><span class="form-error"><?php echo e($formErrors['nom']); ?></span><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="prenom">Prénom <span class="required">*</span></label>
                <input type="text" name="prenom" id="prenom" required maxlength="100" value="<?php echo e($editPrenom); ?>">
                <?php if (isset($formErrors['prenom'])): ?><span class="form-error"><?php echo e($formErrors['prenom']); ?></span><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" maxlength="200" value="<?php echo e($editEmail); ?>">
                <?php if (isset($formErrors['email'])): ?><span class="form-error"><?php echo e($formErrors['email']); ?></span><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="username">Identifiant <span class="required">*</span></label>
                <input type="text" name="username" id="username" required maxlength="100" value="<?php echo e($editUsername); ?>">
                <div class="form-hint">Identifiant de connexion Windows</div>
                <?php if (isset($formErrors['username'])): ?><span class="form-error"><?php echo e($formErrors['username']); ?></span><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="role">Rôle <span class="required">*</span></label>
                <select name="role" id="role" required>
                    <?php foreach (ROLE_LABELS as $key => $label): ?>
                    <option value="<?php echo e($key); ?>" <?php echo $editRole === $key ? 'selected' : ''; ?>><?php echo e($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($formErrors['role'])): ?><span class="form-error"><?php echo e($formErrors['role']); ?></span><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="site_id">Site <span class="required">*</span></label>
                <select name="site_id" id="site_id" required>
                    <?php foreach ($sites as $site): ?>
                    <option value="<?php echo (int) $site['id']; ?>" <?php echo (int) $editSiteId === (int) $site['id'] ? 'selected' : ''; ?>><?php echo e($site['nom']); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($formErrors['site_id'])): ?><span class="form-error"><?php echo e($formErrors['site_id']); ?></span><?php endif; ?>
            </div>
        </div>

        <!-- Password (optional) -->
        <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--grey-200);">
            <h4 style="margin-bottom:12px;color:var(--grey-700);">Changer le mot de passe (optionnel)</h4>
            <div class="form-grid">
                <div class="form-group">
                    <label for="password">Nouveau mot de passe</label>
                    <input type="password" name="password" id="password" autocomplete="new-password">
                    <div class="form-hint">Laissez vide pour ne pas changer</div>
                    <?php if (isset($formErrors['password'])): ?><span class="form-error"><?php echo e($formErrors['password']); ?></span><?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="password_confirm">Confirmer le mot de passe</label>
                    <input type="password" name="password_confirm" id="password_confirm" autocomplete="new-password">
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn--success">Mettre à jour</button>
            <a href="<?php echo url('users'); ?>" class="btn btn--secondary">Retour</a>
        </div>
    </form>
</div>

<!-- Delete user (soft delete) -->
<?php if ($user['is_active'] && (int) $user['id'] !== (int) ($_SESSION['user']['id'] ?? 0)): ?>
<div class="card" style="margin-top:20px;border-top:4px solid var(--dgi-color);">
    <h3 style="margin-bottom:12px;color:var(--dgi-color);">Zone dangereuse</h3>
    <p style="color:var(--grey-600);margin-bottom:16px;">La désactivation rendra le compte inutilisable. Cette action est réversible.</p>

    <?php if (isset($_GET['confirm_delete'])): ?>
    <!-- Confirmation inline — pas de JavaScript -->
    <div class="confirm-inline" style="background:var(--grey-100);padding:16px;border-radius:8px;margin-bottom:12px;">
        <p style="font-weight:600;margin-bottom:12px;">Êtes-vous sûr de vouloir désactiver cet utilisateur ?</p>
        <form method="POST" action="<?php echo url('user_delete'); ?>" style="display:flex;gap:8px;">
            <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
            <input type="hidden" name="user_id" value="<?php echo e((string)$userId); ?>">
            <button type="submit" class="btn btn--danger">Oui, désactiver</button>
            <a href="<?php echo url('user_edit', ['id' => $userId]); ?>" class="btn btn--secondary">Annuler</a>
        </form>
    </div>
    <?php else: ?>
    <a href="<?php echo url('user_edit', ['id' => $userId, 'confirm_delete' => 1]); ?>" class="btn btn--danger">Supprimer (désactiver)</a>
    <?php endif; ?>
</div>
<?php endif; ?>
