<?php
/**
 * User Edit Page — Application SST DREETS BFC
 *
 * Edit user profile/role.
 * Access: superviseur only
 */
requireRole([ROLE_SUPERVISEUR]);

// Service instances (created once for the page)
$fmt = new \App\Services\FormattingService();
$http = new \App\Services\HttpService();
$config = \App\Services\ConfigService::getInstance();

$userId = (int) ($_GET['id'] ?? 0);

if ($userId <= 0) {
    new \App\Services\SessionService()->setFlash('error', 'Utilisateur introuvable.');
    new \App\Services\HttpService()->redirect(new \App\Services\HttpService()->url('users'));
}

$user = \App\Repository\UserRepository::instance()->findById($userId);

if (!$user) {
    new \App\Services\SessionService()->setFlash('error', 'Utilisateur introuvable.');
    new \App\Services\HttpService()->redirect(new \App\Services\HttpService()->url('users'));
}

// Get sites for dropdown
$sites = \App\Repository\SiteRepository::instance()->findAll();

// Form data and errors from session
$formErrors = new \App\Services\SessionService()->getFormErrors();
$formData = new \App\Services\SessionService()->getFormData();

/** @var array<string, mixed> $user */
$userPrenom = (string) ($user['prenom'] ?? '');
$userNom = (string) ($user['nom'] ?? '');
$pageTitle = 'Éditer l\'utilisateur — ' . $fmt->e($userPrenom . ' ' . $userNom);
?>

<h1 class="page-title">Éditer l'utilisateur</h1>


<div class="card">
    <form method="POST" action="<?php echo $http->url('user_edit', ['id' => $userId]); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo $fmt->e($csrfToken); ?>">
        <input type="hidden" name="user_id" value="<?php echo $userId; ?>">

        <?php
        // Prepare variables for the shared template
        $editNom = $formData['nom'] ?? $user['nom'] ?? '';
$editPrenom = $formData['prenom'] ?? $user['prenom'] ?? '';
$editEmail = $formData['email'] ?? $user['email'] ?? '';
$editUsername = $formData['username'] ?? $user['username'] ?? '';
$editRole = $formData['role'] ?? $user['role'] ?? ROLE_AGENT;
$editSiteId = $formData['site_id'] ?? $user['site_id'] ?? 1;
$usernameHint = 'Identifiant de connexion Windows';
require __DIR__ . '/../templates/user_form_fields.php';
?>

        <?php if (!empty($editEmail) && $editRole !== ($user['role'] ?? '')): ?>
        <?php if (($user['role'] ?? '') === ROLE_SUPERVISEUR && $editRole === ROLE_AGENT): ?>
        <div class="separator">
            <div class="alert alert--danger">
                <strong>Attention :</strong> Vous êtes sur le point de rétrograder un superviseur en agent. Cette action est significative.
            </div>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="confirm_demotion" value="1" required>
                    <span>Je confirme la rétrogradation de <strong><?php echo $fmt->e($userPrenom . ' ' . $userNom); ?></strong> de Superviseur à Agent</span>
                </label>
            </div>
        </div>
        <?php endif; ?>
        <div class="separator">
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="notify_role_change" value="1" checked>
                    <span>Avertir l'utilisateur par e-mail du changement de rôle</span>
                </label>
                <small class="text-muted block mt-1">
                    Un e-mail sera envoyé à <strong><?php echo $fmt->e($editEmail); ?></strong> pour l'informer que son rôle passe de
                    <strong><?php echo $fmt->e(ROLE_LABELS[(string) ($user['role'] ?? '')] ?? (string) ($user['role'] ?? '')); ?></strong> à
                    <strong><?php echo $fmt->e(ROLE_LABELS[$editRole] ?? $editRole); ?></strong>.
                </small>
            </div>
        </div>
        <?php elseif (!empty($editEmail) && $editRole === ($user['role'] ?? '')): ?>
        <input type="hidden" name="notify_role_change" value="0">
        <?php else: ?>
        <input type="hidden" name="notify_role_change" value="0">
        <?php endif; ?>

        <div class="form-actions">
            <button type="submit" class="btn btn--success">Mettre à jour</button>
            <a href="<?php echo $http->url('users'); ?>" class="btn btn--secondary">Retour</a>
        </div>
    </form>
</div>

<!-- Delete user (soft delete) -->
<?php if (!empty($user['is_active']) && (int) ($user['id'] ?? 0) !== (int) (new \App\Services\SessionService()->getUserSession()['id'] ?? 0)): ?>
<div class="card card--danger">
    <h3 class="section-header--danger">Zone dangereuse</h3>
    <p class="text-muted mb-4">La désactivation rendra le compte inutilisable. Cette action est réversible.</p>

    <?php if (isset($_GET['confirm_delete'])): ?>
    <!-- Confirmation inline — pas de JavaScript -->
    <div class="confirm-inline">
        <p>Êtes-vous sûr de vouloir désactiver cet utilisateur ?</p>
        <form method="POST" action="<?php echo $http->url('user_delete'); ?>" class="btn-group">
            <input type="hidden" name="csrf_token" value="<?php echo $fmt->e($csrfToken); ?>">
            <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
            <button type="submit" class="btn btn--danger">Oui, désactiver</button>
            <a href="<?php echo $http->url('user_edit', ['id' => $userId]); ?>" class="btn btn--secondary">Annuler</a>
        </form>
    </div>
    <?php else: ?>
    <a href="<?php echo $http->url('user_edit', ['id' => $userId, 'confirm_delete' => 1]); ?>" class="btn btn--danger">Supprimer (désactiver)</a>
    <?php endif; ?>
</div>
<?php endif; ?>
