<h1 class="page-title">Aide</h1>

<?php if ($hotlineEnabled): ?>
<div class="help-contact-banner" role="complementary" aria-label="Hotline">
    <span class="help-contact-banner__icon" aria-hidden="true">📞</span>
    <div class="help-contact-banner__text">
        <strong>Besoin d'aide ?</strong> Appelez la hotline au <strong style="font-size:1.3em;"><?php echo e($hotlineNumber); ?></strong><br>
        <small>Un conseiller vous répondra directement pour vous guider.</small>
    </div>
</div>
<?php endif; ?>

<!-- Que pouvez-vous faire ? -->
<div class="card card--spaced content-section">
    <h2>Que pouvez-vous faire ?</h2>
    <p class="help-description">En tant qu'agent, vous avez <?php echo $ramiEnabled || $dgiEnabled ? '3' : '1'; ?> actions principales :</p>

    <div class="help-profiles-grid">
        <div class="help-profile-card" style="border-left: 5px solid var(--color-primary);">
            <h3>✏️ Signaler un événement</h3>
            <p>Vous avez vu un problème de sécurité<?php if ($ramiEnabled): ?>, une agression<?php endif; ?><?php if ($dgiEnabled): ?> ou un danger grave<?php endif; ?> ? Cliquez sur le bouton <strong>« Déposer un signalement »</strong> sous le registre correspondant sur la page d'accueil.</p>
            <p class="help-description"><?php echo $registryCount; ?> registre<?php echo $registryCount > 1 ? 's' : ''; ?> possible<?php echo $registryCount > 1 ? 's' : ''; ?> :</p>
            <ul class="help-feature-list">
                <li>🔵 <strong>RSST</strong> — Santé et Sécurité au Travail (ex : escalier cassé, équipement défectueux)</li>
                <?php if ($ramiEnabled): ?><li>🟡 <strong>RAMI</strong> — Agressions, Menaces, Incivilités (ex : insultes, harcèlement)</li><?php endif; ?>
                <?php if ($dgiEnabled): ?><li>🔴 <strong>DGI</strong> — Danger Grave et Imminent (ex : risque d'accident immédiat)</li><?php endif; ?>
            </ul>
        </div>

        <div class="help-profile-card" style="border-left: 5px solid #16a34a;">
            <h3>👀 Suivre vos signalements</h3>
            <p>Après avoir envoyé un signalement, vous pouvez suivre son état :</p>
            <ul class="help-feature-list">
                <li>🆕 <strong>Nouveau</strong> — Votre signalement vient d'être envoyé</li>
                <li>🔄 <strong>En cours</strong> — Votre superviseur examine le problème</li>
                <li>✅ <strong>Traité</strong> — Le problème est résolu</li>
            </ul>
            <p>Allez dans <strong>RSST</strong><?php if ($ramiEnabled): ?>, <strong>RAMI</strong><?php endif; ?><?php if ($dgiEnabled): ?> ou <strong>DGI</strong><?php endif; ?> dans le menu de gauche pour voir la liste de vos signalements.</p>
        </div>

        <div class="help-profile-card" style="border-left: 5px solid #8b5cf6;">
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

    <div style="background:#eff6ff;border:2px solid var(--color-primary);border-radius:10px;padding:20px 24px;margin:16px 0;">
        <ol style="margin:0;padding-left:24px;line-height:2.2;">
            <li><strong>Choisissez le bon registre</strong> sur la page d'accueil (RSST<?php if ($ramiEnabled): ?>, RAMI<?php endif; ?><?php if ($dgiEnabled): ?> ou DGI<?php endif; ?>)</li>
            <li><strong>Remplissez le formulaire</strong> — Les champs avec une étoile * sont obligatoires, les autres sont optionnels</li>
            <li><strong>Envoyez</strong> — Un bandeau vert confirme que c'est bien enregistré. Votre superviseur est prévenu automatiquement.</li>
        </ol>
    </div>

    <p style="text-align:center;margin:16px 0 8px 0;font-size:17px;"><strong>Voici les 3 étapes en images :</strong></p>

    <div class="help-profiles-grid" style="grid-template-columns: 1fr;">
        <div class="help-profile-card" style="text-align:center;border-left:5px solid var(--color-primary);">
            <h3 style="text-align:left;">Étape 1 — Choisissez le bon registre</h3>
            <?php echo helpImg('cu1-accueil.png', "Page d'accueil avec les registres", $screenshotBase); ?>
            <p style="text-align:left;color:#555;">Sur la page d'accueil, cliquez sur <strong>« Signaler un événement »</strong> sous le registre qui correspond à votre situation.</p>
        </div>

        <div class="help-profile-card" style="text-align:center;border-left:5px solid #16a34a;">
            <h3 style="text-align:left;">Étape 2 — Remplissez le formulaire</h3>
            <?php echo helpImg('cu2-creation-rsst.png', 'Formulaire de signalement RSST', $screenshotBase); ?>
            <p style="text-align:left;color:#555;">Remplissez les champs obligatoires (date, objet, description). Vous pouvez joindre une photo si besoin.</p>
        </div>

        <div class="help-profile-card" style="text-align:center;border-left:5px solid #8b5cf6;">
            <h3 style="text-align:left;">Étape 3 — Envoyez</h3>
            <?php echo helpImg('consultation-voir-rsst.png', 'Confirmation après envoi', $screenshotBase); ?>
            <p style="text-align:left;color:#555;">Un bandeau vert confirme l'enregistrement. Votre superviseur est prévenu automatiquement.</p>
        </div>
    </div>

    <div style="background:#f0fdf4;border:2px solid #16a34a;border-radius:10px;padding:16px 20px;margin:16px 0;">
        <p style="margin:0 0 8px 0;"><strong>💡 Conseil :</strong> Écrivez comme vous parlez, dans vos propres mots.</p>
        <p style="margin:0;color:#555;"><em>Exemple : si vous dites « l'escalier est cassé », écrivez simplement « L'escalier du 2e étage est cassé, quelqu'un pourrait tomber. »</em></p>
    </div>
</div>

<!-- Questions fréquentes -->
<div class="card card--spaced content-section">
    <h2>Questions fréquentes</h2>

    <div style="margin-bottom:20px;">
        <p style="font-size:17px;font-weight:600;margin:0 0 6px 0;">Est-ce que mon signalement est anonyme ?</p>
        <p style="margin:0;color:#555;">Non. Votre nom est associé au signalement. C'est obligatoire pour le suivi. Mais si vous cochez « Signalement confidentiel », seul le superviseur le verra.</p>
    </div>

    <div style="margin-bottom:20px;">
        <p style="font-size:17px;font-weight:600;margin:0 0 6px 0;">Puis-je modifier un signalement après l'avoir envoyé ?</p>
        <p style="margin:0;color:#555;">Oui, tant qu'il est à l'état « Nouveau ». Cliquez sur « Modifier » dans la liste de vos signalements.</p>
    </div>

    <div style="margin-bottom:20px;">
        <p style="font-size:17px;font-weight:600;margin:0 0 6px 0;">Que se passe-t-il après l'envoi ?</p>
        <p style="margin:0;color:#555;">Votre superviseur reçoit le signalement et le traite. Vous pouvez suivre l'état dans la liste : Nouveau → En cours → Traité.</p>
    </div>

    <div style="margin-bottom:20px;">
        <p style="font-size:17px;font-weight:600;margin:0 0 6px 0;">Je me suis trompé de registre, que faire ?</p>
        <p style="margin:0;color:#555;">Contactez votre superviseur. Il pourra vous aider ou transférer le signalement dans le bon registre.</p>
    </div>

    <?php if ($ramiEnabled): ?>
    <div>
        <p style="font-size:17px;font-weight:600;margin:0 0 6px 0;">Puis-je signaler pour un collègue ?</p>
        <p style="margin:0;color:#555;">Oui, dans le registre RAMI uniquement, cochez la case « Signalement pour le compte de quelqu'un d'autre » et indiquez le nom de la personne concernée. Cette possibilité est prévue par l'article L135-6 du CGFP qui autorise les signalements de témoins.</p>
    </div>
    <?php endif; ?>
</div>

<!-- Guide rapide -->
<div class="card card--spaced content-section">
    <h2>Guide rapide imprimable</h2>
    <p class="help-description">Vous pouvez imprimer un guide en 3 étapes avec des captures d'écran :</p>
    <a href="<?php echo url('guide'); ?>" class="btn btn--primary" style="font-size:17px;min-height:48px;display:inline-flex;gap:8px;">
        📄 Ouvrir le guide rapide
    </a>
</div>