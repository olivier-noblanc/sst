<?php
/**
 * Choose Site Page — Application SST DREETS BFC
 *
 * Shown to agents on their first login when site_id is NULL.
 * Agents can change their site within 7 days of first selection.
 * After 7 days, only a supervisor can change it via user management.
 *
 * NOTE: This page is rendered BEFORE the layout (no header.php).
 * Cache-Control and security headers must be sent here.
 */

// Headers already cleaned by removeUnwantedHeaders() in index.php bootstrap

// === Cache-Control: no-cache for this dynamic page ===
header('Cache-Control: no-cache');

// === Security Headers (same as header.php — this page bypasses layout) ===
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; frame-ancestors 'none';");

$pageTitle = 'Choisir mon site';

// Service instances (created once for the page)
$fmt = new \App\Services\FormattingService();
$http = new \App\Services\HttpService();
$config = getConfigService();

// In noSiteMode, there are no sites to choose — redirect home
if ($config->isNoSiteMode()) {
    new \App\Services\SessionService()->setFlash('info', 'Aucun site n\'est configuré pour le moment. Vous pouvez utiliser l\'application sans rattachement.');
    $http->redirect($http->url('home'));
}

$user = new \App\Services\SessionService()->getUserSession();
$userSiteId = $user->siteId ?? 0;
$hasExistingSite = $user !== null && $user->siteId !== null;
$isWithinGracePeriod = false;
$daysRemaining = 0;

// Safety: if user already has a site, check grace period
if ($hasExistingSite) {
    $siteChosenAt = $user->siteChosenAt;
    if ($siteChosenAt !== null) {
        /** @var string */
        $siteChosenAtStr = $siteChosenAt;
        $chosenTime = strtotime($siteChosenAtStr);
        if ($chosenTime === false) {
            $chosenTime = 0;
        }
        $daysSinceChoice = (time() - $chosenTime) / 86400;
        $isWithinGracePeriod = $daysSinceChoice <= 7;
        $daysRemaining = max(0, ceil(7 - $daysSinceChoice));
    }

    if (!$isWithinGracePeriod) {
        // Outside grace period — redirect home with message
        new \App\Services\SessionService()->setFlash('info', 'Le délai de 7 jours pour modifier votre site est dépassé. Contactez votre superviseur pour changer de site.');
        $http->redirect($http->url('home'));
    }
} else {
    // No site yet — redirect to choose_site if trying to access home
    // This is the normal first-login flow
}

$sites = \App\Repository\SiteRepository::instance()->findActive();

$labelUnite = $config->get('app_label_unite', 'UR');
?>

<h1 class="page-title"><span aria-hidden="true">&#x1F4CD;</span> <?php echo $hasExistingSite ? 'Modifier mon site' : 'Choisissez votre ' . $fmt->e($labelUnite); ?></h1>

<div class="card container--narrow">
    <?php if ($hasExistingSite): ?>
    <p class="choose-site-welcome">
        <strong><?php echo $fmt->e(currentUserDisplayName()); ?></strong>, vous pouvez modifier votre <?php echo $fmt->e($labelUnite); ?>.
    </p>
    <p class="text-muted text-small mb-4">
        Votre site actuel : <strong><?php echo $fmt->e($user->siteCode ?? ''); ?> — <?php echo $fmt->e($user->siteNom ?? ''); ?></strong>.
        Vous avez <strong><?php echo $daysRemaining; ?> jour<?php echo $daysRemaining != 1 ? 's' : ''; ?></strong> pour modifier votre choix.
        Après ce délai, seul un superviseur pourra le changer.
    </p>
    <?php else: ?>
    <p class="choose-site-welcome">
        Bienvenue, <strong><?php echo $fmt->e(currentUserDisplayName()); ?></strong>.
    </p>
    <p class="text-muted text-small mb-4">
        Avant de continuer, vous devez sélectionner votre site (<?php echo $fmt->e($labelUnite); ?>).
        Vous pourrez modifier votre choix pendant <strong>7 jours</strong>. Après ce délai, seul un superviseur pourra le changer.
    </p>
    <?php endif; ?>

    <?php $flash = new \App\Services\SessionService()->getFlash(); ?>
    <?php if ($flash !== null): ?>
        <div class="alert alert--<?php echo $fmt->e($flash->type); ?>" role="alert">
            <?php echo $fmt->e($flash->message); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?php echo $http->url('choose_site'); ?>" id="chooseSiteForm">
        <input type="hidden" name="csrf_token" value="<?php echo $fmt->e(new \App\Services\SessionService()->generateCsrfToken()); ?>">
        
        <div class="form-group">
            <label for="site_id">Votre site (<?php echo $fmt->e($labelUnite); ?>) <span class="required">*</span></label>
            <select id="site_id" name="site_id" required>
                <option value="">— Sélectionnez votre <?php echo $fmt->e($labelUnite); ?> —</option>
                <?php foreach ($sites as $site): ?>
                <?php
                    /** @var int */
                    $siteId = $site['id'];
                    /** @var string */
                    $siteCode = $site['code'];
                    /** @var string */
                    $siteNom = $site['nom'];
                    ?>
                <option value="<?php echo $fmt->e((string) $siteId); ?>" <?php echo $hasExistingSite && $userSiteId === $siteId ? '' : ''; ?>>
                    <?php echo $fmt->e($siteCode . ' — ' . $siteNom); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($hasExistingSite): ?>
        <div class="warning-panel">
            &#x26A0;&#xFE0F; <strong>Attention :</strong> modifier votre site affectera la visibilité de vos signalements. Ce changement sera enregistré dans le journal d'audit.
        </div>
        <button type="submit" class="btn btn--primary btn--full">Modifier mon site</button>
        <a href="<?php echo $http->url('home'); ?>" class="btn btn--secondary btn--full mt-2">Annuler</a>
        <?php else: ?>
        <div class="danger-panel">
            &#x26A0;&#xFE0F; <strong>Attention :</strong> ce choix peut être modifié pendant 7 jours uniquement. Passé ce délai, contactez un superviseur.
        </div>
        <button type="submit" class="btn btn--primary btn--full">Confirmer mon choix</button>
        <?php endif; ?>
    </form>
</div>
