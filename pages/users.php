<?php
/**
 * Users Page — Application SST DREETS BFC
 * 
 * User management: list users and register new user.
 * Access: superviseur only
 */
requireRole(['superviseur']);

$pdo = getDB();

// Active tab
$activeTab = $_GET['tab'] ?? 'list';

// Search filter
$search = trim($_GET['q'] ?? '');

// Get all users
$allUsers = getAllUsers($pdo, 0, false); // include inactive

// Filter by search
if (!empty($search)) {
    $filtered = [];
    foreach ($allUsers as $u) {
        if (stripos($u['nom'], $search) !== false ||
            stripos($u['prenom'], $search) !== false ||
            stripos($u['email'], $search) !== false ||
            stripos($u['username'], $search) !== false ||
            stripos($u['site_nom'], $search) !== false) {
            $filtered[] = $u;
        }
    }
    $allUsers = $filtered;
}

// Get sites for the create form
$sites = getAllSites($pdo);

// Form data and errors from session
$formErrors = getFormErrors();
$formData = getFormData();

$pageTitle = 'Gestion des utilisateurs';
?>

<h1 class="page-title">Gestion des utilisateurs</h1>

<?php require __DIR__ . '/../templates/alert.php'; ?>

<!-- Tabs -->
<div class="tab-bar">
    <a href="<?php echo url('users', ['tab' => 'list']); ?>"
       class="settings-tab <?php echo $activeTab === 'list' ? 'settings-tab--active' : ''; ?>">
        &#x1F465; Liste des utilisateurs
    </a>
    <a href="<?php echo url('users', ['tab' => 'create']); ?>"
       class="settings-tab <?php echo $activeTab === 'create' ? 'settings-tab--active' : ''; ?>">
        &#x2795; Inscrire un utilisateur
    </a>
</div>

<?php if ($activeTab === 'list'): ?>
<!-- Search bar -->
<div class="filter-bar">
    <form method="GET" action="index.php" class="form--inline" style="gap:12px;align-items:flex-end;flex:1;">
        <input type="hidden" name="page" value="users">
        <input type="hidden" name="tab" value="list">
        <div class="form-group" style="flex:1;margin-bottom:0;">
            <input type="text" name="q" value="<?php echo e($search); ?>" placeholder="Rechercher un utilisateur..." style="width:100%;">
        </div>
        <button type="submit" class="btn btn--primary">Rechercher</button>
        <?php if (!empty($search)): ?>
        <a href="<?php echo url('users', ['tab' => 'list']); ?>" class="btn btn--outline">Effacer</a>
        <?php endif; ?>
    </form>
</div>

<!-- Users table -->
<div class="card">
    <div class="table-wrapper">
        <table aria-label="Liste des utilisateurs">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Prénom</th>
                    <th>Email</th>
                    <th>Rôle</th>
                    <th>Site</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($allUsers)): ?>
                <tr>
                    <td colspan="7" class="empty-state">
                        Aucun utilisateur trouvé.
                        <div class="empty-state__cta">
                            <a href="<?php echo url('users', ['tab' => 'create']); ?>" class="btn btn--primary btn--sm">+ Inscrire un utilisateur</a>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($allUsers as $u): ?>
                <tr class="<?php echo !$u['is_active'] ? 'row--inactive' : ''; ?>">
                    <td><?php echo e($u['nom']); ?></td>
                    <td><?php echo e($u['prenom']); ?></td>
                    <td><?php echo e($u['email'] ?? '—'); ?></td>
                    <td><span class="badge <?php echo getRoleBadgeClass($u['role']); ?>"><?php echo e(ROLE_LABELS[$u['role']] ?? $u['role']); ?></span></td>
                    <td><?php echo e($u['site_nom'] ?? '—'); ?></td>
                    <td>
                        <?php if ($u['is_active']): ?>
                            <span class="status-dot--active">&#x25CF; Actif</span>
                        <?php else: ?>
                            <span class="status-dot--inactive">&#x25CF; Inactif</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?php echo url('user_view', ['id' => (int) $u['id']]); ?>" class="btn btn--sm btn--outline">Voir</a>
                        <?php if ($u['is_active']): ?>
                        <a href="<?php echo url('user_edit', ['id' => (int) $u['id']]); ?>" class="btn btn--sm btn--primary">Éditer</a>
                        <?php else: ?>
                        <form method="POST" action="<?php echo url('user_reactivate'); ?>" class="form--inline">
                            <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                            <input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
                            <button type="submit" class="btn btn--sm btn--success">Réactiver</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="result-count">
        <?php echo count($allUsers); ?> utilisateur(s) affiché(s)
    </div>
</div>
<?php endif; ?>

<?php if ($activeTab === 'create'): ?>
<!-- Create user form -->
<div class="card">
    <h3 class="card__title">Inscrire un nouvel utilisateur</h3>
    <form method="POST" action="<?php echo url('user_create'); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">

        <div class="form-grid">
            <div class="form-group">
                <label for="nom">Nom <span class="required">*</span></label>
                <input type="text" name="nom" id="nom" required maxlength="100" value="<?php echo e($formData['nom'] ?? ''); ?>"
                       <?php echo isset($formErrors['nom']) ? 'aria-describedby="err_nom" aria-invalid="true"' : ''; ?>>
                <?php if (isset($formErrors['nom'])): ?><span class="form-error" id="err_nom"><?php echo e($formErrors['nom']); ?></span><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="prenom">Prénom <span class="required">*</span></label>
                <input type="text" name="prenom" id="prenom" required maxlength="100" value="<?php echo e($formData['prenom'] ?? ''); ?>"
                       <?php echo isset($formErrors['prenom']) ? 'aria-describedby="err_prenom" aria-invalid="true"' : ''; ?>>
                <?php if (isset($formErrors['prenom'])): ?><span class="form-error" id="err_prenom"><?php echo e($formErrors['prenom']); ?></span><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" maxlength="200" value="<?php echo e($formData['email'] ?? ''); ?>"
                       <?php echo isset($formErrors['email']) ? 'aria-describedby="err_email" aria-invalid="true"' : ''; ?>>
                <?php if (isset($formErrors['email'])): ?><span class="form-error" id="err_email"><?php echo e($formErrors['email']); ?></span><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="username">Identifiant <span class="required">*</span></label>
                <input type="text" name="username" id="username" required maxlength="100" value="<?php echo e($formData['username'] ?? ''); ?>"
                       aria-describedby="hint_username"
                       <?php echo isset($formErrors['username']) ? 'aria-describedby="err_username" aria-invalid="true"' : ''; ?>>
                <div class="form-hint" id="hint_username">Identifiant de connexion Windows (ex: jean.martin)</div>
                <?php if (isset($formErrors['username'])): ?><span class="form-error" id="err_username"><?php echo e($formErrors['username']); ?></span><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="role">Rôle <span class="required">*</span></label>
                <select name="role" id="role" required
                        <?php echo isset($formErrors['role']) ? 'aria-describedby="err_role" aria-invalid="true"' : ''; ?>>
                    <?php foreach (ROLE_LABELS as $key => $label): ?>
                    <option value="<?php echo e($key); ?>" <?php echo ($formData['role'] ?? 'agent') === $key ? 'selected' : ''; ?>><?php echo e($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($formErrors['role'])): ?><span class="form-error" id="err_role"><?php echo e($formErrors['role']); ?></span><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="site_id">Site <span class="required">*</span></label>
                <select name="site_id" id="site_id" required
                        <?php echo isset($formErrors['site_id']) ? 'aria-describedby="err_site_id" aria-invalid="true"' : ''; ?>>
                    <?php foreach ($sites as $site): ?>
                    <option value="<?php echo (int) $site['id']; ?>" <?php echo ($formData['site_id'] ?? 1) == $site['id'] ? 'selected' : ''; ?>><?php echo e($site['nom']); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($formErrors['site_id'])): ?><span class="form-error" id="err_site_id"><?php echo e($formErrors['site_id']); ?></span><?php endif; ?>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn--success">Créer l'utilisateur</button>
            <a href="<?php echo url('users', ['tab' => 'list']); ?>" class="btn btn--secondary">Annuler</a>
        </div>
    </form>
</div>

<?php endif; ?>
