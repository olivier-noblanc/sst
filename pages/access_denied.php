<?php
/**
 * Access Denied Page — Application SST DREETS BFC
 * 
 * Shown when a user tries to access a page or resource
 * they don't have permission for.
 */
$pageTitle = 'Accès refusé';
?>
<div class="card empty-state" style="padding:40px 20px;">
    <div style="font-size:48px;margin-bottom:16px;">&#x1F6AB;</div>
    <h2>Accès refusé</h2>
    <p class="text-muted" style="margin:16px 0;">
        Vous n'avez pas les permissions nécessaires pour accéder à cette page.
    </p>
    <?php if (isset($_SESSION['user'])): ?>
    <p class="text-muted text-small">
        Votre rôle : <span class="badge <?php echo getRoleBadgeClass($_SESSION['user']['role']); ?>">
            <?php echo e(ROLE_LABELS[$_SESSION['user']['role']] ?? $_SESSION['user']['role']); ?>
        </span>
    </p>
    <?php endif; ?>
    <div style="margin-top:24px;">
        <a href="<?php echo url('home'); ?>" class="btn btn--primary">Retour à l'accueil</a>
    </div>
</div>
