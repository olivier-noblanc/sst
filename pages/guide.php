<?php
/**
 * Quick Guide Page — Application SST DREETS BFC
 *
 * Printable A4 guide with 3 steps to create a report.
 * Designed for non-technical users (large text, screenshots, simple words).
 * Accessible at index.php?page=guide — no login required for printing.
 *
 * CSS is self-contained (print-optimized, no sidebar/header when printed).
 */
$pageTitle = 'Guide rapide';
?>
<style>
    /* Guide page — self-contained print-ready styles */
    .guide {
        max-width: 800px;
        margin: 0 auto;
        padding: 24px;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        color: #1a1a1a;
        line-height: 1.6;
    }
    .guide-header {
        text-align: center;
        border-bottom: 3px solid #0056A3;
        padding-bottom: 16px;
        margin-bottom: 24px;
    }
    .guide-header__logo {
        font-size: 14px;
        color: #666;
        margin-bottom: 4px;
    }
    .guide-header__title {
        font-size: 26px;
        font-weight: 700;
        color: #0056A3;
        margin: 8px 0 4px 0;
    }
    .guide-header__subtitle {
        font-size: 16px;
        color: #555;
        margin: 0;
    }
    .guide-intro {
        background: #e8f4fd;
        border-left: 4px solid #0056A3;
        padding: 14px 18px;
        margin-bottom: 24px;
        border-radius: 4px;
        font-size: 15px;
    }
    .guide-intro strong {
        color: #0056A3;
    }
    .guide-step {
        display: flex;
        gap: 20px;
        margin-bottom: 20px;
        align-items: flex-start;
        page-break-inside: avoid;
    }
    .guide-step__number {
        flex-shrink: 0;
        width: 52px;
        height: 52px;
        border-radius: 50%;
        background: #0056A3;
        color: white;
        font-size: 26px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        line-height: 1;
    }
    .guide-step__content {
        flex: 1;
    }
    .guide-step__title {
        font-size: 18px;
        font-weight: 700;
        color: #0056A3;
        margin: 0 0 6px 0;
    }
    .guide-step__text {
        font-size: 15px;
        margin: 0 0 10px 0;
    }
    .guide-step__img {
        border: 1px solid #ddd;
        border-radius: 8px;
        max-width: 100%;
        height: auto;
        display: block;
        margin: 0 auto;
    }
    .guide-step__highlight {
        background: #fff3cd;
        border: 1px solid #ffc107;
        border-radius: 6px;
        padding: 10px 14px;
        margin-top: 10px;
        font-size: 14px;
    }
    .guide-step__highlight strong {
        color: #856404;
    }
    .guide-registres {
        display: flex;
        gap: 12px;
        margin: 16px 0;
    }
    .guide-registre {
        flex: 1;
        text-align: center;
        padding: 12px 8px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
    }
    .guide-registre--rsst {
        background: #e8f0f8;
        border: 2px solid #2E5C8A;
        color: #2E5C8A;
    }
    .guide-registre--rami {
        background: #f0f0f0;
        border: 2px solid #6C6C6C;
        color: #6C6C6C;
    }
    .guide-registre--dgi {
        background: #fde8e8;
        border: 2px solid #B22222;
        color: #B22222;
    }
    .guide-registre__label {
        display: block;
        font-size: 20px;
        font-weight: 700;
        margin-bottom: 2px;
    }
    .guide-footer {
        text-align: center;
        font-size: 13px;
        color: #888;
        border-top: 1px solid #ddd;
        padding-top: 12px;
        margin-top: 24px;
    }
    .guide-print-btn {
        display: inline-block;
        padding: 10px 24px;
        background: #0056A3;
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 16px;
        cursor: pointer;
        margin-bottom: 20px;
        text-decoration: none;
    }
    .guide-print-btn:hover {
        background: #003D75;
    }

    @media print {
        .guide-print-btn { display: none !important; }
        .guide { padding: 0; max-width: 100%; }
        .guide-step { margin-bottom: 14px; }
        body { background: white !important; }
        /* Hide app header/sidebar/footer when printing guide */
        .header, .sidebar, .footer, .skip-link,
        .sidebar-toggle-checkbox, .sidebar-overlay,
        .impersonate-banner { display: none !important; }
        .main-content { margin: 0 !important; padding: 0 !important; }
    }
</style>

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
                Sur la page d'accueil, cliquez sur <strong>« Signaler un evenement »</strong>
                sous le registre qui correspond a votre situation :
            </p>
            <div class="guide-registres">
                <div class="guide-registre guide-registre--rsst">
                    <span class="guide-registre__label">RSST</span>
                    Sante et securite au travail<br>
                    <small>Risques, equipements, ergonomie</small>
                </div>
                <div class="guide-registre guide-registre--rami">
                    <span class="guide-registre__label">RAMI</span>
                    Agressions, menaces, incivilites<br>
                    <small>Harcement, violence verbale/physique</small>
                </div>
                <div class="guide-registre guide-registre--dgi">
                    <span class="guide-registre__label">DGI</span>
                    Danger grave et imminent<br>
                    <small>Urgence, droit de retrait</small>
                </div>
            </div>
            <img src="screenshots/guide-etape1.png" alt="Page d'accueil avec les 3 registres" class="guide-step__img" width="340">
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
            <ul style="margin: 0 0 10px 20px; font-size: 15px;">
                <li><strong>Date de l'evenement</strong> — la date ou cela s'est passe</li>
                <li><strong>Objet</strong> — un resume court en quelques mots</li>
                <li><strong>Description</strong> — expliquez ce qui s'est passe en detail</li>
            </ul>
            <p class="guide-step__text">
                Vous pouvez aussi ajouter une <strong>piece jointe</strong> (photo ou PDF) si vous avez un document a joindre.
            </p>
            <img src="screenshots/guide-etape2.png" alt="Formulaire de creation de signalement" class="guide-step__img" width="340">
            <div class="guide-step__highlight">
                <strong>Conseil :</strong> ecrivez comme vous parlez, dans vos propres mots.
                Il n'y a pas de bonne ou mauvaise facon de decrire un evenement.
            </div>
        </div>
    </div>

    <!-- Step 3 -->
    <div class="guide-step">
        <div class="guide-step__number">3</div>
        <div class="guide-step__content">
            <h2 class="guide-step__title">Envoyez et c'est fait !</h2>
            <p class="guide-step__text">
                Cliquez sur <strong>« Envoyer le signalement »</strong>.
                Un bandeau vert confirme que votre signalement a bien ete enregistre.
                Il porte un numero de reference (ex: RSST-2026-0001).
            </p>
            <p class="guide-step__text">
                Votre <strong>superviseur</strong> est automatiquement prevenu et va prendre en charge votre signalement.
                Vous pouvez suivre son etat a tout moment depuis la liste des signalements.
            </p>
            <img src="screenshots/guide-etape3.png" alt="Confirmation d'enregistrement du signalement" class="guide-step__img" width="340">
            <div class="guide-step__highlight">
                <strong>Et apres ?</strong> Votre signalement passe par 3 etats :
                <span style="color:#2E5C8A;font-weight:600;">Nouveau</span>
                &rarr;
                <span style="color:#E67E22;font-weight:600;">En cours</span>
                &rarr;
                <span style="color:#27AE60;font-weight:600;">Traite</span>
            </div>
        </div>
    </div>

    <div class="guide-footer">
        Application SST — DREETS Bourgogne-Franche-Comte | 
        Besoin d'aide ? Contactez votre superviseur ou consultez la documentation dans l'application.
    </div>
</div>
