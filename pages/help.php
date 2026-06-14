<?php
/**
 * Help / Documentation Page — Application SST DREETS BFC
 * 
 * Refonte complète de la documentation intégrée.
 * Captures d'écran HTML réelles de l'application.
 * Navigation par ancres — contenu toujours visible, rien de pliable.
 */
$pageTitle = 'Documentation';
$userRole = $_SESSION['user']['role'] ?? 'agent';
$labelUnite = e(getConfig('app_label_unite', 'UR'));
$screenshotBase = 'screenshots';
?>

<h1 class="page-title">Documentation</h1>

<!-- ============================================================ -->
<!-- Sommaire                                                      -->
<!-- ============================================================ -->
<nav class="help-toc" aria-label="Sommaire de la documentation">
    <h2 class="help-toc__title">Sommaire</h2>
    <ol class="help-toc__list">
        <li><a href="#profils">Profils utilisateurs</a></li>
        <li><a href="#droits">Tableau des droits</a></li>
        <li><a href="#confidentialite">Confidentialité des signalements</a></li>
        <li><a href="#registres">Les 3 registres</a></li>
        <li><a href="#cycle-vie">Cycle de vie d'un signalement</a></li>
        <li><a href="#cas-usage">Cas d'usage</a>
            <ol>
                <li><a href="#cu1">CU1 — Signaler un événement RSST</a></li>
                <li><a href="#cu2">CU2 — Signalement RAMI pour un collègue</a></li>
                <li><a href="#cu3">CU3 — Danger Grave et Imminent (DGI)</a></li>
                <li><a href="#cu4">CU4 — Traiter un signalement</a></li>
                <li><a href="#cu5">CU5 — Abandonner un signalement</a></li>
                <li><a href="#cu6">CU6 — Consulter la synthèse (CSA/CHSCT)</a></li>
                <li><a href="#cu7">CU7 — Gérer les utilisateurs et la configuration</a></li>
                <li><a href="#cu8">CU8 — Imprimer une fiche de signalement</a></li>
            </ol>
        </li>
        <li><a href="#auth">Authentification</a></li>
    </ol>
</nav>

<!-- ============================================================ -->
<!-- 1. Profils utilisateurs                                       -->
<!-- ============================================================ -->
<div id="profils" class="card card--spaced content-section">
    <h2>Profils utilisateurs</h2>
    <p class="help-description">L'application dispose de 3 profils avec des droits croissants. En production, le profil est attribué par un Superviseur via la gestion des utilisateurs, ou automatiquement via la liste des superviseurs configurée dans les Paramètres (utile pour une première installation). Votre profil détermine les fonctionnalités accessibles dans le menu latéral et les actions possibles sur les signalements.</p>

    <div class="help-profiles-grid">

        <!-- Agent -->
        <div class="help-profile-card">
            <h3>
                <span class="badge badge--agent badge--sm">Agent</span>
            </h3>
            <p class="help-description">Profil par défaut de tout nouvel utilisateur. L'agent peut signaler des événements et suivre les signalements de son site. À la première connexion, l'agent choisit son site (définitif, seul un superviseur peut le changer). La vue de l'agent est centrée sur son <?php echo $labelUnite; ?> : seuls les signalements de son site sont visibles, et la visibilité dépend du paramétrage choisi par le superviseur.</p>
            <ul class="help-feature-list">
                <li>Accéder à l'accueil avec les 3 cartes de registres</li>
                <li>Créer un signalement (RSST, RAMI, DGI)</li>
                <li>Consulter la liste des signalements de son site</li>
                <li>Voir le détail d'un signalement</li>
                <li>Modifier un signalement tant qu'il n'est pas traité</li>
                <li>Consulter le Préambule</li>
            </ul>
            <p class="help-note">
                La visibilité des signalements dépend du paramétrage choisi par le superviseur : confidentiel (ses signalements uniquement), choix de l'agent (public ou confidentiel au dépôt), ou public (tous les signalements du site).
            </p>
        </div>

        <!-- Superviseur -->
        <div class="help-profile-card help-profile-card--superviseur">
            <h3>
                <span class="badge badge--superviseur badge--sm">Superviseur</span>
            </h3>
            <p class="help-description">Profil d'administration. Le superviseur gère les réponses aux signalements, les utilisateurs et la configuration de l'application. Il peut également attribuer le rôle superviseur à d'autres utilisateurs. Le superviseur dispose d'une vue transversale sur tous les sites, ce qui lui permet de suivre l'ensemble des signalements et d'intervenir rapidement, y compris sur les signalements confidentiels.</p>
            <ul class="help-feature-list">
                <li><strong>Tout ce que l'Agent peut faire</strong>, plus :</li>
                <li>Répondre à un signalement (passer en « En cours » ou « Traité »)</li>
                <li>Abandonner un signalement</li>
                <li>Voir les signalements de <strong>tous les sites</strong></li>
                <li>Accéder à la <strong>Synthèse</strong> et aux <strong>Statistiques</strong></li>
                <li><strong>Exporter</strong> les données (CSV)</li>
                <li>Gérer les <strong>utilisateurs</strong> (créer, modifier, désactiver, attribuer les rôles)</li>
                <li>Configurer les <strong>paramètres</strong> (SMTP, notifications, visibilité, application)</li>
                <li>Imprimer une fiche de signalement (PDF)</li>
            </ul>
            <p class="help-note">
                Peut être attribué par un autre superviseur via la gestion des utilisateurs, ou auto-attribué via <strong>Paramètres &rarr; Logins Windows des superviseurs</strong> (utile pour une première installation).
            </p>
        </div>

        <!-- CHSCT -->
        <div class="help-profile-card help-profile-card--chsct">
            <h3>
                <span class="badge badge--chsct badge--sm">Membre CSA/CHSCT</span>
            </h3>
            <p class="help-description">Membre de la Commission Santé, Sécurité et Conditions de Travail. Accès en consultation élargie sur tous les sites pour le suivi des registres SST. Ce profil est conçu pour permettre aux représentants du personnel d'exercer leur mission de suivi sans pouvoir modifier les données ou la configuration de l'application.</p>
            <ul class="help-feature-list">
                <li><strong>Tout ce que l'Agent peut faire</strong>, plus :</li>
                <li>Voir les signalements de <strong>tous les sites</strong></li>
                <li>Accéder à la <strong>Synthèse</strong> et aux <strong>Statistiques</strong></li>
                <li><strong>Exporter</strong> les données (CSV)</li>
            </ul>
            <p class="help-note">
                Ne peut pas répondre aux signalements ni gérer les utilisateurs. Rôle de consultation uniquement.
            </p>
        </div>
    </div>

    <?php echo helpScreenshot($screenshotBase . '/cu1-accueil.html', "Page d'accueil de l'agent avec les 3 cartes de registre"); ?>
</div>

<!-- ============================================================ -->
<!-- 2. Tableau récapitulatif des droits                           -->
<!-- ============================================================ -->
<div id="droits" class="card card--spaced content-section">
    <h2>Tableau des droits</h2>
    <p class="help-description">Ce tableau récapitule les fonctionnalités accessibles selon chaque profil. Les droits sont cumulatifs : le Superviseur hérite de toutes les capacités de l'Agent, et le membre CSA/CHSCT dispose des mêmes droits de consultation que le Superviseur sans les capacités d'action.</p>
    <div class="table-wrapper">
        <table class="table table--compact help-rights-table" aria-label="Permissions par profil">
            <thead>
                <tr>
                    <th class="text-left">Fonctionnalité</th>
                    <th class="text-center">Agent</th>
                    <th class="text-center">Superviseur</th>
                    <th class="text-center">CSA/CHSCT</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Créer un signalement</td>
                    <td class="text-center">&#x2705;</td>
                    <td class="text-center">&#x2705;</td>
                    <td class="text-center">&#x2705;</td>
                </tr>
                <tr>
                    <td>Voir ses signalements</td>
                    <td class="text-center">&#x2705;</td>
                    <td class="text-center">&#x2705;</td>
                    <td class="text-center">&#x2705;</td>
                </tr>
                <tr>
                    <td>Modifier un signalement (non traité)</td>
                    <td class="text-center">&#x2705;</td>
                    <td class="text-center">&#x2705;</td>
                    <td class="text-center">&#x2705;</td>
                </tr>
                <tr>
                    <td>Voir les signalements de tous les sites</td>
                    <td class="text-center">&#x274C;</td>
                    <td class="text-center">&#x2705;</td>
                    <td class="text-center">&#x2705;</td>
                </tr>
                <tr>
                    <td>Répondre à un signalement</td>
                    <td class="text-center">&#x274C;</td>
                    <td class="text-center">&#x2705;</td>
                    <td class="text-center">&#x274C;</td>
                </tr>
                <tr>
                    <td>Abandonner un signalement</td>
                    <td class="text-center">&#x274C;</td>
                    <td class="text-center">&#x2705;</td>
                    <td class="text-center">&#x274C;</td>
                </tr>
                <tr>
                    <td>Imprimer une fiche</td>
                    <td class="text-center">&#x274C;</td>
                    <td class="text-center">&#x2705;</td>
                    <td class="text-center">&#x274C;</td>
                </tr>
                <tr>
                    <td>Synthèse des signalements</td>
                    <td class="text-center">&#x274C;</td>
                    <td class="text-center">&#x2705;</td>
                    <td class="text-center">&#x2705;</td>
                </tr>
                <tr>
                    <td>Statistiques</td>
                    <td class="text-center">&#x274C;</td>
                    <td class="text-center">&#x2705;</td>
                    <td class="text-center">&#x2705;</td>
                </tr>
                <tr>
                    <td>Exporter les données</td>
                    <td class="text-center">&#x274C;</td>
                    <td class="text-center">&#x2705;</td>
                    <td class="text-center">&#x2705;</td>
                </tr>
                <tr>
                    <td>Gérer les utilisateurs</td>
                    <td class="text-center">&#x274C;</td>
                    <td class="text-center">&#x2705;</td>
                    <td class="text-center">&#x274C;</td>
                </tr>
                <tr>
                    <td>Paramètres de l'application</td>
                    <td class="text-center">&#x274C;</td>
                    <td class="text-center">&#x2705;</td>
                    <td class="text-center">&#x274C;</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ============================================================ -->
<!-- 3. Confidentialité des signalements                           -->
<!-- ============================================================ -->
<div id="confidentialite" class="card card--spaced content-section">
    <h2>Confidentialité des signalements</h2>
    <p class="help-description">La visibilité des signalements dépend du paramétrage choisi par le superviseur dans les Paramètres de l'application. Ce paramétrage peut être défini globalement ou par registre (RSST, RAMI, DGI), ce qui permet d'adapter la confidentialité aux exigences légales de chaque registre. Par exemple, le registre RSST est par défaut public conformément au décret 82-453 art. 3-2, tandis que le registre RAMI peut légitimement rester confidentiel.</p>
    <div class="table-wrapper">
        <table class="table table--compact" aria-label="Modes de visibilité des signalements">
            <thead>
                <tr>
                    <th>Mode</th>
                    <th>Visibilité de l'agent</th>
                    <th>Détail</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Confidentiel</strong></td>
                    <td>Ses signalements uniquement</td>
                    <td>L'agent ne voit que ses propres signalements. Les autres agents ne voient rien de ses signalements, pas même le titre. C'est le mode le plus restrictif, adapté aux situations sensibles (RAMI par exemple).</td>
                </tr>
                <tr>
                    <td><strong>Choix de l'agent</strong></td>
                    <td>Dépend du choix au dépôt</td>
                    <td>L'agent choisit la visibilité de chaque signalement lors de la création. Par défaut, le signalement est confidentiel. L'agent voit les signalements publics de son <?php echo $labelUnite; ?> ainsi que ses propres signalements (même confidentiels). Ce mode offre la meilleure flexibilité.</td>
                </tr>
                <tr>
                    <td><strong>Visibilité publique</strong></td>
                    <td>Tous les signalements du site</td>
                    <td>Tous les signalements du site sont visibles par tous les agents du site. Conforme au décret 82-453 pour le registre RSST (consultable par tout agent).</td>
                </tr>
                <tr class="help-separator-row">
                    <td><span class="badge badge--superviseur">Superviseur</span></td>
                    <td>Tous les sites</td>
                    <td>Le superviseur a accès à l'ensemble des signalements, tous sites confondus, y compris les confidentiels. L'accès aux signalements confidentiels est tracé dans le journal d'audit.</td>
                </tr>
                <tr>
                    <td><span class="badge badge--chsct">CSA/CHSCT</span></td>
                    <td>Tous les sites</td>
                    <td>Le membre CSA/CHSCT peut consulter tous les signalements, y compris les confidentiels, pour l'exercice de ses missions. L'accès aux signalements confidentiels est également tracé.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ============================================================ -->
<!-- 4. Les 3 registres                                            -->
<!-- ============================================================ -->
<div id="registres" class="card card--spaced content-section">
    <h2>Les 3 registres</h2>
    <p class="help-description">L'application gère 3 registres distincts, chacun couvrant un domaine spécifique de la santé et sécurité au travail. Chaque registre possède ses propres champs, son code couleur et ses règles de visibilité par défaut. Depuis l'accueil, les 3 registres sont présentés sous forme de cartes permettant un accès direct à la création d'un signalement.</p>

    <div class="help-profiles-grid">

        <div class="help-profile-card help-profile-card--rsst">
            <h3>RSST</h3>
            <p class="help-description help-description--title">Registre de Santé et de Sécurité au Travail</p>
            <p class="help-description">Signalement de tout événement lié à la santé ou la sécurité au travail : conditions de travail dangereuses, équipements défectueux, risques professionnels, problèmes d'ergonomie, exposition à des substances nocives, etc. Ce registre est le plus courant et couvre l'ensemble des situations quotidiennes pouvant affecter la santé des agents.</p>
            <p class="help-note help-note--inline">Champ spécifique : <strong>Lieu</strong> de l'événement.</p>
            <p class="help-note help-note--inline">Visibilité par défaut : <strong>public</strong> (conforme décret 82-453 art. 3-2).</p>
        </div>

        <div class="help-profile-card help-profile-card--rami">
            <h3>RAMI</h3>
            <p class="help-description help-description--title">Registre des Actes d'Agressions, de Menaces et d'Incivilités</p>
            <p class="help-description">Signalement d'agressions physiques ou verbales, de menaces, ou d'incivilités subies par un agent dans le cadre de ses fonctions. Ce registre permet également de signaler un événement survenu à un collègue (champ « Pour le compte de »), ce qui est important lorsque la victime est dans l'incapacité de faire le signalement elle-même.</p>
            <p class="help-note help-note--inline">Champ spécifique : <strong>« Pour le compte de »</strong> (signalement pour un tiers).</p>
            <p class="help-note help-note--inline">Champ spécifique : <strong>Nature de l'auteur</strong> (usager / collègue / hiérarchie / tiers) et <strong>type d'acte</strong> (verbal / physique / moral / sexiste / autre) — optionnels mais recommandés pour les statistiques.</p>
        </div>

        <div class="help-profile-card help-profile-card--dgi">
            <h3>DGI</h3>
            <p class="help-description help-description--title">Registre de signalement d'un Danger Grave et Imminent</p>
            <p class="help-description">Signalement d'une situation de danger grave et imminent nécessitant une action immédiate. Ce registre bénéficie d'une procédure accélérée avec notification immédiate aux superviseurs. Le DGI est reservé aux situations où la vie ou l'intégrité physique des agents est menacée de manière directe et imminente.</p>
            <p class="help-note help-note--inline">Procédure : <strong>traitement prioritaire</strong>, notification immédiate.</p>
            <p class="help-note help-note--inline" style="background: #fff8e1; padding: 0.5rem; border-left: 3px solid #f0ad4e; border-radius: 3px; margin-top: 0.5rem;">
                <strong>Clarification :</strong> Le formulaire vaut notification au sens <strong>L4131-1</strong> (droit de retrait individuel). La consignation formelle au sens <strong>D4132-1</strong> reste du ressort du représentant CSA/CHSCT — deux actes distincts.
            </p>
        </div>
    </div>

    <?php echo helpScreenshot($screenshotBase . '/cu2-creation-rsst.html', "Formulaire de création d'un signalement RSST"); ?>
    <?php echo helpScreenshot($screenshotBase . '/cu3-creation-rami.html', "Formulaire de création d'un signalement RAMI avec le champ « Pour le compte de »"); ?>
    <?php echo helpScreenshot($screenshotBase . '/cu4-creation-dgi.html', "Formulaire de création d'un signalement DGI avec le bandeau d'avertissement"); ?>
</div>

<!-- ============================================================ -->
<!-- 5. Cycle de vie d'un signalement                              -->
<!-- ============================================================ -->
<div id="cycle-vie" class="card card--spaced content-section">
    <h2>Cycle de vie d'un signalement</h2>
    <p class="help-description">Chaque signalement suit un workflow en 4 états. Le passage d'un état à l'autre est tracé dans l'historique du signalement et dans le journal d'audit. L'agent qui a créé le signalement peut suivre l'évolution directement depuis la liste ou la vue détaillée de son signalement.</p>
    <div class="help-workflow">
        <span class="badge badge--nouveau">Nouveau</span>
        <span class="text-muted">&rarr;</span>
        <span class="badge badge--en-cours">En cours</span>
        <span class="text-muted">&rarr;</span>
        <span class="badge badge--traite">Traité</span>
        <span class="text-muted">ou</span>
        <span class="badge badge--abandonne">Abandonné</span>
    </div>
    <div class="table-wrapper">
        <table class="table table--compact" aria-label="Cycle de vie des signalements">
            <thead>
                <tr>
                    <th class="text-left">État</th>
                    <th class="text-left">Description</th>
                    <th class="text-left">Qui peut changer ?</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><span class="badge badge--nouveau">Nouveau</span></td>
                    <td>Signalement créé par un agent, en attente de traitement par un superviseur. Une notification e-mail est envoyée aux adresses configurées pour le site concerné.</td>
                    <td>État initial à la création</td>
                </tr>
                <tr>
                    <td><span class="badge badge--en-cours">En cours</span></td>
                    <td>Un superviseur a pris en charge le signalement et a commencé à le traiter. Il a fourni une première réponse indiquant les actions engagées.</td>
                    <td>Superviseur (via « Répondre »)</td>
                </tr>
                <tr>
                    <td><span class="badge badge--traite">Traité</span></td>
                    <td>Le signalement a été traité et une réponse finale a été apportée par le superviseur. L'agent déclarant peut consulter la réponse.</td>
                    <td>Superviseur (via « Répondre »)</td>
                </tr>
                <tr>
                    <td><span class="badge badge--abandonne">Abandonné</span></td>
                    <td>Le signalement a été abandonné avec un motif (doublon, hors périmètre, erreur de saisie, etc.). Le signalement reste visible mais n'est plus actif.</td>
                    <td>Superviseur (via « Abandonner »)</td>
                </tr>
            </tbody>
        </table>
    </div>

    <?php echo helpScreenshot($screenshotBase . '/cu5-liste-signalements.html', "Liste des signalements avec filtres et badges d'état"); ?>
    <?php echo helpScreenshot($screenshotBase . '/cu5-voir-signalement.html', "Vue détaillée d'un signalement RSST avec son historique"); ?>
</div>

<!-- ============================================================ -->
<!-- 6. Cas d'usage                                                -->
<!-- ============================================================ -->
<div id="cas-usage" class="card card--spaced content-section">
    <h2>Cas d'usage</h2>
    <p class="help-description">Scénarios concrets d'utilisation de l'application selon le profil et la situation. Chaque cas illustre le parcours complet de l'utilisateur avec des captures d'écran réelles de l'application.</p>

    <!-- CU1 : Agent signale RSST -->
    <div id="cu1" class="help-profile-card card--spaced">
        <h3>CU1 — Un agent signale un événement dans le registre RSST</h3>
        <p class="text-small text-muted help-case-label">Profil : Agent &bull; Registre : RSST</p>
        <div class="help-feature-list help-case-body">
            <strong>Situation :</strong> Jean, agent à l'<?php echo $labelUnite; ?> Côte-d'Or, constate que la rampe d'escalier du 2e étage est desserrée et présente un risque de chute. Il doit signaler cet événement dans le registre RSST pour que le service technique intervienne.<br><br>
            <strong>Parcours :</strong>
            <ol>
                <li>Jean se connecte à l'application (automatiquement via Windows Auth en production)</li>
                <li>Sur l'accueil, il clique sur <strong>« Inscrire un signalement »</strong> sur la carte RSST (carte bleue)</li>
                <li>Il remplit le formulaire :
                    <ul class="help-feature-list">
                        <li><strong>Objet</strong> : « Rampe d'escalier desserrée - risque de chute »</li>
                        <li><strong>Date de l'événement</strong> : date du jour</li>
                        <li><strong>Lieu</strong> : « Bâtiment principal, 2e étage, escalier B »</li>
                        <li><strong>Description</strong> : détail de la situation observée</li>
                    </ul>
                </li>
                <li>Il valide &rarr; le signalement est créé avec le statut <span class="badge badge--nouveau">Nouveau</span> et la référence <code>rsst-25-001</code></li>
                <li>Un e-mail de notification est envoyé aux adresses configurées pour ce site</li>
                <li>Jean peut suivre son signalement dans la liste RSST de son site</li>
            </ol>
            <?php echo helpScreenshot($screenshotBase . '/cu1-accueil.html', "Page d'accueil de l'agent avec les 3 cartes de registre"); ?>
            <?php echo helpScreenshot($screenshotBase . '/cu2-creation-rsst.html', "Formulaire de création d'un signalement RSST"); ?>
        </div>
    </div>

    <!-- CU2 : RAMI pour un tiers -->
    <div id="cu2" class="help-profile-card card--spaced">
        <h3>CU2 — Signalement RAMI pour le compte d'un collègue</h3>
        <p class="text-small text-muted help-case-label">Profil : Agent &bull; Registre : RAMI</p>
        <div class="help-feature-list help-case-body">
            <strong>Situation :</strong> Sophie, agent au Siège, est témoin d'une agression verbale envers son collègue Pierre par un usager. Pierre est trop choqué pour faire le signalement lui-même. Sophie va signaler l'événement pour le compte de Pierre.<br><br>
            <strong>Parcours :</strong>
            <ol>
                <li>Sophie clique sur <strong>« Inscrire un signalement »</strong> sur la carte RAMI (carte grise)</li>
                <li>Elle remplit le formulaire et sélectionne un agent dans le champ <strong>« Pour le compte de »</strong> (champ spécifique RAMI, avec recherche par nom)</li>
                <li>Elle renseigne les champs optionnels mais recommandés : <strong>Nature de l'auteur</strong> (ex : Usager) et <strong>Type d'acte</strong> (ex : Verbal)</li>
                <li>Elle décrit les faits de manière objective avec date, heure et lieu</li>
                <li>Le signalement est enregistré — Sophie apparaît comme déclarant, Pierre comme « pour le compte de »</li>
            </ol>
            <p class="help-note" style="background: #e8f5e9; padding: 0.5rem; border-left: 3px solid #4caf50; border-radius: 3px; margin-top: 0.5rem;">
                <strong>Que se passe-t-il après ?</strong> Votre signalement est envoyé aux superviseurs de votre site par notification e-mail. Un superviseur le prendra en charge, passera le statut à « En cours », puis à « Traité » avec une réponse. Vous pouvez suivre l'avancement dans la liste des signalements. En cas d'absence de réponse prolongée, le superviseur en sera alerté automatiquement.
            </p>
            <?php echo helpScreenshot($screenshotBase . '/cu3-creation-rami.html', "Formulaire RAMI avec le champ « Pour le compte de » et les listes déroulantes nature_auteur et type_acte"); ?>
        </div>
    </div>

    <!-- CU3 : DGI urgence -->
    <div id="cu3" class="help-profile-card card--spaced">
        <h3>CU3 — Signalement d'un Danger Grave et Imminent (DGI)</h3>
        <p class="text-small text-muted help-case-label">Profil : Agent &bull; Registre : DGI</p>
        <div class="help-feature-list help-case-body">
            <strong>Situation :</strong> Marc, agent à l'<?php echo $labelUnite; ?> Doubs, découvre une fuite de gaz dans les locaux. La situation nécessite une intervention immédiate et constitue un danger grave et imminent pour les occupants du bâtiment.<br><br>
            <strong>Parcours :</strong>
            <ol>
                <li>Marc clique sur <strong>« Inscrire un signalement »</strong> sur la carte DGI (carte rouge, signalant l'urgence)</li>
                <li>Un bandeau d'avertissement rappelle la procédure DGI et la distinction L4131-1 / D4132-1</li>
                <li>Il signale le danger en précisant la nature, le lieu exact et l'heure</li>
                <li>Le signalement est créé avec la référence <code>dgi-26-001</code> et une <strong>notification immédiate</strong> est envoyée aux superviseurs</li>
                <li>Le traitement est <strong>prioritaire</strong> — le superviseur doit répondre dans les plus brefs délais</li>
            </ol>
            <?php echo helpScreenshot($screenshotBase . '/cu4-creation-dgi.html', "Formulaire DGI avec le bandeau d'avertissement sur la procédure prioritaire"); ?>
        </div>
    </div>

    <!-- CU4 : Superviseur traite -->
    <div id="cu4" class="help-profile-card card--spaced">
        <h3>CU4 — Un superviseur traite un signalement</h3>
        <p class="text-small text-muted help-case-label">Profil : Superviseur &bull; Tous registres</p>
        <div class="help-feature-list help-case-body">
            <strong>Situation :</strong> Claire, superviseure, reçoit la notification du signalement RSST de Jean (rampe desserrée). Elle doit traiter le signalement en apportant une réponse adaptée et en faisant évoluer le statut.<br><br>
            <strong>Parcours :</strong>
            <ol>
                <li>Claire consulte la <strong>liste RSST</strong> ou le lien dans l'e-mail de notification</li>
                <li>Elle clique sur le signalement <code>rsst-25-001</code> pour voir le détail complet (objet, description, lieu, déclarant)</li>
                <li>Elle clique sur <strong>« Répondre »</strong></li>
                <li>Elle passe le statut à <span class="badge badge--en-cours">En cours</span> en indiquant : « Mission demandée au service technique pour réparation »</li>
                <li>Quelques jours plus tard, la rampe est réparée. Elle revient sur le signalement et le passe à <span class="badge badge--traite">Traité</span> avec la réponse : « Rampe réparée le 12/06/2025. Contrôle visuel effectué. »</li>
                <li>Le déclarant (Jean) peut voir la réponse dans le détail de son signalement</li>
            </ol>
            <?php echo helpScreenshot($screenshotBase . '/cu5-voir-signalement.html', "Vue détaillée d'un signalement RSST en cours de traitement"); ?>
            <?php echo helpScreenshot($screenshotBase . '/cu5-repondre-signalement.html', "Formulaire de réponse du superviseur avec changement de statut En cours ou Traité"); ?>
        </div>
    </div>

    <!-- CU5 : Superviseur abandonne -->
    <div id="cu5" class="help-profile-card card--spaced">
        <h3>CU5 — Un superviseur abandonne un signalement</h3>
        <p class="text-small text-muted help-case-label">Profil : Superviseur</p>
        <div class="help-feature-list help-case-body">
            <strong>Situation :</strong> Un signalement s'avère être un doublon, une erreur de saisie, ou ne pas relever du registre concerné. Le superviseur décide de l'abandonner plutôt que de le traiter, afin de maintenir la fiabilité des données dans les registres.<br><br>
            <strong>Parcours :</strong>
            <ol>
                <li>Le superviseur consulte le signalement</li>
                <li>Il clique sur <strong>« Abandonner »</strong></li>
                <li>Il saisit un motif d'abandon (ex : « Doublon du signalement rsst-25-003 » ou « Hors périmètre — relève du service logistique »)</li>
                <li>Le statut passe à <span class="badge badge--abandonne">Abandonné</span></li>
                <li>Le signalement reste visible dans la liste mais marqué comme abandonné avec le motif</li>
            </ol>
            <?php echo helpScreenshot($screenshotBase . '/cu5-liste-signalements-sup.html', "Liste des signalements vue par le superviseur avec les actions Répondre et Abandonner"); ?>
        </div>
    </div>

    <!-- CU6 : CHSCT consulte -->
    <div id="cu6" class="help-profile-card card--spaced">
        <h3>CU6 — Un membre CSA/CHSCT consulte les signalements</h3>
        <p class="text-small text-muted help-case-label">Profil : Membre CSA/CHSCT</p>
        <div class="help-feature-list help-case-body">
            <strong>Situation :</strong> Philippe, membre CSA/CHSCT, souhaite avoir une vue d'ensemble de l'activité des 3 registres sur l'ensemble des sites pour préparer la réunion trimestrielle de la commission. Il a besoin de données consolidées et de tendances.<br><br>
            <strong>Parcours :</strong>
            <ol>
                <li>Il accède à la <strong>Synthèse</strong> pour voir le nombre de signalements par registre, par site et par état — cette vue croisée permet d'identifier les sites les plus touchés et les registres les plus actifs</li>
                <li>Il consulte les <strong>Statistiques</strong> pour voir les tendances (évolution mensuelle, répartition par site, types d'actes pour le RAMI)</li>
                <li>Il utilise l'<strong>Export</strong> pour télécharger les données au format CSV et les analyser dans Excel pour son rapport</li>
                <li>Il peut consulter le détail de n'importe quel signalement sur <strong>tous les sites</strong>, y compris les confidentiels (l'accès est tracé)</li>
            </ol>
            <p class="help-warning-callout">
                Le membre CSA/CHSCT <strong>ne peut pas répondre</strong> aux signalements ni modifier les utilisateurs — il a un rôle de consultation uniquement. S'il souhaite qu'un signalement soit traité, il doit en faire la demande à un superviseur.
            </p>
            <?php echo helpScreenshot($screenshotBase . '/cu7-synthese.html', "Page de synthèse montrant le nombre de signalements par registre, par site et par état"); ?>
            <?php echo helpScreenshot($screenshotBase . '/cu6-statistiques.html', "Page des statistiques avec graphiques d'évolution et répartition"); ?>
        </div>
    </div>

    <!-- CU7 : Superviseur gère utilisateurs -->
    <div id="cu7" class="help-profile-card card--spaced">
        <h3>CU7 — Un superviseur gère les utilisateurs et la configuration</h3>
        <p class="text-small text-muted help-case-label">Profil : Superviseur uniquement</p>
        <div class="help-feature-list help-case-body">
            <strong>Situation :</strong> Un nouvel agent arrive à l'<?php echo $labelUnite; ?> Jura. Il doit pouvoir utiliser l'application. Par ailleurs, l'application vient d'être installée et le superviseur doit effectuer la configuration initiale (SMTP, notifications, visibilité).<br><br>
            <strong>Gestion des utilisateurs :</strong>
            <ol>
                <li><strong>En production</strong> : l'agent se connecte &rarr; son compte est <strong>automatiquement créé</strong> avec le rôle Agent et sans site attribué</li>
                <li>L'agent est alors redirigé vers la page <strong>« Choisir mon site »</strong> — il sélectionne son <?php echo $labelUnite; ?> parmi les sites actifs. Ce choix est <strong>définitif</strong> pour l'agent (seul un superviseur peut le modifier ensuite)</li>
                <li>Le superviseur peut ensuite modifier le site ou le rôle dans <strong>Utilisateurs</strong> si nécessaire</li>
                <li>Il peut aussi <strong>désactiver</strong> un compte d'agent qui a quitté la structure (le compte reste en base pour l'historique mais n'est plus accessible)</li>
            </ol>
            <p class="help-feature-list card--spaced-top"><strong>Configuration initiale :</strong></p>
            <ol>
                <li>Dans <strong>Paramètres &rarr; Application</strong>, configurer le nom de l'organisation et le libellé des unités (UR, UD...)</li>
                <li>Dans <strong>Paramètres &rarr; Application</strong>, compléter la <strong>liste des logins superviseurs</strong> — ces utilisateurs seront automatiquement promus Superviseur lors de leur première connexion via IIS (utile pour une première installation)</li>
                <li>Dans <strong>Paramètres &rarr; SMTP</strong>, configurer le serveur d'envoi d'e-mails pour les notifications</li>
                <li>Dans <strong>Paramètres &rarr; Notifications</strong>, ajouter les adresses e-mail à notifier par site et/ou globalement</li>
                <li>Dans <strong>Paramètres &rarr; Application</strong>, ajuster la visibilité des signalements par registre (confidentiel, choix de l'agent, public)</li>
            </ol>
            <?php echo helpScreenshot($screenshotBase . '/cu10-utilisateurs.html', "Page de gestion des utilisateurs avec liste, rôles et sites d'affectation"); ?>
            <?php echo helpScreenshot($screenshotBase . '/cu9-parametres.html', "Page des paramètres avec les onglets Application, SMTP et Notifications"); ?>
            <?php echo helpScreenshot($screenshotBase . '/cu15-choix-site.html', "Page de choix du site lors de la première connexion d'un agent"); ?>
        </div>
    </div>

    <!-- CU8 : Impression -->
    <div id="cu8" class="help-profile-card card--spaced">
        <h3>CU8 — Imprimer une fiche de signalement</h3>
        <p class="text-small text-muted help-case-label">Profil : Superviseur uniquement</p>
        <div class="help-feature-list help-case-body">
            <strong>Situation :</strong> Le superviseur doit archiver une fiche papier du signalement ou la transmettre à un service partenaire (médecine du travail, inspection du travail, etc.). L'application génère un PDF professionnel reprenant l'ensemble des informations du signalement.<br><br>
            <strong>Parcours :</strong>
            <ol>
                <li>Le superviseur ouvre le signalement depuis la liste</li>
                <li>Il clique sur <strong>« Télécharger en PDF »</strong></li>
                <li>Un fichier PDF est généré automatiquement par le serveur et téléchargé par le navigateur</li>
                <li>Le PDF contient : la référence, le type de registre, l'objet, la description, les dates, le déclarant, la réponse du superviseur et l'historique des réponses</li>
                <li>Le document est prêt pour impression ou archivage numérique</li>
            </ol>
            <p class="help-note" style="background: #e3f2fd; padding: 0.5rem; border-left: 3px solid #1976d2; border-radius: 3px; margin-top: 0.5rem;">
                <strong>Format :</strong> Le PDF est généré côté serveur via FPDF. Il ne nécessite aucun plugin ni JavaScript. Le format est optimisé pour l'impression A4.
            </p>
            <?php echo helpScreenshot($screenshotBase . '/cu8-export.html', "Page d'export des données avec sélection des filtres et format CSV"); ?>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- 7. Authentification                                           -->
<!-- ============================================================ -->
<div id="auth" class="card card--spaced content-section">
    <h2>Authentification</h2>
    <p class="help-description">L'authentification fonctionne différemment selon l'environnement. En production, l'authentification est transparente grâce à Windows Authentication. En développement, un formulaire permet de tester les différents profils.</p>
    <div class="help-profiles-grid">
        <div class="help-auth-card--prod">
            <h4>Production (IIS)</h4>
            <p class="help-description">
                L'authentification est gérée par <strong>IIS Windows Authentication</strong>. 
                L'utilisateur est automatiquement authentifié via son compte Windows Active Directory.
                Aucun formulaire de login n'est affiché. Son compte est créé automatiquement à la première connexion avec le rôle Agent par défaut.
                <br><br>
                <strong>Promotion automatique :</strong> si le login Windows figure dans la liste configurée dans
                <strong>Paramètres &rarr; Application &rarr; Logins Windows des superviseurs</strong>,
                l'utilisateur est automatiquement promu Superviseur. Cette promotion prend effet immédiatement, sans nécessiter de déconnexion/reconnexion.
            </p>
        </div>
        <div class="help-auth-card--dev">
            <h4>Développement</h4>
            <p class="help-description">
                En mode développement, un <strong>formulaire de connexion mock</strong> permet de tester les différents profils sans infrastructure Windows.
                Les comptes de test sont : <code>admin.dev</code> (superviseur), <code>agent.dev</code> (agent), <code>chsct.dev</code> (CSA/CHSCT).
                <br><br>
                <strong>Mode développement automatique :</strong> si le serveur ne détecte pas <code>AUTH_USER</code> (variable IIS), l'application bascule automatiquement en mode développement. Ceci est normal sur les serveurs Apache, Caddy, Docker ou tout environnement non-IIS.
            </p>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- Liens utiles                                                  -->
<!-- ============================================================ -->
<div class="card--spaced-top" style="display: flex; gap: 1rem; flex-wrap: wrap;">
    <a href="<?php echo url('preamble'); ?>" class="btn btn--outline">Lire le Préambule</a>
    <a href="<?php echo url('changelog'); ?>" class="btn btn--outline">Journal des modifications</a>
</div>

<?php
/**
 * Generate a visible screenshot block (no collapsible details).
 * Renders the iframe directly — always visible, never folded.
 */
function helpScreenshot(string $src, string $alt): string {
    $id = 'ss-' . substr(md5($src), 0, 8);
    return <<<HTML
    <div class="help-screenshot-block" id="{$id}">
        <p class="help-screenshot-label">{$alt}</p>
        <div class="help-screenshot-wrapper">
            <iframe src="{$src}" class="help-screenshot-iframe" title="{$alt}" loading="lazy" sandbox="allow-same-origin"></iframe>
        </div>
    </div>
    HTML;
}
?>
