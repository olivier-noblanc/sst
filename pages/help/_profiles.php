<!-- 1. Profils utilisateurs -->
<div id="profils" class="card card--spaced content-section">
    <h2>Profils utilisateurs</h2>
    <p class="help-description">L'application propose <strong>3 profils</strong>. Votre profil définit ce que vous pouvez faire :</p>

    <div class="help-profiles-grid">

        <!-- Agent -->
        <div class="help-profile-card">
            <h3>
                <span class="badge badge--agent badge--sm">Agent</span>
            </h3>
            <p class="help-description">Profil par défaut. Signalez des événements et suivez vos signalements. À la première connexion, vous choisissez votre site (définitif).</p>
            <ul class="help-feature-list">
                <li>🏠 Accéder à l'accueil avec les <?php echo $registryCount; ?> cartes de registres</li>
                <li>✏️ Créer un signalement (RSST<?php if ($ramiEnabled): ?>, RAMI<?php endif; ?><?php if ($dgiEnabled): ?>, DGI<?php endif; ?>)</li>
                <li>📋 Consulter la liste des signalements de son site</li>
                <li>🔍 Voir le détail d'un signalement</li>
                <li>✏️ Modifier un signalement tant qu'il n'est pas traité</li>
                <li>📖 Consulter le Préambule</li>
            </ul>
            <p class="help-note">
                👁️ Visibilité des signalements selon le choix du superviseur : confidentiel (vos signalements uniquement), choix de l'agent, ou public (tous les signalements du site).
            </p>
        </div>

        <!-- Superviseur -->
        <div class="help-profile-card help-profile-card--superviseur">
            <h3>
                <span class="badge badge--superviseur badge--sm">Superviseur</span>
            </h3>
            <p class="help-description">Vous gérez les réponses, les utilisateurs et la configuration. Vous voyez tous les signalements de tous les sites.</p>
            <ul class="help-feature-list">
                <li>✅ <strong>Tout ce que l'Agent peut faire</strong>, plus :</li>
                <li>💬 Répondre à un signalement (passer en « En cours » ou « Traité »)</li>
                <li>🚫 Abandonner un signalement</li>
                <li>👁️ Voir les signalements de <strong>tous les sites</strong></li>
                <li>📊 Accéder à la <strong>Synthèse</strong> et aux <strong>Statistiques</strong></li>
                <li>📥 <strong>Exporter</strong> les données (fichier tableur pour Excel)</li>
                <li>👥 Gérer les <strong>utilisateurs</strong> (créer, modifier, désactiver)</li>
                <li>⚙️ Configurer les <strong>paramètres</strong> (envoi d'e-mails, notifications, visibilité)</li>
                <li>🖨️ Imprimer une fiche de signalement (document imprimable)</li>
            </ul>
            <p class="help-note">
                🔑 Attribué par un autre superviseur via la gestion des utilisateurs, ou via <strong>Paramètres &rarr; Identifiants Windows des superviseurs</strong> (pour une première installation).
            </p>
        </div>

        <!-- CHSCT -->
        <div class="help-profile-card help-profile-card--chsct">
            <h3>
                <span class="badge badge--chsct badge--sm"><?php echo e(getRoleLabel('chsct')); ?></span>
            </h3>
            <p class="help-description">Membre de la Formation Spécialisée du CSA. Consultez les signalements pour lesquels le déclarant a donné son consentement, sans modifier les données.</p>
            <ul class="help-feature-list">
                <li>✅ <strong>Tout ce que l'Agent peut faire</strong>, plus :</li>
                <li>👁️ Voir les signalements <strong>pour lesquels le consentement est donné</strong></li>
            </ul>
            <p class="help-note">
                👁️ Rôle de consultation uniquement — pas de réponse aux signalements, pas d'accès aux statistiques, ni de gestion des utilisateurs.
            </p>
        </div>
    </div>

    <?php echo helpScreenshot($screenshotBase . '/cu1-accueil.html', "Page d'accueil de l'agent avec les cartes de registre"); ?>
</div>
