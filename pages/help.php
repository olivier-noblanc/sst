<?php
/**
 * Help / Documentation Page — Application SST DREETS BFC
 * 
 * Documents the 3 user profiles, their permissions,
 * and the 3 registries with their specific features.
 * Conformant to the DIRECCTE SST reference documentation.
 */
$pageTitle = 'Documentation';
$userRole = $_SESSION['user']['role'] ?? 'agent';
?>

<h1 class="page-title"><span aria-hidden="true">&#x1F4DA;</span> Documentation</h1>

<!-- ============================================================ -->
<!-- Profils utilisateurs                                          -->
<!-- ============================================================ -->
<div class="card card--spaced content-section">
    <h2>&#x1F465; Profils utilisateurs</h2>
    <p class="help-description">L'application dispose de 3 profils avec des droits croissants. En production, le profil est attribué par un Superviseur via la gestion des utilisateurs, ou automatiquement via la liste des superviseurs configurée dans les Paramètres (utile pour une première installation).</p>

    <div class="help-profiles-grid">

        <!-- Agent -->
        <div class="help-profile-card">
            <h3>
                <span class="badge badge--agent badge--sm">Agent</span>
            </h3>
            <p class="help-description">Profil par défaut de tout nouvel utilisateur. L'agent peut signaler des événements et suivre les signalements de son site. À la première connexion, l'agent choisit son site (définitif, seul un superviseur peut le changer).</p>
            <ul class="help-feature-list">
                <li>Accéder à l'accueil</li>
                <li>Créer un signalement (RSST, RAMI, DGI)</li>
                <li>Consulter la liste des signalements de son site</li>
                <li>Voir le détail d'un signalement</li>
                <li>Modifier un signalement tant qu'il n'est pas traité</li>
                <li>Consulter le Préambule</li>
            </ul>
            <p class="help-note">
                &#x1F512; La visibilité des signalements dépend du paramétrage choisi par le superviseur : confidentiel (ses signalements uniquement), choix de l'agent (public ou confidentiel au dépôt), ou public (tous les signalements du site).
            </p>
        </div>

        <!-- Superviseur -->
        <div class="help-profile-card help-profile-card--superviseur">
            <h3>
                <span class="badge badge--superviseur badge--sm">Superviseur</span>
            </h3>
            <p class="help-description">Profil d'administration. Le superviseur gère les réponses aux signalements, les utilisateurs et la configuration de l'application. Il peut également attribuer le rôle superviseur à d'autres utilisateurs.</p>
            <ul class="help-feature-list">
                <li><strong>Tout ce que l'Agent peut faire</strong>, plus :</li>
                <li>Répondre à un signalement (passer en « En cours » ou « Traité »)</li>
                <li>Abandonner un signalement</li>
                <li>Voir les signalements de <strong>tous les sites</strong></li>
                <li>Accéder à la <strong>Synthèse</strong></li>
                <li>Accéder aux <strong>Statistiques</strong></li>
                <li><strong>Exporter</strong> les données</li>
                <li>Gérer les <strong>utilisateurs</strong> (créer, modifier, désactiver, attribuer les rôles)</li>
                <li>Configurer les <strong>paramètres</strong> (SMTP, notifications, application)</li>
                <li>Imprimer une fiche de signalement</li>
            </ul>
            <p class="help-note">
                &#x1F511; Peut être attribué par un autre superviseur via la gestion des utilisateurs, ou auto-attribué via <strong>Paramètres &rarr; Logins Windows des superviseurs</strong> (utile pour une première installation).
            </p>
        </div>

        <!-- CHSCT -->
        <div class="help-profile-card help-profile-card--chsct">
            <h3>
                <span class="badge badge--chsct badge--sm">Membre CHSCT</span>
            </h3>
            <p class="help-description">Membre de la Commission Hygiène, Sécurité et Conditions de Travail. Accès en consultation élargie sur tous les sites pour le suivi des registres SST.</p>
            <ul class="help-feature-list">
                <li><strong>Tout ce que l'Agent peut faire</strong>, plus :</li>
                <li>Voir les signalements de <strong>tous les sites</strong></li>
                <li>Accéder à la <strong>Synthèse</strong></li>
                <li>Accéder aux <strong>Statistiques</strong></li>
                <li><strong>Exporter</strong> les données</li>
            </ul>
            <p class="help-note">
                &#x26A0; Ne peut pas répondre aux signalements ni gérer les utilisateurs. Rôle de consultation uniquement.
            </p>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- Tableau récapitulatif des droits                              -->
<!-- ============================================================ -->
<div class="card card--spaced content-section">
    <h2>&#x1F512; Tableau des droits</h2>
    <div class="table-wrapper">
        <table class="table table--compact help-rights-table" aria-label="Permissions par profil">
            <thead>
                <tr>
                    <th class="text-left">Fonctionnalité</th>
                    <th class="text-center">Agent</th>
                    <th class="text-center">Superviseur</th>
                    <th class="text-center">CHSCT</th>
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
<!-- Confidentialité des signalements                              -->
<!-- ============================================================ -->
<div class="card card--spaced content-section">
    <h2>&#x1F510; Confidentialité des signalements</h2>
    <p class="help-description">La visibilité des signalements dépend du paramétrage choisi par le superviseur dans les Paramètres de l'application :</p>
    <div class="table-wrapper">
        <table class="table table--compact" aria-label="Caractéristiques des registres">
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
                    <td>L'agent ne voit que ses propres signalements. Les autres agents ne voient rien de ses signalements, pas même le titre. C'est le mode le plus restrictif.</td>
                </tr>
                <tr>
                    <td><strong>Choix de l'agent</strong></td>
                    <td>Dépend du choix au dépôt</td>
                    <td>L'agent choisit la visibilité de chaque signalement lors de la création. Par défaut, le signalement est confidentiel. L'agent voit les signalements publics de son <?php echo e(getConfig('app_label_unite', 'UR')); ?> ainsi que ses propres signalements (même confidentiels).</td>
                </tr>
                <tr>
                    <td><strong>Visibilité publique</strong></td>
                    <td>Tous les signalements du site</td>
                    <td>Tous les signalements du site sont visibles par tous les agents du site.</td>
                </tr>
                <tr class="help-separator-row">
                    <td><span class="badge badge--superviseur">Superviseur</span></td>
                    <td>Tous les sites</td>
                    <td>Le superviseur a accès à l'ensemble des signalements, tous sites confondus, y compris les confidentiels.</td>
                </tr>
                <tr>
                    <td><span class="badge badge--chsct">CHSCT</span></td>
                    <td>Tous les sites</td>
                    <td>Le membre CHSCT peut consulter tous les signalements, y compris les confidentiels, pour l'exercice de ses missions.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ============================================================ -->
<!-- Les 3 registres                                               -->
<!-- ============================================================ -->
<div class="card card--spaced content-section">
    <h2>&#x1F4CB; Les 3 registres</h2>

    <div class="help-profiles-grid">

        <div class="help-profile-card help-profile-card--rsst">
            <h3>&#x1F4CB; RSST</h3>
            <p class="help-description help-description--title">Registre de Santé et de Sécurité au Travail</p>
            <p class="help-description">Signalement de tout événement lié à la santé ou la sécurité au travail : conditions de travail dangereuses, équipements défectueux, risques professionnels, etc.</p>
            <p class="help-note help-note--inline">Champ spécifique : <strong>Lieu</strong> de l'événement.</p>
        </div>

        <div class="help-profile-card help-profile-card--rami">
            <h3>&#x26A0;&#xFE0F; RAMI</h3>
            <p class="help-description help-description--title">Registre des Actes d'Agressions, de Menaces et d'Incivilités</p>
            <p class="help-description">Signalement d'agressions physiques ou verbales, de menaces, ou d'incivilités subies par un agent dans le cadre de ses fonctions.</p>
            <p class="help-note help-note--inline">Champ spécifique : <strong>« Pour le compte de »</strong> (signalement pour un tiers).</p>
        </div>

        <div class="help-profile-card help-profile-card--dgi">
            <h3>&#x1F534; DGI</h3>
            <p class="help-description help-description--title">Registre de signalement d'un Danger Grave et Imminent</p>
            <p class="help-description">Signalement d'une situation de danger grave et imminent nécessitant une action immédiate. Ce registre bénéficie d'une procédure accélérée.</p>
            <p class="help-note help-note--inline">Procédure : <strong>traitement prioritaire</strong>, notification immédiate.</p>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- Cycle de vie d'un signalement                                 -->
<!-- ============================================================ -->
<div class="card card--spaced content-section">
    <h2>&#x1F504; Cycle de vie d'un signalement</h2>
    <p class="help-description">Chaque signalement suit un workflow en 4 états :</p>
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
                    <td>Signalement créé par un agent, en attente de traitement</td>
                    <td>État initial à la création</td>
                </tr>
                <tr>
                    <td><span class="badge badge--en-cours">En cours</span></td>
                    <td>Un superviseur a pris en charge le signalement</td>
                    <td>Superviseur (via « Répondre »)</td>
                </tr>
                <tr>
                    <td><span class="badge badge--traite">Traité</span></td>
                    <td>Le signalement a été traité et une réponse a été apportée</td>
                    <td>Superviseur (via « Répondre »)</td>
                </tr>
                <tr>
                    <td><span class="badge badge--abandonne">Abandonné</span></td>
                    <td>Le signalement a été abandonné (hors délai, doublon, etc.)</td>
                    <td>Superviseur (via « Abandonner »)</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ============================================================ -->
<!-- Cas d'usage                                                   -->
<!-- ============================================================ -->
<div class="card card--spaced content-section">
    <h2>&#x1F3AF; Cas d'usage</h2>
    <p class="help-description">Scénarios concrets d'utilisation de l'application selon le profil et la situation.</p>

    <!-- CU1 : Agent signale -->
    <div class="help-profile-card card--spaced">
        <h3>CU1 — Un agent signale un événement dans le registre RSST</h3>
        <p class="text-small text-muted help-case-label">Profil : Agent &bull; Registre : RSST</p>
        <div class="help-feature-list help-case-body">
            <strong>Situation :</strong> Jean, agent à l'UR Côte-d'Or, constate que la rampe d'escalier du 2e étage est desserrée et présente un risque de chute.<br><br>
            <strong>Parcours :</strong>
            <ol>
                <li>Jean se connecte à l'application (automatiquement via Windows Auth en prod)</li>
                <li>Sur l'accueil, il clique sur <strong>« Inscrire un signalement »</strong> sur la carte RSST</li>
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
        </div>
    </div>

    <!-- CU2 : RAMI pour un tiers -->
    <div class="help-profile-card card--spaced">
        <h3>CU2 — Signalement RAMI pour le compte d'un collègue</h3>
        <p class="text-small text-muted help-case-label">Profil : Agent &bull; Registre : RAMI</p>
        <div class="help-feature-list help-case-body">
            <strong>Situation :</strong> Sophie, agent au Siège, est témoin d'une agression verbale envers son collègue Pierre par un usager. Pierre est trop choqué pour faire le signalement lui-même.<br><br>
            <strong>Parcours :</strong>
            <ol>
                <li>Sophie clique sur <strong>« Inscrire un signalement »</strong> sur la carte RAMI</li>
                <li>Elle remplit le formulaire et coche <strong>« Pour le compte de »</strong> (champ spécifique RAMI)</li>
                <li>Elle sélectionne <strong>Pierre Dupont</strong> dans la liste des agents comme bénéficiaire du signalement</li>
                <li>Elle décrit les faits de manière objective avec date, heure et lieu</li>
                <li>Le signalement est enregistré — Sophie apparaît comme déclarant, Pierre comme « pour le compte de »</li>
            </ol>
        </div>
    </div>

    <!-- CU3 : DGI urgence -->
    <div class="help-profile-card card--spaced">
        <h3>CU3 — Signalement d'un Danger Grave et Imminent (DGI)</h3>
        <p class="text-small text-muted help-case-label">Profil : Agent &bull; Registre : DGI</p>
        <div class="help-feature-list help-case-body">
            <strong>Situation :</strong> Marc, agent à l'UR Doubs, découvre une fuite de gaz dans les locaux. La situation nécessite une intervention immédiate.<br><br>
            <strong>Parcours :</strong>
            <ol>
                <li>Marc clique sur <strong>« Inscrire un signalement »</strong> sur la carte DGI (couleur rouge)</li>
                <li>Il signale le danger avec la mention <strong>Danger Grave et Imminent</strong></li>
                <li>Il précise la nature du danger, le lieu exact et l'heure</li>
                <li>Le signalement est créé avec la référence <code>dgi-25-001</code> et une <strong>notification immédiate</strong> est envoyée aux superviseurs</li>
                <li>Le traitement est <strong>prioritaire</strong> — le superviseur doit répondre dans les plus brefs délais</li>
            </ol>
        </div>
    </div>

    <!-- CU4 : Superviseur traite -->
    <div class="help-profile-card card--spaced">
        <h3>CU4 — Un superviseur traite un signalement</h3>
        <p class="text-small text-muted help-case-label">Profil : Superviseur &bull; Tous registres</p>
        <div class="help-feature-list help-case-body">
            <strong>Situation :</strong> Claire, superviseure, reçoit la notification du signalement RSST de Jean (rampe desserrée). Elle doit traiter le signalement.<br><br>
            <strong>Parcours :</strong>
            <ol>
                <li>Claire consulte la <strong>liste RSST</strong> ou le lien dans l'e-mail de notification</li>
                <li>Elle clique sur le signalement <code>rsst-25-001</code> pour voir le détail</li>
                <li>Elle clique sur <strong>« Répondre »</strong></li>
                <li>Elle passe le statut à <span class="badge badge--en-cours">En cours</span> en indiquant : « Mission demandée au service technique pour réparation »</li>
                <li>Quelques jours plus tard, la rampe est réparée. Elle revient sur le signalement et le passe à <span class="badge badge--traite">Traité</span> avec la réponse : « Rampe réparée le 12/06/2025. Contrôle visuel effectué. »</li>
                <li>Le déclarant (Jean) peut voir la réponse dans le détail de son signalement</li>
            </ol>
        </div>
    </div>

    <!-- CU5 : Superviseur abandonne -->
    <div class="help-profile-card card--spaced">
        <h3>CU5 — Un superviseur abandonne un signalement</h3>
        <p class="text-small text-muted help-case-label">Profil : Superviseur</p>
        <div class="help-feature-list help-case-body">
            <strong>Situation :</strong> Un signalement s'avère être un doublon ou ne pas relever du registre concerné.<br><br>
            <strong>Parcours :</strong>
            <ol>
                <li>Le superviseur consulte le signalement</li>
                <li>Il clique sur <strong>« Abandonner »</strong></li>
                <li>Il saisit un motif d'abandon (ex : « Doublon du signalement rsst-25-003 »)</li>
                <li>Le statut passe à <span class="badge badge--abandonne">Abandonné</span></li>
            </ol>
        </div>
    </div>

    <!-- CU6 : CHSCT consulte -->
    <div class="help-profile-card card--spaced">
        <h3>CU6 — Un membre CHSCT consulte les signalements</h3>
        <p class="text-small text-muted help-case-label">Profil : Membre CHSCT</p>
        <div class="help-feature-list help-case-body">
            <strong>Situation :</strong> Philippe, membre CHSCT, souhaite avoir une vue d'ensemble de l'activité des 3 registres sur l'ensemble des sites.<br><br>
            <strong>Parcours :</strong>
            <ol>
                <li>Il accède à la <strong>Synthèse</strong> pour voir le nombre de signalements par registre, par site et par état</li>
                <li>Il consulte les <strong>Statistiques</strong> pour voir les tendances (évolution mensuelle, répartition par site)</li>
                <li>Il utilise l'<strong>Export</strong> pour télécharger les données au format CSV et les analyser dans Excel</li>
                <li>Il peut consulter le détail de n'importe quel signalement sur <strong>tous les sites</strong></li>
            </ol>
            <p class="help-warning-callout">
                &#x26A0; Le membre CHSCT <strong>ne peut pas répondre</strong> aux signalements ni modifier les utilisateurs — il a un rôle de consultation uniquement.
            </p>
        </div>
    </div>

    <!-- CU7 : Superviseur gère utilisateurs -->
    <div class="help-profile-card card--spaced">
        <h3>CU7 — Un superviseur gère les utilisateurs et la configuration</h3>
        <p class="text-small text-muted help-case-label">Profil : Superviseur uniquement</p>
        <div class="help-feature-list help-case-body">
            <strong>Situation :</strong> Un nouvel agent arrive à l'UR Jura. Il doit pouvoir utiliser l'application.<br><br>
            <strong>Parcours :</strong>
            <ol>
                <li><strong>En production</strong> : l'agent se connecte &rarr; son compte est <strong>automatiquement créé</strong> avec le rôle Agent et sans site attribué.</li>
                <li>L'agent est alors redirigé vers la page <strong>« Choisir mon site »</strong> — il sélectionne son UR parmi les sites actifs. Ce choix est <strong>définitif</strong> pour l'agent (seul un superviseur peut le modifier ensuite).</li>
                <li>Le superviseur peut ensuite modifier le site ou le rôle dans <strong>Utilisateurs</strong> si nécessaire</li>
                <li>Il peut aussi <strong>désactiver</strong> un compte d'agent qui a quitté la structure</li>
            </ol>
            <p class="help-feature-list card--spaced-top"><strong>Configuration initiale :</strong></p>
            <ol>
                <li>Dans <strong>Paramètres &rarr; Application</strong>, configurer le nom de l'organisation et le libellé des unités (UR, UD...)</li>
                <li>Dans <strong>Paramètres &rarr; Application</strong>, compléter la <strong>liste des logins superviseurs</strong> — ces utilisateurs seront automatiquement promus Superviseur lors de leur première connexion via IIS (utile pour une première installation)</li>
                <li>Dans <strong>Paramètres &rarr; SMTP</strong>, configurer le serveur d'envoi d'e-mails</li>
                <li>Dans <strong>Paramètres &rarr; Notifications</strong>, ajouter les adresses e-mail à notifier par site et/ou globalement</li>
            </ol>
        </div>
    </div>

    <!-- CU8 : Impression -->
    <div class="help-profile-card card--spaced">
        <h3>CU8 — Imprimer une fiche de signalement</h3>
        <p class="text-small text-muted help-case-label">Profil : Superviseur uniquement</p>
        <div class="help-feature-list help-case-body">
            <strong>Situation :</strong> Le superviseur doit archiver une fiche papier ou la transmettre à un service partenaire.<br><br>
            <strong>Parcours :</strong>
            <ol>
                <li>Le superviseur ouvre le signalement</li>
                <li>Il clique sur <strong>« Télécharger en PDF »</strong></li>
                <li>Un fichier PDF est généré et téléchargé automatiquement, prêt pour impression ou archivage</li>
            </ol>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- Authentification                                              -->
<!-- ============================================================ -->
<div class="card card--spaced content-section">
    <h2>&#x1F510; Authentification</h2>
    <div class="help-profiles-grid">
        <div class="help-auth-card--prod">
            <h4>&#x1F5A5;&#xFE0F; Production (IIS)</h4>
            <p class="help-description">
                L'authentification est gérée par <strong>IIS Windows Authentication</strong>. 
                L'utilisateur est automatiquement authentifié via son compte Windows Active Directory.
                Aucun formulaire de login n'est affiché. Son compte est créé automatiquement à la première connexion.
                <br><br>
                <strong>Promotion automatique :</strong> si le login Windows figure dans la liste configurée dans
                <strong>Paramètres &rarr; Application &rarr; Logins Windows des superviseurs</strong>,
                l'utilisateur est automatiquement promu Superviseur.
            </p>
        </div>
        <div class="help-auth-card--dev">
            <h4>&#x2699;&#xFE0F; Développement</h4>
            <p class="help-description">
                En mode développement, un <strong>formulaire de connexion mock</strong> permet de tester les différents profils.
                Les comptes de test sont : <code>admin.dev</code>, <code>agent.dev</code>, <code>chsct.dev</code>.
            </p>
        </div>
    </div>
</div>

<div class="card--spaced-top">
    <a href="<?php echo url('preamble'); ?>" class="btn btn--outline">&#x1F4D6; Lire le Préambule</a>
</div>
