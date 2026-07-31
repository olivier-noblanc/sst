<?php
/**
 * User View Page — Application SST DREETS BFC
 *
 * View user profile (read-only).
 * Access: superviseur only
 */
requireRole([\App\Enum\UserRole::Superviseur->value]);

// Service instances (created once for the page)
$fmt = new \App\Services\FormattingService();
$http = new \App\Services\HttpService();
$config = getConfigService();

$pdo = getDB();
$noSiteMode = $config->isNoSiteMode();
$userId = (int) ($_GET['id'] ?? 0);
/** @var int $userId */

if ($userId <= 0) {
    new \App\Services\SessionService()->setFlash('error', 'Utilisateur introuvable.');
    new \App\Services\HttpService()->redirect(new \App\Services\HttpService()->url('users'));
}

$user = \App\Repository\UserRepository::instance()->findById($userId);

if ($user === null) {
    new \App\Services\SessionService()->setFlash('error', 'Utilisateur introuvable.');
    new \App\Services\HttpService()->redirect(new \App\Services\HttpService()->url('users'));
    return;
}
$userPrenom = $user->prenom;
$userNom = $user->nom;
$userRole = $user->role;
$userEmail = (string) ($user->email ?? '');
$userUsername = $user->username;
$userSiteNom = $user->siteNom ?? '—';
$userCreatedAt = $user->createdAt;
$userUpdatedAt = $user->updatedAt ?? '';
$userIsActive = !empty($user->isActive);

// Get user's report count
$reportCount = \App\Repository\ReportRepository::instance()->countByDeclarantId($userId);

$pageTitle = 'Utilisateur — ' . $fmt->e($userPrenom . ' ' . $userNom);
?>

<h1 class="page-title">Profil utilisateur</h1>


<div class="card">
    <div class="user-profile-header">
        <div>
            <h2 class="card__subtitle mb-1"><?php echo $fmt->e($userPrenom . ' ' . $userNom); ?></h2>
            <div class="btn-group--inline items-center">
                <span class="badge <?php echo $fmt->getRoleBadgeClass($userRole); ?>"><?php echo $fmt->e(ROLE_LABELS[$userRole] ?? $userRole); ?></span>
                <?php if ($userIsActive): ?>
                    <span class="status-dot--active">&#x25CF; Actif</span>
                <?php else: ?>
                    <span class="status-dot--inactive">&#x25CF; Inactif</span>
                <?php endif; ?>
            </div>
        </div>
        <a href="<?php echo $http->url('user_edit', ['id' => $userId]); ?>" class="btn btn--primary">Éditer</a>
    </div>

    <table class="report-detail__table" aria-label="Informations utilisateur">
        <tr>
            <th>Nom</th>
            <td><?php echo $fmt->e($userNom); ?></td>
        </tr>
        <tr>
            <th>Prénom</th>
            <td><?php echo $fmt->e($userPrenom); ?></td>
        </tr>
        <tr>
            <th>Email</th>
            <td><?php echo $fmt->e($userEmail); ?></td>
        </tr>
        <tr>
            <th>Identifiant</th>
            <td><code class="code-inline"><?php echo $fmt->e($userUsername); ?></code></td>
        </tr>
        <tr>
            <th>Rôle</th>
            <td><span class="badge <?php echo $fmt->getRoleBadgeClass($userRole); ?>"><?php echo $fmt->e(ROLE_LABELS[$userRole] ?? $userRole); ?></span></td>
        </tr>
        <?php if (!$noSiteMode): ?>
        <tr>
            <th><?php echo $fmt->e($config->get('app_label_unite', 'UR')); ?></th>
            <td><?php echo $fmt->e($userSiteNom); ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <th>Signalements créés</th>
            <td><?php echo $reportCount; ?></td>
        </tr>
        <tr>
            <th>Date de création</th>
            <td><?php echo $fmt->e($fmt->formatDateTimeFR($userCreatedAt)); ?></td>
        </tr>
        <tr>
            <th>Dernière modification</th>
            <td><?php echo $fmt->e($fmt->formatDateTimeFR($userUpdatedAt)); ?></td>
        </tr>
    </table>
</div>

<div class="form-actions">
    <a href="<?php echo $http->url('user_edit', ['id' => $userId]); ?>" class="btn btn--primary">Éditer</a>
    <a href="<?php echo $http->url('users'); ?>" class="btn btn--secondary">Retour à la liste</a>
</div>

<?php if (!empty($user->isActive) || $userNom !== \App\Repository\AnonymizationPolicy::ANONYMIZED_NAME): ?>
<div class="card rgpd-section mt-5">
    <h3 class="card__subtitle text-muted">RGPD — Données personnelles</h3>
    <p class="rgpd-section__desc">
        Conformément au RGPD, l'utilisateur peut exercer son droit d'accès (export) ou d'effacement (anonymisation).
        L'anonymisation remplace les données personnelles par des placeholders et désactive le compte. Les signalements sont conservés.
    </p>
    <div class="btn-group flex-wrap">
        <?php /** @var string $csrfToken */ ?>
        <form method="POST" action="<?php echo $http->url('user_edit', ['id' => $userId]); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo $fmt->e($csrfToken); ?>">
            <input type="hidden" name="action" value="export_data">
            <button type="submit" class="btn btn--outline text-small">&#x1F4E5; Exporter les données (droit d'accès)</button>
        </form>
        <?php if ($userNom !== \App\Repository\AnonymizationPolicy::ANONYMIZED_NAME): ?>
        <?php if (isset($_GET['confirm_anonymize'])): ?>
        <span class="section-header--danger">&#x26A0;&#xFE0F; Anonymiser définitivement ?</span>
        <form method="POST" action="<?php echo $http->url('user_edit', ['id' => $userId]); ?>" class="form--inline">
            <input type="hidden" name="csrf_token" value="<?php echo $fmt->e($csrfToken); ?>">
            <input type="hidden" name="action" value="anonymize">
            <button type="submit" class="btn btn--danger text-small">Oui, anonymiser</button>
        </form>
        <a href="<?php echo $http->url('user_view', ['id' => $userId]); ?>" class="btn btn--secondary text-small">Annuler</a>
        <?php else: ?>
        <a href="<?php echo $http->url('user_view', ['id' => $userId, 'confirm_anonymize' => 1]); ?>" class="btn btn--danger text-small">&#x1F512; Anonymiser (droit d'effacement)</a>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
