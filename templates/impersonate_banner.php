<?php
/**
 * Impersonation Banner — Application SST DREETS BFC
 *
 * Orange warning banner shown when a superviseur is impersonating a lower-privilege role.
 * Includes a "Reprendre mon rôle" button to restore the real role.
 * Visible on all pages when impersonation is active.
 */
?>
<?php if (isImpersonatingRole()): ?>
<div class="impersonate-banner" role="alert">
    <span class="impersonate-banner__text">
        <span class="impersonate-icon impersonate-icon--banner" aria-hidden="true"></span> Vous incarnez le rôle <strong><?php echo e(ROLE_LABELS[getImpersonatedRole()] ?? getImpersonatedRole()); ?></strong>
        <span class="impersonate-banner__hint">— vous voyez l'application avec les mêmes restrictions que ce rôle.</span>
    </span>
    <form method="POST" action="<?php echo url('impersonate'); ?>" class="impersonate-banner__form">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
        <input type="hidden" name="action" value="stop">
        <button type="submit" class="impersonate-banner__btn">&#x21A9; Reprendre mon rôle</button>
    </form>
</div>
<?php endif; ?>
