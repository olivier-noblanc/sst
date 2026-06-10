<?php
/**
 * Login Page — Application SST DREETS BFC
 * 
 * MOCK LOGIN FORM — DEV MODE ONLY
 * 
 * In production, IIS handles Windows Authentication before PHP runs.
 * This page is unreachable in prod (index.php redirects away).
 * This form exists ONLY for local development testing.
 */
$pageTitle = 'Connexion';

// Safety: if somehow accessed in prod, redirect away
if (!DEV_MODE) {
    if (isset($_SESSION['user'])) {
        redirect(url('home'));
    }
    die('Erreur : cette page n\'est pas accessible en production. '
      . 'L\'authentification est gérée par IIS Windows Authentication.');
}

// If already authenticated, redirect to home
if (isset($_SESSION['user'])) {
    redirect(url('home'));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e(APP_NAME); ?> — Connexion</title>
    <link rel="stylesheet" href="<?php echo assetUrl('css/style.css'); ?>">
</head>
<body class="login-body">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1><?php echo e(APP_NAME); ?></h1>
                <p class="login-subtitle"><?php echo e(getConfig('app_nom_complet', 'DREETS Bourgogne-Franche-Comté')); ?></p>
                <p class="login-dev-badge">⚙ Mode développement</p>
            </div>

            <?php $flash = getFlash(); ?>
            <?php if ($flash): ?>
                <div class="alert alert--<?php echo e($flash['type']); ?>" role="alert">
                    <?php echo e($flash['message']); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?php echo url('login'); ?>" class="login-form">
                <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">
                <div class="form-group">
                    <label for="username">Nom d'utilisateur</label>
                    <input type="text" id="username" name="username" required
                           placeholder="Ex: admin.dev ou agent.dev"
                           autofocus
                           value="<?php echo e($_POST['username'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <input type="password" id="password" name="password"
                           placeholder="(non vérifié en mode dev)">
                    <span class="form-hint">Le mot de passe n'est pas vérifié en mode développement.</span>
                </div>

                <button type="submit" class="btn btn--primary btn--full">Se connecter</button>
            </form>

            <div class="login-dev-info">
                <p><strong>Comptes de test :</strong></p>
                <ul>
                    <li><code>admin.dev</code> — Superviseur (Siège)</li>
                    <li><code>agent.dev</code> — Agent (UR21 Côte-d'Or)</li>
                    <li><code>chsct.dev</code> — Membre CHSCT (Siège)</li>
                </ul>
                <p style="font-size:12px;color:var(--grey-500);margin-top:8px;">
                    Les logins configurés dans Paramètres → Logins superviseur seront automatiquement promus Superviseur.
                </p>
                <p style="font-size:12px;color:var(--grey-500);margin-top:4px;">
                    Tout autre nom d'utilisateur créera un compte agent automatiquement.
                </p>
                <p style="font-size:11px;color:var(--grey-400);margin-top:12px;border-top:1px solid var(--grey-200);padding-top:8px;">
                    ⚠ En production, cette page n'existe pas. L'authentification est automatique via IIS Windows Authentication.
                </p>
            </div>
        </div>
    </div>
</body>
</html>
