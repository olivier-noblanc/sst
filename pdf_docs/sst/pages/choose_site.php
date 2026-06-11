<?php
/**
 * Choose Site Page — Application SST DREETS BFC
 * 
 * Shown to agents on their first login when site_id is NULL.
 * The choice is irreversible for the agent — only a superviseur
 * can change it later via user management.
 */
$pageTitle = 'Choisir mon site';

// Safety: if user already has a site, redirect away
if (!empty($_SESSION['user']['site_id'])) {
    redirect(url('home'));
}

$pdo = getDB();
$sites = getActiveSites($pdo);

$labelUnite = getConfig('app_label_unite', 'UR');
?>

<h1 class="page-title">📍 Choisissez votre <?php echo e($labelUnite); ?></h1>

<div class="card" style="max-width:600px;margin:0 auto;">
    <p style="margin-bottom:8px;font-size:14px;">
        Bienvenue, <strong><?php echo e($_SESSION['user']['prenom'] ?? ''); ?> <?php echo e($_SESSION['user']['nom'] ?? ''); ?></strong>.
    </p>
    <p style="margin-bottom:16px;color:var(--grey-600);font-size:13px;">
        Avant de continuer, vous devez sélectionner votre site (<?php echo e($labelUnite); ?>). 
        Ce choix est <strong>définitif</strong> — vous ne pourrez pas le modifier vous-même par la suite. 
        Seul un superviseur pourra le changer si nécessaire.
    </p>

    <?php $flash = getFlash(); ?>
    <?php if ($flash): ?>
        <div class="alert alert--<?php echo e($flash['type']); ?>" role="alert">
            <?php echo e($flash['message']); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?php echo url('choose_site'); ?>" id="chooseSiteForm">
        <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">
        
        <div class="form-group">
            <label for="site_id">Votre site (<?php echo e($labelUnite); ?>) <span style="color:var(--dgi-color,#dc2626);">*</span></label>
            <select id="site_id" name="site_id" required>
                <option value="">— Sélectionnez votre <?php echo e($labelUnite); ?> —</option>
                <?php foreach ($sites as $site): ?>
                <option value="<?php echo e($site['id']); ?>">
                    <?php echo e($site['code'] . ' — ' . $site['nom']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="margin-bottom:16px;padding:12px 16px;background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;color:#991b1b;font-size:13px;">
            ⚠️ <strong>Attention :</strong> ce choix est définitif. Vous ne pourrez plus le modifier par vous-même. En cas d'erreur, contactez un superviseur.
        </div>

        <button type="submit" class="btn btn--primary btn--full">Confirmer mon choix</button>
    </form>
</div>
