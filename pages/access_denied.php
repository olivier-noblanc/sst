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
<?php if ((new \App\Services\SessionService())->isImpersonatingRole()): ?>
<div class="impersonate-banner" role="alert">
    <span class="impersonate-banner__text">
        &#x1F3AD; Vous incarnez le rôle <strong><?php echo (new \App\Services\FormattingService())->e(ROLE_LABELS[(new \App\Services\SessionService())->getImpersonatedRole()] ?? (new \App\Services\SessionService())->getImpersonatedRole()); ?></strong>
        <span class="impersonate-banner__hint">— les pages réservées aux superviseurs ne sont pas accessibles dans ce mode.</span>
    </span>
    <form method="POST" action="<?php echo (new \App\Services\HttpService())->url('impersonate'); ?>" class="impersonate-banner__form">
        <input type="hidden" name="csrf_token" value="<?php echo (new \App\Services\FormattingService())->e($csrfToken ?? ''); ?>">
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
    <?php if ((new \App\Services\SessionService())->isUserLoggedIn()): ?>
    <p class="text-muted text-small">
        Votre rôle : <span class="badge <?php echo (new \App\Services\FormattingService())->getRoleBadgeClass(currentUserRole()); ?>">
            <?php echo (new \App\Services\FormattingService())->e(ROLE_LABELS[currentUserRole()] ?? currentUserRole()); ?>
        </span>
    </p>
    <?php endif; ?>
    <?php if ((new \App\Services\SessionService())->isImpersonatingRole()): ?>
    <p class="text-muted text-small mt-2">
        Vous incarnez actuellement le rôle <strong><?php echo (new \App\Services\FormattingService())->e(ROLE_LABELS[(new \App\Services\SessionService())->getImpersonatedRole()] ?? (new \App\Services\SessionService())->getImpersonatedRole()); ?></strong>.
        Reprenez votre rôle de superviseur pour accéder à cette page.
    </p>
    <?php endif; ?>
    <div class="mt-6">
        <a href="<?php echo (new \App\Services\HttpService())->url('home'); ?>" class="btn btn--primary">Retour à l'accueil</a>
    </div>
</div>
