<?php
/**
 * Access Denied Page — Application SST DREETS BFC
 *
 * Shown when a user tries to access a page or resource
 * they don't have permission for.
 * Can be rendered standalone (by requireRole) or within the normal layout.
 */
$pageTitle = 'Accès refusé';
?>
<?php if (isImpersonatingRole()): ?>
<div class="impersonate-banner" role="alert">
    <span class="impersonate-banner__text">
        &#x1F3AD; Vous incarnez le rôle <strong><?php echo e(ROLE_LABELS[getImpersonatedRole()] ?? getImpersonatedRole()); ?></strong>
        <span class="impersonate-banner__hint">— les pages réservées aux superviseurs ne sont pas accessibles dans ce mode.</span>
    </span>
    <form method="POST" action="<?php echo url('impersonate'); ?>" class="impersonate-banner__form">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken ?? ''); ?>">
        <input type="hidden" name="action" value="stop">
        <button type="submit" class="impersonate-banner__btn">&#x21A9; Reprendre mon rôle</button>
    </form>
</div>
<?php endif; ?>
<div class="card empty-state access-denied-card">
    <div class="access-denied-icon">&#x1F6AB;</div>
    <h2>Accès refusé</h2>
    <p class="text-muted my-4">
        Vous n'avez pas les permissions nécessaires pour accéder à cette page.
    </p>
    <?php if (isUserLoggedIn()): ?>
    <p class="text-muted text-small">
        Votre rôle : <span class="badge <?php echo getRoleBadgeClass(currentUserRole()); ?>">
            <?php echo e(ROLE_LABELS[currentUserRole()] ?? currentUserRole()); ?>
        </span>
    </p>
    <?php endif; ?>
    <?php if (isImpersonatingRole()): ?>
    <p class="text-muted text-small mt-2">
        Vous incarnez actuellement le rôle <strong><?php echo e(ROLE_LABELS[getImpersonatedRole()] ?? getImpersonatedRole()); ?></strong>.
        Reprenez votre rôle de superviseur pour accéder à cette page.
    </p>
    <?php endif; ?>
    <div class="mt-6">
        <a href="<?php echo url('home'); ?>" class="btn btn--primary">Retour à l'accueil</a>
    </div>
</div>
