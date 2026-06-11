<?php
/**
 * Header Template — Application SST DREETS BFC
 * 
 * Blue header bar with logo, app title, user name, and logout link.
 */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e(APP_NAME); ?> — <?php echo e($pageTitle ?? 'Accueil'); ?></title>
    <link rel="stylesheet" href="<?php echo assetUrl('css/style.css'); ?>">
</head>
<body>
    <header class="header" role="banner">
        <div class="header__logo">
            <?php if (file_exists(__DIR__ . '/../public/img/logo-dreets.png')): ?>
                <img src="img/logo-dreets.png" alt="DREETS BFC" class="header__logo-img">
            <?php else: ?>
                <span class="header__logo-text"><?php echo e(getConfig('app_nom_organisation', 'DREETS BFC')); ?></span>
            <?php endif; ?>
            <span class="header__title"><?php echo e(APP_NAME); ?></span>
        </div>
        <?php if (isset($_SESSION['user'])): ?>
        <div class="header__user">
            <span class="header__username">
                <?php echo e($_SESSION['user']['prenom'] ?? ''); ?> <?php echo e($_SESSION['user']['nom'] ?? ''); ?>
                <span class="badge <?php echo getRoleBadgeClass($_SESSION['user']['role'] ?? ''); ?>" style="margin-left:6px;font-size:11px;">
                    <?php echo e(ROLE_LABELS[$_SESSION['user']['role'] ?? 'agent'] ?? 'Agent'); ?>
                </span>
            </span>
            <a href="<?php echo url('logout'); ?>" class="header__logout" title="Déconnexion">
                ⇥ Déconnexion
            </a>
        </div>
        <?php endif; ?>
    </header>
