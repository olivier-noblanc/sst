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
                <input type="text" name="nom" id="nom" required maxlength="100" value="<?php echo e($editNom); ?>"
                       <?php echo isset($formErrors['nom']) ? 'aria-describedby="err_nom" aria-invalid="true"' : ''; ?>>
                <?php if (isset($formErrors['nom'])): ?><span class="form-error" id="err_nom"><?php echo e($formErrors['nom']); ?></span><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="prenom">Prénom <span class="required">*</span></label>
                <input type="text" name="prenom" id="prenom" required maxlength="100" value="<?php echo e($editPrenom); ?>"
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
                <input type="text" name="username" id="username" required maxlength="100" value="<?php echo e($editUsername); ?>"
                       aria-describedby="hint_username"
                       <?php echo isset($formErrors['username']) ? 'aria-describedby="err_username" aria-invalid="true"' : ''; ?>>
                <div class="form-hint" id="hint_username">Identifiant de connexion Windows</div>
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
            <div class="form-group">
                <label for="site_id">Site <span class="required">*</span></label>
                <select name="site_id" id="site_id" required
                        <?php echo isset($formErrors['site_id']) ? 'aria-describedby="err_site_id" aria-invalid="true"' : ''; ?>>
                    <?php foreach ($sites as $site): ?>
                    <option value="<?php echo (int) $site['id']; ?>" <?php echo (int) $editSiteId === (int) $site['id'] ? 'selected' : ''; ?>><?php echo e($site['nom']); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($formErrors['site_id'])): ?><span class="form-error" id="err_site_id"><?php echo e($formErrors['site_id']); ?></span><?php endif; ?>
            </div>
        </div>

        <?php if (!empty($editEmail) && $editRole !== $user['role']): ?>
        <div class="separator">
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="notify_role_change" value="1" checked>
                    <span>Avertir l'utilisateur par e-mail du changement de rôle</span>
                </label>
                <small class="text-muted block mt-1">
                    Un e-mail sera envoyé à <strong><?php echo e($editEmail); ?></strong> pour l'informer que son rôle passe de
                    <strong><?php echo e(ROLE_LABELS[$user['role']] ?? $user['role']); ?></strong> à
                    <strong><?php echo e(ROLE_LABELS[$editRole] ?? $editRole); ?></strong>.
                </small>
            </div>
        </div>
        <?php elseif (!empty($editEmail) && $editRole === $user['role']): ?>
        <input type="hidden" name="notify_role_change" value="0">
        <?php else: ?>
        <input type="hidden" name="notify_role_change" value="0">
        <?php endif; ?>

        <div class="form-actions">
            <button type="submit" class="btn btn--success">Mettre à jour</button>
            <a href="<?php echo url('users'); ?>" class="btn btn--secondary">Retour</a>
        </div>
    </form>
</div>

<!-- Delete user (soft delete) -->
<?php if ($user['is_active'] && (int) $user['id'] !== (int) ($_SESSION['user']['id'] ?? 0)): ?>
<div class="card card--danger">
    <h3 class="section-header--danger">Zone dangereuse</h3>
    <p class="text-muted mb-4">La désactivation rendra le compte inutilisable. Cette action est réversible.</p>

    <?php if (isset($_GET['confirm_delete'])): ?>
    <!-- Confirmation inline — pas de JavaScript -->
    <div class="confirm-inline">
        <p>Êtes-vous sûr de vouloir désactiver cet utilisateur ?</p>
        <form method="POST" action="<?php echo url('user_delete'); ?>" class="btn-group">
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
