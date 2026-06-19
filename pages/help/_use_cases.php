<!-- 6. Cas d'usage -->
<div id="cas-usage" class="card card--spaced content-section">
    <h2>Cas d'usage</h2>
    <p class="help-description">Exemples concrets selon votre profil et votre situation.</p>

    <!-- CU1 : Agent signale RSST -->
    <div id="cu1" class="help-profile-card card--spaced">
        <h3>CU1 — Un agent signale un événement dans le registre RSST</h3>
        <p class="text-small text-muted help-case-label">Profil : Agent &bull; Registre : RSST</p>
        <div class="help-feature-list help-case-body">
            <strong>🎯 Situation :</strong> Jean constate que la rampe d'escalier du 2e étage est desserrée. Il signale ce danger dans le registre RSST.<br><br>
            <strong>📝 Étapes :</strong>
            <ol>
                <li>🔑 Jean se connecte (automatiquement avec son compte Windows)</li>
                <li>🖱️ Il clique sur <strong>« Signaler un événement »</strong> sur la carte RSST (carte bleue)</li>
                <li>✏️ Il remplit le formulaire :
                    <ul class="help-feature-list">
                        <li><strong>Objet</strong> : « Rampe d'escalier desserrée »</li>
                        <li><strong>Date</strong> : date du jour</li>
                        <li><strong>Lieu</strong> : « Bâtiment principal, 2e étage, escalier B »</li>
                        <li><strong>Description</strong> : détail de la situation</li>
                    </ul>
                </li>
                <li>✅ Il valide &rarr; signalement créé <span class="badge badge--nouveau">Nouveau</span> (référence <code>rsst-25-001</code>)</li>
                <li>📧 Un e-mail prévient les superviseurs du site</li>
                <li>👀 Jean suit son signalement dans la liste RSST</li>
            </ol>
            <?php echo helpScreenshot($screenshotBase . '/cu1-accueil.html', "Page d'accueil de l'agent avec les cartes de registre"); ?>
            <?php echo helpScreenshot($screenshotBase . '/cu2-creation-rsst.html', "Formulaire de création d'un signalement RSST"); ?>
        </div>
    </div>

    <!-- CU2 : RAMI pour un tiers -->
    <?php if ($ramiEnabled): ?>
    <div id="cu2" class="help-profile-card card--spaced">
        <h3>CU2 — Signalement RAMI pour le compte d'un collègue</h3>
        <p class="text-small text-muted help-case-label">Profil : Agent &bull; Registre : RAMI</p>
        <div class="help-feature-list help-case-body">
            <strong>🎯 Situation :</strong> Sophie est témoin d'une agression verbale envers son collègue Pierre. Pierre est trop choqué pour signaler. Sophie le fait pour lui.<br><br>
            <strong>📝 Étapes :</strong>
            <ol>
                <li>🖱️ Sophie clique sur <strong>« Déposer un signalement »</strong> sur la carte RAMI (carte grise)</li>
                <li>🤝 Elle sélectionne Pierre dans le champ <strong>« Pour le compte de »</strong></li>
                <li>🏷️ Elle indique la nature de l'auteur (ex : Usager) et le type d'acte (ex : Verbal)</li>
                <li>✏️ Elle décrit les faits avec date, heure et lieu</li>
                <li>✅ Le signalement est enregistré — Sophie est déclarante, Pierre est « pour le compte de »</li>
            </ol>
            <p class="help-note help-note--green">
                <strong>💬 Après ?</strong> Votre signalement est envoyé aux superviseurs par e-mail. Un superviseur le prend en charge, puis le passe à « En cours » puis « Traité » avec une réponse. Vous suivez l'avancement dans la liste.
            </p>
            <?php echo helpScreenshot($screenshotBase . '/cu3-creation-rami.html', 'Formulaire RAMI avec le champ « Pour le compte de » et les listes déroulantes nature_auteur et type_acte'); ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- CU3 : DGI urgence -->
    <?php if ($dgiEnabled): ?>
    <div id="cu3" class="help-profile-card card--spaced">
        <h3>CU3 — Signalement d'un Danger Grave et Imminent (DGI)</h3>
        <p class="text-small text-muted help-case-label">Profil : Agent &bull; Registre : DGI</p>
        <div class="help-feature-list help-case-body">
            <strong>🎯 Situation :</strong> Marc découvre une fuite de gaz. Danger immédiat pour tous les occupants du bâtiment.<br><br>
            <strong>📝 Étapes :</strong>
            <ol>
                <li>🖱️ Marc clique sur <strong>« Déposer un signalement »</strong> sur la carte DGI (carte rouge)</li>
                <li>⚠️ Un bandeau rappelle la procédure d'urgence DGI</li>
                <li>✏️ Il décrit le danger : nature, lieu exact et heure</li>
                <li>⚡ Le signalement est créé (<code>dgi-26-001</code>) et les superviseurs sont <strong>prévenus immédiatement</strong></li>
                <li>🔴 Le traitement est <strong>prioritaire</strong> — réponse dans les plus brefs délais</li>
            </ol>
            <?php echo helpScreenshot($screenshotBase . '/cu4-creation-dgi.html', "Formulaire DGI avec le bandeau d'avertissement sur la procédure prioritaire"); ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- CU4 : Superviseur traite -->
    <div id="cu4" class="help-profile-card card--spaced">
        <h3>CU4 — Un superviseur traite un signalement</h3>
        <p class="text-small text-muted help-case-label">Profil : Superviseur &bull; Tous registres</p>
        <div class="help-feature-list help-case-body">
            <strong>🎯 Situation :</strong> Claire, superviseure, reçoit le signalement de Jean (rampe desserrée). Elle doit répondre et faire avancer le signalement.<br><br>
            <strong>📝 Étapes :</strong>
            <ol>
                <li>📧 Claire ouvre le signalement depuis la <strong>liste RSST</strong> ou le lien dans l'e-mail</li>
                <li>👀 Elle lit le détail (objet, description, lieu, déclarant)</li>
                <li>🖱️ Elle clique sur <strong>« Répondre »</strong></li>
                <li>💬 Elle passe le statut à <span class="badge badge--en-cours">En cours</span> : « Mission demandée au service technique »</li>
                <li>✅ Quelques jours plus tard, elle passe à <span class="badge badge--traite">Traité</span> : « Rampe réparée le 12/06. Contrôle effectué. »</li>
                <li>👀 Jean voit la réponse dans son signalement</li>
            </ol>
            <?php echo helpScreenshot($screenshotBase . '/consultation-voir-rsst.html', "Vue détaillée d'un signalement RSST en cours de traitement"); ?>
            <?php echo helpScreenshot($screenshotBase . '/cu4-repondre-signalement.html', 'Formulaire de réponse du superviseur avec changement de statut En cours ou Traité'); ?>
            <?php echo helpScreenshot($screenshotBase . '/cu4-modifier-signalement.html', "Formulaire de modification d'un signalement"); ?>
        </div>
    </div>

    <!-- CU5 : Superviseur abandonne -->
    <div id="cu5" class="help-profile-card card--spaced">
        <h3>CU5 — Un superviseur abandonne un signalement</h3>
        <p class="text-small text-muted help-case-label">Profil : Superviseur</p>
        <div class="help-feature-list help-case-body">
            <strong>🎯 Situation :</strong> Un signalement est un doublon, une erreur ou hors sujet. Le superviseur l'abandonne plutôt que de le traiter.<br><br>
            <strong>📝 Étapes :</strong>
            <ol>
                <li>👀 Le superviseur consulte le signalement</li>
                <li>🖱️ Il clique sur <strong>« Abandonner »</strong></li>
                <li>✏️ Il saisit un motif (ex : « Doublon du signalement rsst-25-003 »)</li>
                <li>🚫 Le statut passe à <span class="badge badge--abandonne">Abandonné</span></li>
                <li>👀 Le signalement reste visible, marqué comme abandonné avec le motif</li>
            </ol>
            <?php echo helpScreenshot($screenshotBase . '/cu5-liste-signalements-sup.html', 'Liste des signalements vue par le superviseur avec les actions Répondre et Abandonner'); ?>
            <?php echo helpScreenshot($screenshotBase . '/cu1-accueil-superviseur.html', "Page d'accueil du superviseur avec accès à tous les registres et sites"); ?>
        </div>
    </div>

    <!-- CU6 : CHSCT consulte -->
    <div id="cu6" class="help-profile-card card--spaced">
        <h3>CU6 — Un <?php echo e(getRoleLabelShort('chsct')); ?> consulte les signalements</h3>
        <p class="text-small text-muted help-case-label">Profil : <?php echo e(getRoleLabel('chsct')); ?></p>
        <div class="help-feature-list help-case-body">
            <strong>🎯 Situation :</strong> Philippe, <?php echo e(getRoleLabelShort('chsct')); ?>, veut voir l'activité des <?php echo $registryCount; ?> registres sur tous les sites pour préparer la réunion trimestrielle.<br><br>
            <strong>📝 Étapes :</strong>
            <ol>
                <li>📊 Il ouvre la <strong>Synthèse</strong> pour voir les signalements par registre, site et état</li>
                <li>📈 Il consulte les <strong>Statistiques</strong> (évolution mensuelle, répartition, types d'actes)</li>
                <li>📥 Il <strong>exporte</strong> les données en fichier tableur pour les analyser dans Excel</li>
                <li>👀 Il peut consulter n'importe quel signalement sur <strong>tous les sites</strong>, même les confidentiels (consultation enregistrée)</li>
            </ol>
            <p class="help-warning-callout">
                👁️ Le <?php echo e(getRoleLabelShort('chsct')); ?> <strong>ne peut pas répondre</strong> aux signalements ni gérer les utilisateurs. Pour faire traiter un signalement, demandez à un superviseur.
            </p>
            <?php echo helpScreenshot($screenshotBase . '/cu7-synthese.html', 'Page de synthèse montrant le nombre de signalements par registre, par site et par état'); ?>
            <?php echo helpScreenshot($screenshotBase . '/cu6-statistiques.html', "Page des statistiques avec graphiques d'évolution et répartition"); ?>
        </div>
    </div>

    <!-- CU7 : Superviseur gère utilisateurs -->
    <div id="cu7" class="help-profile-card card--spaced">
        <h3>CU7 — Un superviseur gère les utilisateurs et la configuration</h3>
        <p class="text-small text-muted help-case-label">Profil : Superviseur uniquement</p>
        <div class="help-feature-list help-case-body">
            <strong>🎯 Situation :</strong> Un nouvel agent arrive. Il doit pouvoir utiliser l'application. Le superviseur configure aussi l'envoi d'e-mails et les notifications.<br><br>
            <strong>👥 Gestion des utilisateurs :</strong>
            <ol>
                <li>🔑 L'agent se connecte &rarr; son compte est <strong>créé automatiquement</strong> avec le rôle Agent</li>
                <li>🏢 Il choisit son <?php echo $labelUnite; ?> dans la page <strong>« Choisir mon site »</strong> (choix définitif, seul un superviseur peut le changer)</li>
                <li>✏️ Le superviseur peut modifier le site ou le rôle dans <strong>Utilisateurs</strong></li>
                <li>🚫 Il peut <strong>désactiver</strong> un compte d'agent parti (le compte reste pour l'historique)</li>
            </ol>
            <p class="help-feature-list card--spaced-top"><strong>⚙️ Configuration initiale :</strong></p>
            <ol>
                <li>📦 <strong>Paramètres &rarr; Application</strong> : nom de l'organisation et libellé des unités</li>
                <li>🔑 <strong>Paramètres &rarr; Application</strong> : liste des identifiants superviseurs (promus automatiquement à la première connexion)</li>
                <li>📧 <strong>Paramètres &rarr; Envoi d'e-mails</strong> : configuration du courriel pour les notifications</li>
                <li>🔔 <strong>Paramètres &rarr; Notifications</strong> : adresses e-mail à prévenir par site</li>
                <li>👁️ <strong>Paramètres &rarr; Application</strong> : visibilité des signalements par registre</li>
            </ol>
            <?php echo helpScreenshot($screenshotBase . '/cu10-utilisateurs.html', "Page de gestion des utilisateurs avec liste, rôles et sites d'affectation"); ?>
            <?php echo helpScreenshot($screenshotBase . '/cu9-parametres.html', "Page des paramètres : Application, envoi d'e-mails et Notifications"); ?>
            <?php echo helpScreenshot($screenshotBase . '/cu15-choix-site.html', "Page de choix du site lors de la première connexion d'un agent"); ?>
        </div>
    </div>

    <!-- CU8 : Impression -->
    <div id="cu8" class="help-profile-card card--spaced">
        <h3>CU8 — Imprimer une fiche de signalement</h3>
        <p class="text-small text-muted help-case-label">Profil : Superviseur uniquement</p>
        <div class="help-feature-list help-case-body">
            <strong>🎯 Situation :</strong> Le superviseur doit archiver une fiche papier du signalement ou l'envoyer à un partenaire (médecine du travail, inspection du travail…).<br><br>
            <strong>📝 Étapes :</strong>
            <ol>
                <li>👀 Le superviseur ouvre le signalement depuis la liste</li>
                <li>🖱️ Il clique sur <strong>« Télécharger en PDF »</strong></li>
                <li>📄 Un fichier PDF est généré automatiquement et téléchargé</li>
                <li>📋 Le PDF contient : référence, registre, objet, description, dates, déclarant, réponse et historique</li>
                <li>🖨️ Le document est prêt pour impression ou archivage</li>
            </ol>
            <p class="help-note help-note--blue">
                📄 Le document est généré automatiquement. Pas besoin de logiciel supplémentaire. Format optimisé pour impression A4.
            </p>
            <?php echo helpScreenshot($screenshotBase . '/cu8-export.html', "Page d'export des données avec sélection des filtres et fichier tableur"); ?>
        </div>
    </div>
</div>
