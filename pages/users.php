<?php
/**
 * Users Page — Application SST DREETS BFC
 *
 * User management: list users and register new user.
 * Access: superviseur only
 */
requireRole([ROLE_SUPERVISEUR]);

// Service instances (created once for the page)
$fmt = new \App\Services\FormattingService();
$http = new \App\Services\HttpService();
$config = \App\Services\ConfigService::getInstance();

$noSiteMode = $config->isNoSiteMode();

// Active tab
$activeTab = (string) ($_GET['tab'] ?? 'list');

// Search filter
$search = trim((string) ($_GET['q'] ?? ''));

// Get all users
$allUsers = \App\Repository\UserRepository::instance()->findAll(0, false); // include inactive

// Filter by search
if (!empty($search)) {
    $filtered = [];
    foreach ($allUsers as $u) {
        if (stripos((string) $u['nom'], $search) !== false
            || stripos((string) $u['prenom'], $search) !== false
            || stripos((string) $u['email'], $search) !== false
            || stripos((string) $u['username'], $search) !== false
            || (!$noSiteMode && stripos((string) $u['site_nom'], $search) !== false)) {
            $filtered[] = $u;
        }
    }
    $allUsers = $filtered;
}

// Get sites for the create form
$sites = \App\Repository\SiteRepository::instance()->findAll();

// Form data and errors from session
$formErrors = (new \App\Services\SessionService())->getFormErrors();
$formData = (new \App\Services\SessionService())->getFormData();

$pageTitle = 'Gestion des utilisateurs';
?>

<h1 class="page-title">Gestion des utilisateurs</h1>


<!-- Tabs -->
<div class="tab-bar">
    <a href="<?php echo $http->url('users', ['tab' => 'list']); ?>"
       class="settings-tab <?php echo $activeTab === 'list' ? 'settings-tab--active' : ''; ?>">
        &#x1F465; Liste des utilisateurs
    </a>
    <a href="<?php echo $http->url('users', ['tab' => 'create']); ?>"
       class="settings-tab <?php echo $activeTab === 'create' ? 'settings-tab--active' : ''; ?>">
        &#x2795; Inscrire un utilisateur
    </a>
</div>

<?php if ($activeTab === 'list'): ?>
<!-- Search bar -->
<div class="filter-bar">
    <form method="GET" action="index.php" class="form--inline gap-3 align-self-end flex-1">
        <input type="hidden" name="page" value="users">
        <input type="hidden" name="tab" value="list">
        <div class="form-group flex-1 mb-0">
            <input type="text" name="q" value="<?php echo $fmt->e($search); ?>" placeholder="Rechercher un utilisateur..." class="w-full">
        </div>
        <button type="submit" class="btn btn--primary">Rechercher</button>
        <?php if (!empty($search)): ?>
        <a href="<?php echo $http->url('users', ['tab' => 'list']); ?>" class="btn btn--outline">Effacer</a>
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
                    <?php if (!$noSiteMode): ?><th><?php echo $fmt->e($config->get('app_label_unite', 'UR')); ?></th><?php endif; ?>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($allUsers)): ?>
                <tr>
                    <td colspan="<?php echo $noSiteMode ? '6' : '7'; ?>" class="empty-state">
                        Aucun utilisateur trouvé.
                        <div class="empty-state__cta">
                            <a href="<?php echo $http->url('users', ['tab' => 'create']); ?>" class="btn btn--primary btn--sm">+ Inscrire un utilisateur</a>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($allUsers as $u): ?>
                <?php /** @var array<string, mixed> $u */ ?>
                <tr class="<?php echo !$u['is_active'] ? 'row--inactive' : ''; ?>">
                    <td><?php echo $fmt->e((string) ($u['nom'] ?? '')); ?></td>
                    <td><?php echo $fmt->e((string) ($u['prenom'] ?? '')); ?></td>
                    <td><?php echo $fmt->e((string) ($u['email'] ?? '—')); ?></td>
                    <td><span class="badge <?php echo $fmt->getRoleBadgeClass((string) ($u['role'] ?? '')); ?>"><?php echo $fmt->e(ROLE_LABELS[(string) ($u['role'] ?? '')] ?? (string) ($u['role'] ?? '')); ?></span></td>
                    <?php if (!$noSiteMode): ?><td><?php echo $fmt->e((string) ($u['site_nom'] ?? '—')); ?></td><?php endif; ?>
                    <td>
                        <?php if ($u['is_active']): ?>
                            <span class="status-dot--active">&#x25CF; Actif</span>
                        <?php else: ?>
                            <span class="status-dot--inactive">&#x25CF; Inactif</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?php echo $http->url('user_view', ['id' => (int) $u['id']]); ?>" class="btn btn--sm btn--outline">Voir</a>
                        <?php if ($u['is_active']): ?>
                        <a href="<?php echo $http->url('user_edit', ['id' => (int) $u['id']]); ?>" class="btn btn--sm btn--primary">Éditer</a>
                        <?php else: ?>
                        <form method="POST" action="<?php echo $http->url('user_reactivate'); ?>" class="form--inline">
                            <input type="hidden" name="csrf_token" value="<?php echo $fmt->e($csrfToken); ?>">
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
    <form method="POST" action="<?php echo $http->url('user_create'); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo $fmt->e($csrfToken); ?>">

        <?php
        // Prepare variables for the shared template
        $editNom = $formData['nom'] ?? '';
    $editPrenom = $formData['prenom'] ?? '';
    $editEmail = $formData['email'] ?? '';
    $editUsername = $formData['username'] ?? '';
    $editRole = $formData['role'] ?? ROLE_AGENT;
    $editSiteId = $formData['site_id'] ?? 1;
    $usernameHint = 'Identifiant de connexion Windows (ex: jean.martin)';
    require __DIR__ . '/../templates/user_form_fields.php';
    ?>

        <div class="form-actions">
            <button type="submit" class="btn btn--success">Créer l'utilisateur</button>
            <a href="<?php echo $http->url('users', ['tab' => 'list']); ?>" class="btn btn--secondary">Annuler</a>
        </div>
    </form>
</div>

<?php endif; ?>
