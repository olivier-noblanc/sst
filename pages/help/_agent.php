<?php
/** @var string $screenshotBase */
/** @var bool $dgiEnabled */
/** @var bool $ramiEnabled */
/** @var int $registryCount */
/** @var bool $hotlineEnabled */
/** @var string $hotlineNumber */
?>
<!-- AIDE SIMPLIFIÉE — Agent (Monsieur Robert) -->
<h1 class="page-title">Aide</h1>

<?php if ($hotlineEnabled): ?>
<div class="help-contact-banner" role="complementary" aria-label="Hotline">
    <span class="help-contact-banner__icon" aria-hidden="true">📞</span>
    <div class="help-contact-banner__text">
        <strong>Besoin d'aide ?</strong> Appelez la hotline au <strong class="help-hotline-number"><?php echo a($hotlineNumber); ?></strong><br>
        <small>Un conseiller vous répondra directement pour vous guider.</small>
    </div>
</div>
<?php endif; ?>

<!-- Que pouvez-vous faire ? -->
<div class="card card--spaced content-section">
    <h2>Que pouvez-vous faire ?</h2>
    <p class="help-description">En tant qu'agent, vous avez <?php echo $ramiEnabled || $dgiEnabled ? '3' : '1'; ?> actions principales :</p>

    <div class="help-profiles-grid">
        <div class="help-profile-card help-profile-card--border-primary">
            <h3>✏️ Signaler un événement</h3>
            <p>Vous avez vu un problème de sécurité<?php if ($ramiEnabled): ?>, une agression<?php endif; ?><?php if ($dgiEnabled): ?> ou un danger grave<?php endif; ?> ? Cliquez sur le bouton <strong>« Déposer un signalement »</strong> sous le registre correspondant sur la page d'accueil.</p>
            <p class="help-description"><?php echo $registryCount; ?> registre<?php echo $registryCount > 1 ? 's' : ''; ?> possible<?php echo $registryCount > 1 ? 's' : ''; ?> :</p>
            <ul class="help-feature-list">
                <li>🔵 <strong>RSST</strong> — Santé et Sécurité au Travail (ex : escalier cassé, équipement défectueux)</li>
                <?php if ($ramiEnabled): ?><li>🟡 <strong>RAMI</strong> — Agressions, Menaces, Incivilités (ex : insultes, harcèlement)</li><?php endif; ?>
                <?php if ($dgiEnabled): ?><li>🔴 <strong>DGI</strong> — Danger Grave et Imminent (ex : risque d'accident immédiat)</li><?php endif; ?>
            </ul>
        </div>

        <div class="help-profile-card help-profile-card--border-success">
            <h3>👀 Suivre vos signalements</h3>
            <p>Après avoir envoyé un signalement, vous pouvez suivre son état :</p>
            <ul class="help-feature-list">
                <li>🆕 <strong>Nouveau</strong> — Votre signalement vient d'être envoyé</li>
                <li>🔄 <strong>En cours</strong> — Votre superviseur examine le problème</li>
                <li>✅ <strong>Traité</strong> — Le problème est résolu</li>
            </ul>
            <p>Allez dans <strong>RSST</strong><?php if ($ramiEnabled): ?>, <strong>RAMI</strong><?php endif; ?><?php if ($dgiEnabled): ?> ou <strong>DGI</strong><?php endif; ?> dans le menu de gauche pour voir la liste de vos signalements.</p>
        </div>

        <div class="help-profile-card help-profile-card--border-purple">
            <h3>🔒 Confidentialité</h3>
            <p>Si vous cochez <strong>« Signalement confidentiel »</strong>, seul votre superviseur pourra voir votre signalement. Les autres agents ne le verront pas.</p>
            <p>Utile pour les signalements RAMI (agressions, harcèlement) si vous ne voulez pas que tout le monde soit au courant.</p>
        </div>
    </div>
</div>

<!-- Comment signaler ? -->
<div class="card card--spaced content-section">
    <h2>Comment signaler un événement ?</h2>
    <p class="help-description">C'est simple, cela prend environ 2 minutes :</p>

    <div class="help-callout--primary">
        <ol class="help-callout__list">
            <li><strong>Choisissez le bon registre</strong> sur la page d'accueil (RSST<?php if ($ramiEnabled): ?>, RAMI<?php endif; ?><?php if ($dgiEnabled): ?> ou DGI<?php endif; ?>)</li>
            <li><strong>Remplissez le formulaire</strong> — Les champs avec une étoile * sont obligatoires, les autres sont optionnels</li>
            <li><strong>Envoyez</strong> — Un bandeau vert confirme que c'est bien enregistré. Votre superviseur est prévenu automatiquement.</li>
        </ol>
    </div>

    <p class="help-section-intro"><strong>Voici les 3 étapes en images :</strong></p>

    <div class="help-profiles-grid help-profiles-grid--single">
        <div class="help-profile-card help-profile-card--center help-profile-card--border-primary">
            <h3 class="help-step-title">Étape 1 — Choisissez le bon registre</h3>
            <?php echo helpImg('cu1-accueil.png', "Page d'accueil avec les registres", $screenshotBase); ?>
            <p class="help-step-desc">Sur la page d'accueil, cliquez sur <strong>« Signaler un événement »</strong> sous le registre qui correspond à votre situation.</p>
        </div>

        <div class="help-profile-card help-profile-card--center help-profile-card--border-success">
            <h3 class="help-step-title">Étape 2 — Remplissez le formulaire</h3>
            <?php echo helpImg('cu2-creation-rsst.png', 'Formulaire de signalement RSST', $screenshotBase); ?>
            <p class="help-step-desc">Remplissez les champs obligatoires (date, objet, description). Vous pouvez joindre une photo si besoin.</p>
        </div>

        <div class="help-profile-card help-profile-card--center help-profile-card--border-purple">
            <h3 class="help-step-title">Étape 3 — Envoyez</h3>
            <?php echo helpImg('consultation-voir-rsst.png', 'Confirmation après envoi', $screenshotBase); ?>
            <p class="help-step-desc">Un bandeau vert confirme l'enregistrement. Votre superviseur est prévenu automatiquement.</p>
        </div>
    </div>

    <div class="help-callout--success">
        <p class="help-callout__tip"><strong>💡 Conseil :</strong> Écrivez comme vous parlez, dans vos propres mots.</p>
        <p class="help-text-muted"><em>Exemple : si vous dites « l'escalier est cassé », écrivez simplement « L'escalier du 2e étage est cassé, quelqu'un pourrait tomber. »</em></p>
    </div>
</div>

<!-- Questions fréquentes -->
<div class="card card--spaced content-section">
    <h2>Questions fréquentes</h2>

    <div class="help-faq-item">
        <p class="help-faq__question">Est-ce que mon signalement est anonyme ?</p>
        <p class="help-faq__answer">Non. Votre nom est associé au signalement. C'est obligatoire pour le suivi. Mais si vous cochez « Signalement confidentiel », seul le superviseur le verra.</p>
    </div>

    <div class="help-faq-item">
        <p class="help-faq__question">Puis-je modifier un signalement après l'avoir envoyé ?</p>
        <p class="help-faq__answer">Oui, tant qu'il est à l'état « Nouveau ». Cliquez sur « Modifier » dans la liste de vos signalements.</p>
    </div>

    <div class="help-faq-item">
        <p class="help-faq__question">Que se passe-t-il après l'envoi ?</p>
        <p class="help-faq__answer">Votre superviseur reçoit le signalement et le traite. Vous pouvez suivre l'état dans la liste : Nouveau → En cours → Traité.</p>
    </div>

    <div class="help-faq-item">
        <p class="help-faq__question">Je me suis trompé de registre, que faire ?</p>
        <p class="help-faq__answer">Contactez votre superviseur. Il pourra vous aider ou transférer le signalement dans le bon registre.</p>
    </div>

    <?php if ($ramiEnabled): ?>
    <div class="help-faq-item">
        <p class="help-faq__question">Puis-je signaler pour un collègue ?</p>
        <p class="help-faq__answer">Oui, dans le registre RAMI uniquement, cochez la case « Signalement pour le compte de quelqu'un d'autre » et indiquez le nom de la personne concernée. Cette possibilité est prévue par l'article L135-6 du CGFP qui autorise les signalements de témoins.</p>
    </div>
    <?php endif; ?>
</div>

<!-- Guide rapide -->
<div class="card card--spaced content-section">
    <h2>Guide rapide imprimable</h2>
    <p class="help-description">Vous pouvez imprimer un guide en 3 étapes avec des captures d'écran :</p>
    <a href="<?php echo url('guide'); ?>" class="btn btn--primary help-cta-btn">
        📄 Ouvrir le guide rapide
    </a>
</div>
