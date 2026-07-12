<?php
/**
 * Quick Guide Page — Application SST DREETS BFC
 *
 * Printable A4 guide with 3 steps to create a report.
 * Designed for non-technical users (large text, screenshots, simple words).
 * Accessible at index.php?page=guide — no login required for printing.
 *
 * CSS served via css.php with proper HTTP caching (ETag, 304).
 */
$pageTitle = 'Guide rapide';
$ramiEnabled = \App\Services\ConfigService::getInstance()->isRegistryEnabled(TYPE_RAMI);
$dgiEnabled = \App\Services\ConfigService::getInstance()->isRegistryEnabled(TYPE_DGI);
$registryCount = 1 + ($ramiEnabled ? 1 : 0) + ($dgiEnabled ? 1 : 0);
$noSiteMode = \App\Services\ConfigService::getInstance()->isNoSiteMode();
$labelUnite = \App\Services\ConfigService::getInstance()->get('app_label_unite', 'UR');
?>
<?php echo new \App\Services\AssetService()->cssLink('css/guide.css'); ?>

<div class="guide">
    <div class="guide-header">
        <div class="guide-header__logo">DREETS Bourgogne-Franche-Comte</div>
        <h1 class="guide-header__title">Comment signaler un evenement ?</h1>
        <p class="guide-header__subtitle">Application SST — Guide en 3 etapes</p>
    </div>

    <button onclick="window.print()" class="guide-print-btn">Imprimer ce guide</button>

    <div class="guide-intro">
        <strong>Vous avez constate un probleme</strong> lie a la sante, la securite ou l'integrite au travail ?
        Voici comment le signaler en 3 etapes simples. Cela prend environ 2 minutes.
    </div>

    <!-- Step 1 -->
    <div class="guide-step">
        <div class="guide-step__number">1</div>
        <div class="guide-step__content">
            <h2 class="guide-step__title">Choisissez le bon registre</h2>
            <p class="guide-step__text">
                <?php if ($registryCount === 1): ?>
                Sur la page d'accueil, <strong>cliquez sur le bouton « Deposer un signalement »</strong>
                sous le registre <strong>RSST (Sante et Securite au Travail)</strong>.
                C'est le registre par defaut pour signaler tout probleme de sante ou securite au travail.
                <?php else: ?>
                Sur la page d'accueil, <strong>cliquez sur le bouton « Signaler un evenement »</strong>
                sous le registre correspondant :
                <strong>RSST (Sante et Securite au Travail)</strong><?php if ($ramiEnabled): ?>,
                <strong>RAMI (Agressions, Menaces, Incivilites)</strong><?php endif; ?><?php if ($dgiEnabled): ?> ou
                <strong>DGI (Danger Grave et Imminent)</strong><?php else: ?><?php if ($ramiEnabled): ?> ou<?php endif; ?><?php endif; ?>.
                Choisissez le registre qui correspond a votre situation.
                <?php endif; ?>
            </p>
            <div class="guide-registres">
                <div class="guide-registre guide-registre--rsst">
                    <span class="guide-registre__label">RSST</span>
                    Sante et securite au travail<br>
                    <small>Risques, equipements, ergonomie</small>
                </div>
                <?php if ($ramiEnabled): ?>
                <div class="guide-registre guide-registre--rami">
                    <span class="guide-registre__label">RAMI</span>
                    Agressions, menaces, incivilites<br>
                    <small>Harcement, violence verbale/physique</small>
                </div>
                <?php endif; ?>
                <?php if ($dgiEnabled): ?>
                <div class="guide-registre guide-registre--dgi">
                    <span class="guide-registre__label">DGI</span>
                    Danger grave et imminent<br>
                    <small>Urgence, droit de retrait</small>
                </div>
                <?php endif; ?>
            </div>
            <img src="screenshots/guide-etape1.png" alt="Page d'accueil avec les <?php echo $registryCount; ?> registres" class="guide-step__img" width="340">
            <p class="guide-step__caption">Capture d'ecran : la page d'accueil avec les <?php echo $registryCount; ?> registre<?php echo $registryCount > 1 ? 's' : ''; ?> et les boutons « Signaler un evenement »</p>
        </div>
    </div>

    <!-- Step 2 -->
    <div class="guide-step">
        <div class="guide-step__number">2</div>
        <div class="guide-step__content">
            <h2 class="guide-step__title">Remplissez le formulaire</h2>
            <p class="guide-step__text">
                Completez les champs obligatoires (marques d'une etoile <strong>*</strong>) :
            </p>
            <ul class="guide-step__list">
                <li><strong>Date de l'evenement</strong> — la date ou cela s'est passe</li>
                <?php if (!$noSiteMode): ?>
                <li><strong><?php echo new \App\Services\FormattingService()->e($labelUnite); ?> de rattachement</strong> — votre unite de travail</li>
                <?php endif; ?>
                <li><strong>Objet</strong> — un resume court en quelques mots</li>
                <li><strong>Description</strong> — expliquez ce qui s'est passe en detail</li>
            </ul>
            <p class="guide-step__text">
                Vous pouvez aussi ajouter une <strong>piece jointe</strong> (photo ou PDF) si vous avez un document a joindre.
            </p>
            <img src="screenshots/guide-etape2.png" alt="Formulaire de creation de signalement" class="guide-step__img" width="340">
            <p class="guide-step__caption">Capture d'ecran : le formulaire a remplir avec les champs Objet, Description, etc.</p>
            <div class="guide-step__example">
                <strong>Exemple d'objet :</strong> <code>Escalier casse au 2e etage</code><br>
                <strong>Exemple de description :</strong> <code>La rampe est desserree au 2e etage du batiment B. Quelqu'un pourrait tomber en descendant.</code>
            </div>
            <div class="guide-step__highlight">
                <strong>Conseil :</strong> ecrivez comme vous parlez, dans vos propres mots.
                Il n'y a pas de bonne ou mauvaise facon de decrire un evenement.
                Exemple : si vous dites « l'escalier est casse », ecrivez simplement
                « L'escalier du 2e etage est casse, quelqu'un pourrait tomber. »
            </div>
        </div>
    </div>

    <!-- Step 3 -->
    <div class="guide-step">
        <div class="guide-step__number">3</div>
        <div class="guide-step__content">
            <h2 class="guide-step__title">Envoyez et c'est fait !</h2>
            <p class="guide-step__text">
                Cliquez sur le bouton <strong>« Envoyer le signalement »</strong> en bas du formulaire.
            </p>
            <div class="guide-step__confirm">
                <strong>Apres l'envoi, un bandeau vert confirme que votre signalement a bien ete enregistre.</strong><br>
                Votre superviseur est automatiquement prevenu et va prendre en charge votre signalement.<br>
                Votre signalement porte un numero de reference (ex: RSST-2026-0001).
                Vous pouvez suivre son etat a tout moment depuis la liste des signalements.
            </div>
            <img src="screenshots/guide-etape3.png" alt="Confirmation d'enregistrement du signalement" class="guide-step__img" width="340">
            <p class="guide-step__caption">Capture d'ecran : le bandeau vert de confirmation apres l'envoi du signalement</p>
            <div class="guide-step__highlight">
                <strong>Et apres ?</strong> Votre signalement passe par 3 etats :
                <span class="guide-status--nouveau">Nouveau</span> : votre signalement vient d'etre envoye.
                &rarr;
                <span class="guide-status--encours">En cours</span> : votre superviseur examine le probleme.
                &rarr;
                <span class="guide-status--traite">Traite</span> : le probleme est resolu.
            </div>
        </div>
    </div>

    <div class="guide-footer">
        Application SST — DREETS Bourgogne-Franche-Comte | 
        Besoin d'aide ? Contactez votre superviseur ou consultez la documentation dans l'application.
    </div>
</div>
