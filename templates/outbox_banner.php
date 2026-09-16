<?php
/**
 * Outbox Banner — Application SST DREETS BFC
 *
 * Bannière locale NON-DISMISSIBLE signalant un incident d'envoi SMTP/outbox
 * (messages en échec définitif, ou pending échus trop anciens). Le texte
 * demande explicitement l'intervention d'un technicien et renvoie vers les
 * paramètres SMTP. Affichée sur toutes les pages connectées (incluse par
 * templates/header.php).
 *
 * Aucun style inline : les styles sont portés par public/css/style.css
 * (.outbox-banner / .outbox-banner__text / .outbox-banner__link).
 * Zéro JavaScript : la bannière n'est pas fermable.
 */
if (!isUserLoggedIn()) {
    return;
}

if (!\App\Services\OutboxHealthService::instance()->hasIncident()) {
    return;
}

$smtpSettingsUrl = new \App\Services\HttpService()->url('settings', ['tab' => 'smtp']);
?>
<div class="outbox-banner" role="alert">
    <p class="outbox-banner__text">
        <strong>Envoi des e-mails indisponible.</strong>
        Des messages n'ont pas pu être envoyés&nbsp;: l'intervention d'un technicien est nécessaire pour rétablir le service.
    </p>
    <a class="outbox-banner__link" href="<?php echo e($smtpSettingsUrl); ?>">Vérifier la configuration SMTP</a>
</div>