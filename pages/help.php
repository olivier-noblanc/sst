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

<h1 class="page-title">📚 Documentation</h1>

<!-- ============================================================ -->
<!-- Profils utilisateurs                                          -->
<!-- ============================================================ -->
<div class="card" style="margin-bottom:20px;">
    <h2 style="margin-bottom:16px;">👥 Profils utilisateurs</h2>
    <p style="margin-bottom:16px;color:var(--grey-600);">L'application dispose de 3 profils avec des droits croissants. En production, le profil est attribué par un Superviseur via la gestion des utilisateurs, ou automatiquement via la liste des superviseurs configurée dans les Paramètres (utile pour une première installation).</p>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;">

        <!-- Agent -->
        <div style="border:1px solid var(--grey-200);border-radius:8px;padding:16px;border-left:4px solid var(--rsst-color,#2563eb);">
            <h3 style="margin-bottom:8px;">
                <span class="badge badge--agent" style="font-size:12px;">Agent</span>
            </h3>
            <p style="font-size:13px;color:var(--grey-600);margin-bottom:12px;">Profil par défaut de tout nouvel utilisateur. L'agent peut signaler des événements et suivre les signalements de son site. À la première connexion, l'agent choisit son site (définitif, seul un superviseur peut le changer).</p>
            <ul style="font-size:13px;margin:0;padding-left:18px;">
                <li>Accéder à l'accueil</li>
                <li>Créer un signalement (RSST, RAMI, DGI)</li>
                <li>Consulter la liste des signalements de son site</li>
                <li>Voir le détail d'un signalement</li>
                <li>Modifier un signalement tant qu'il n'est pas traité</li>
                <li>Consulter le Préambule</li>
            </ul>
            <p style="font-size:12px;color:var(--grey-500);margin-top:10px;border-top:1px solid var(--grey-100);padding-top:8px;">
                🔒 La visibilité des signalements dépend du paramétrage choisi par le superviseur : confidentiel (ses signalements uniquement), choix de l'agent (public ou confidentiel au dépôt), ou public (tous les signalements du site).
            </p>
        </div>

        <!-- Superviseur -->
        <div style="border:1px solid var(--grey-200);border-radius:8px;padding:16px;border-left:4px solid var(--dgi-color,#dc2626);">
            <h3 style="margin-bottom:8px;">
                <span class="badge badge--superviseur" style="font-size:12px;">Superviseur</span>
            </h3>
            <p style="font-size:13px;color:var(--grey-600);margin-bottom:12px;">Profil d'administration. Le superviseur gère les réponses aux signalements, les utilisateurs et la configuration de l'application. Il peut également attribuer le rôle superviseur à d'autres utilisateurs.</p>
            <ul style="font-size:13px;margin:0;padding-left:18px;">
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
            <p style="font-size:12px;color:var(--grey-500);margin-top:10px;border-top:1px solid var(--grey-100);padding-top:8px;">
                🔑 Peut être attribué par un autre superviseur via la gestion des utilisateurs, ou auto-attribué via <strong>Paramètres → Logins Windows des superviseurs</strong> (utile pour une première installation).
            </p>
        </div>

        <!-- CHSCT -->
        <div style="border:1px solid var(--grey-200);border-radius:8px;padding:16px;border-left:4px solid #8b5cf6;">
            <h3 style="margin-bottom:8px;">
                <span class="badge badge--chsct" style="font-size:12px;">Membre CHSCT</span>
            </h3>
            <p style="font-size:13px;color:var(--grey-600);margin-bottom:12px;">Membre de la Commission Hygiène, Sécurité et Conditions de Travail. Accès en consultation élargie sur tous les sites pour le suivi des registres SST.</p>
            <ul style="font-size:13px;margin:0;padding-left:18px;">
                <li><strong>Tout ce que l'Agent peut faire</strong>, plus :</li>
                <li>Voir les signalements de <strong>tous les sites</strong></li>
                <li>Accéder à la <strong>Synthèse</strong></li>
                <li>Accéder aux <strong>Statistiques</strong></li>
                <li><strong>Exporter</strong> les données</li>
            </ul>
            <p style="font-size:12px;color:var(--grey-500);margin-top:10px;border-top:1px solid var(--grey-100);padding-top:8px;">
                ⚠ Ne peut pas répondre aux signalements ni gérer les utilisateurs. Rôle de consultation uniquement.
            </p>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- Tableau récapitulatif des droits                              -->
<!-- ============================================================ -->
<div class="card" style="margin-bottom:20px;">
    <h2 style="margin-bottom:16px;">🔒 Tableau des droits</h2>
    <div style="overflow-x:auto;">
        <table class="table" style="font-size:13px;min-width:500px;">
            <thead>
                <tr>
                    <th style="text-align:left;">Fonctionnalité</th>
                    <th style="text-align:center;">Agent</th>
                    <th style="text-align:center;">Superviseur</th>
                    <th style="text-align:center;">CHSCT</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Créer un signalement</td>
                    <td style="text-align:center;">✅</td>
                    <td style="text-align:center;">✅</td>
                    <td style="text-align:center;">✅</td>
                </tr>
                <tr>
                    <td>Voir ses signalements</td>
                    <td style="text-align:center;">✅</td>
                    <td style="text-align:center;">✅</td>
                    <td style="text-align:center;">✅</td>
                </tr>
                <tr>
                    <td>Modifier un signalement (non traité)</td>
                    <td style="text-align:center;">✅</td>
                    <td style="text-align:center;">✅</td>
                    <td style="text-align:center;">✅</td>
                </tr>
                <tr>
                    <td>Voir les signalements de tous les sites</td>
                    <td style="text-align:center;">❌</td>
                    <td style="text-align:center;">✅</td>
                    <td style="text-align:center;">✅</td>
                </tr>
                <tr>
                    <td>Répondre à un signalement</td>
                    <td style="text-align:center;">❌</td>
                    <td style="text-align:center;">✅</td>
                    <td style="text-align:center;">❌</td>
                </tr>
                <tr>
                    <td>Abandonner un signalement</td>
                    <td style="text-align:center;">❌</td>
                    <td style="text-align:center;">✅</td>
                    <td style="text-align:center;">❌</td>
                </tr>
                <tr>
                    <td>Imprimer une fiche</td>
                    <td style="text-align:center;">❌</td>
                    <td style="text-align:center;">✅</td>
                    <td style="text-align:center;">❌</td>
                </tr>
                <tr>
                    <td>Synthèse des signalements</td>
                    <td style="text-align:center;">❌</td>
                    <td style="text-align:center;">✅</td>
                    <td style="text-align:center;">✅</td>
                </tr>
                <tr>
                    <td>Statistiques</td>
                    <td style="text-align:center;">❌</td>
                    <td style="text-align:center;">✅</td>
                    <td style="text-align:center;">✅</td>
                </tr>
                <tr>
                    <td>Exporter les données</td>
                    <td style="text-align:center;">❌</td>
                    <td style="text-align:center;">✅</td>
                    <td style="text-align:center;">✅</td>
                </tr>
                <tr>
                    <td>Gérer les utilisateurs</td>
                    <td style="text-align:center;">❌</td>
                    <td style="text-align:center;">✅</td>
                    <td style="text-align:center;">❌</td>
                </tr>
                <tr>
                    <td>Paramètres de l'application</td>
                    <td style="text-align:center;">❌</td>
                    <td style="text-align:center;">✅</td>
                    <td style="text-align:center;">❌</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ============================================================ -->
<!-- Confidentialité des signalements                              -->
<!-- ============================================================ -->
<div class="card" style="margin-bottom:20px;">
    <h2 style="margin-bottom:16px;">🔐 Confidentialité des signalements</h2>
    <p style="margin-bottom:12px;color:var(--grey-600);font-size:13px;">La visibilité des signalements dépend du paramétrage choisi par le superviseur dans les Paramètres de l'application :</p>
    <table class="table" style="font-size:13px;">
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
            <tr style="border-top:2px solid var(--grey-200);">
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

<!-- ============================================================ -->
<!-- Les 3 registres                                               -->
<!-- ============================================================ -->
<div class="card" style="margin-bottom:20px;">
    <h2 style="margin-bottom:16px;">📋 Les 3 registres</h2>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;">

        <div style="border:1px solid var(--grey-200);border-radius:8px;padding:16px;border-top:4px solid var(--rsst-color,#2563eb);">
            <h3 style="color:var(--rsst-color,#2563eb);margin-bottom:8px;">📋 RSST</h3>
            <p style="font-size:13px;font-weight:600;margin-bottom:6px;">Registre de Santé et de Sécurité au Travail</p>
            <p style="font-size:13px;color:var(--grey-600);">Signalement de tout événement lié à la santé ou la sécurité au travail : conditions de travail dangereuses, équipements défectueux, risques professionnels, etc.</p>
            <p style="font-size:12px;color:var(--grey-500);margin-top:8px;">Champ spécifique : <strong>Lieu</strong> de l'événement.</p>
        </div>

        <div style="border:1px solid var(--grey-200);border-radius:8px;padding:16px;border-top:4px solid var(--rami-color,#6b7280);">
            <h3 style="color:var(--rami-color,#6b7280);margin-bottom:8px;">⚠️ RAMI</h3>
            <p style="font-size:13px;font-weight:600;margin-bottom:6px;">Registre des Actes d'Agressions, de Menaces et d'Incivilités</p>
            <p style="font-size:13px;color:var(--grey-600);">Signalement d'agressions physiques ou verbales, de menaces, ou d'incivilités subies par un agent dans le cadre de ses fonctions.</p>
            <p style="font-size:12px;color:var(--grey-500);margin-top:8px;">Champ spécifique : <strong>« Pour le compte de »</strong> (signalement pour un tiers).</p>
        </div>

        <div style="border:1px solid var(--grey-200);border-radius:8px;padding:16px;border-top:4px solid var(--dgi-color,#dc2626);">
            <h3 style="color:var(--dgi-color,#dc2626);margin-bottom:8px;">🔴 DGI</h3>
            <p style="font-size:13px;font-weight:600;margin-bottom:6px;">Registre de signalement d'un Danger Grave et Imminent</p>
            <p style="font-size:13px;color:var(--grey-600);">Signalement d'une situation de danger grave et imminent nécessitant une action immédiate. Ce registre bénéficie d'une procédure accélérée.</p>
            <p style="font-size:12px;color:var(--grey-500);margin-top:8px;">Procédure : <strong>traitement prioritaire</strong>, notification immédiate.</p>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- Cycle de vie d'un signalement                                 -->
<!-- ============================================================ -->
<div class="card" style="margin-bottom:20px;">
    <h2 style="margin-bottom:16px;">🔄 Cycle de vie d'un signalement</h2>
    <p style="margin-bottom:12px;color:var(--grey-600);font-size:13px;">Chaque signalement suit un workflow en 4 états :</p>
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
        <span class="badge badge--nouveau" style="font-size:13px;padding:6px 14px;">Nouveau</span>
        <span style="color:var(--grey-400);">→</span>
        <span class="badge badge--en-cours" style="font-size:13px;padding:6px 14px;">En cours</span>
        <span style="color:var(--grey-400);">→</span>
        <span class="badge badge--traite" style="font-size:13px;padding:6px 14px;">Traité</span>
        <span style="color:var(--grey-400);">ou</span>
        <span class="badge badge--abandonne" style="font-size:13px;padding:6px 14px;">Abandonné</span>
    </div>
    <table class="table" style="font-size:13px;">
        <thead>
            <tr>
                <th style="text-align:left;">État</th>
                <th style="text-align:left;">Description</th>
                <th style="text-align:left;">Qui peut changer ?</th>
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

<!-- ============================================================ -->
<!-- Cas d'usage                                                   -->
<!-- ============================================================ -->
<div class="card" style="margin-bottom:20px;">
    <h2 style="margin-bottom:16px;">🎯 Cas d'usage</h2>
    <p style="margin-bottom:16px;color:var(--grey-600);">Scénarios concrets d'utilisation de l'application selon le profil et la situation.</p>

    <!-- CU1 : Agent signale -->
    <div style="border:1px solid var(--grey-200);border-radius:8px;padding:16px;margin-bottom:16px;">
        <h3 style="margin-bottom:8px;">CU1 — Un agent signale un événement dans le registre RSST</h3>
        <p style="font-size:12px;color:var(--grey-500);margin-bottom:10px;">Profil : Agent • Registre : RSST</p>
        <div style="font-size:13px;line-height:1.8;">
            <strong>Situation :</strong> Jean, agent à l'UR Côte-d'Or, constate que la rampe d'escalier du 2e étage est desserrée et présente un risque de chute.<br><br>
            <strong>Parcours :</strong>
            <ol style="padding-left:20px;">
                <li>Jean se connecte à l'application (automatiquement via Windows Auth en prod)</li>
                <li>Sur l'accueil, il clique sur <strong>« Inscrire un signalement »</strong> sur la carte RSST</li>
                <li>Il remplit le formulaire :
                    <ul style="padding-left:18px;">
                        <li><strong>Objet</strong> : « Rampe d'escalier desserrée - risque de chute »</li>
                        <li><strong>Date de l'événement</strong> : date du jour</li>
                        <li><strong>Lieu</strong> : « Bâtiment principal, 2e étage, escalier B »</li>
                        <li><strong>Description</strong> : détail de la situation observée</li>
                    </ul>
                </li>
                <li>Il valide → le signalement est créé avec le statut <span class="badge badge--nouveau">Nouveau</span> et la référence <code>rsst-25-001</code></li>
                <li>Un e-mail de notification est envoyé aux adresses configurées pour ce site</li>
                <li>Jean peut suivre son signalement dans la liste RSST de son site</li>
            </ol>
        </div>
    </div>

    <!-- CU2 : RAMI pour un tiers -->
    <div style="border:1px solid var(--grey-200);border-radius:8px;padding:16px;margin-bottom:16px;">
        <h3 style="margin-bottom:8px;">CU2 — Signalement RAMI pour le compte d'un collègue</h3>
        <p style="font-size:12px;color:var(--grey-500);margin-bottom:10px;">Profil : Agent • Registre : RAMI</p>
        <div style="font-size:13px;line-height:1.8;">
            <strong>Situation :</strong> Sophie, agent au Siège, est témoin d'une agression verbale envers son collègue Pierre par un usager. Pierre est trop choqué pour faire le signalement lui-même.<br><br>
            <strong>Parcours :</strong>
            <ol style="padding-left:20px;">
                <li>Sophie clique sur <strong>« Inscrire un signalement »</strong> sur la carte RAMI</li>
                <li>Elle remplit le formulaire et coche <strong>« Pour le compte de »</strong> (champ spécifique RAMI)</li>
                <li>Elle sélectionne <strong>Pierre Dupont</strong> dans la liste des agents comme bénéficiaire du signalement</li>
                <li>Elle décrit les faits de manière objective avec date, heure et lieu</li>
                <li>Le signalement est enregistré — Sophie apparaît comme déclarant, Pierre comme « pour le compte de »</li>
            </ol>
        </div>
    </div>

    <!-- CU3 : DGI urgence -->
    <div style="border:1px solid var(--grey-200);border-radius:8px;padding:16px;margin-bottom:16px;">
        <h3 style="margin-bottom:8px;">CU3 — Signalement d'un Danger Grave et Imminent (DGI)</h3>
        <p style="font-size:12px;color:var(--grey-500);margin-bottom:10px;">Profil : Agent • Registre : DGI</p>
        <div style="font-size:13px;line-height:1.8;">
            <strong>Situation :</strong> Marc, agent à l'UR Doubs, découvre une fuite de gaz dans les locaux. La situation nécessite une intervention immédiate.<br><br>
            <strong>Parcours :</strong>
            <ol style="padding-left:20px;">
                <li>Marc clique sur <strong>« Inscrire un signalement »</strong> sur la carte DGI (couleur rouge)</li>
                <li>Il signale le danger avec la mention <strong>Danger Grave et Imminent</strong></li>
                <li>Il précise la nature du danger, le lieu exact et l'heure</li>
                <li>Le signalement est créé avec la référence <code>dgi-25-001</code> et une <strong>notification immédiate</strong> est envoyée aux superviseurs</li>
                <li>Le traitement est <strong>prioritaire</strong> — le superviseur doit répondre dans les plus brefs délais</li>
            </ol>
        </div>
    </div>

    <!-- CU4 : Superviseur traite -->
    <div style="border:1px solid var(--grey-200);border-radius:8px;padding:16px;margin-bottom:16px;">
        <h3 style="margin-bottom:8px;">CU4 — Un superviseur traite un signalement</h3>
        <p style="font-size:12px;color:var(--grey-500);margin-bottom:10px;">Profil : Superviseur • Tous registres</p>
        <div style="font-size:13px;line-height:1.8;">
            <strong>Situation :</strong> Claire, superviseure, reçoit la notification du signalement RSST de Jean (rampe desserrée). Elle doit traiter le signalement.<br><br>
            <strong>Parcours :</strong>
            <ol style="padding-left:20px;">
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
    <div style="border:1px solid var(--grey-200);border-radius:8px;padding:16px;margin-bottom:16px;">
        <h3 style="margin-bottom:8px;">CU5 — Un superviseur abandonne un signalement</h3>
        <p style="font-size:12px;color:var(--grey-500);margin-bottom:10px;">Profil : Superviseur</p>
        <div style="font-size:13px;line-height:1.8;">
            <strong>Situation :</strong> Un signalement s'avère être un doublon ou ne pas relever du registre concerné.<br><br>
            <strong>Parcours :</strong>
            <ol style="padding-left:20px;">
                <li>Le superviseur consulte le signalement</li>
                <li>Il clique sur <strong>« Abandonner »</strong></li>
                <li>Il saisit un motif d'abandon (ex : « Doublon du signalement rsst-25-003 »)</li>
                <li>Le statut passe à <span class="badge badge--abandonne">Abandonné</span></li>
            </ol>
        </div>
    </div>

    <!-- CU6 : CHSCT consulte -->
    <div style="border:1px solid var(--grey-200);border-radius:8px;padding:16px;margin-bottom:16px;">
        <h3 style="margin-bottom:8px;">CU6 — Un membre CHSCT consulte les signalements</h3>
        <p style="font-size:12px;color:var(--grey-500);margin-bottom:10px;">Profil : Membre CHSCT</p>
        <div style="font-size:13px;line-height:1.8;">
            <strong>Situation :</strong> Philippe, membre CHSCT, souhaite avoir une vue d'ensemble de l'activité des 3 registres sur l'ensemble des sites.<br><br>
            <strong>Parcours :</strong>
            <ol style="padding-left:20px;">
                <li>Il accède à la <strong>Synthèse</strong> pour voir le nombre de signalements par registre, par site et par état</li>
                <li>Il consulte les <strong>Statistiques</strong> pour voir les tendances (évolution mensuelle, répartition par site)</li>
                <li>Il utilise l'<strong>Export</strong> pour télécharger les données au format CSV et les analyser dans Excel</li>
                <li>Il peut consulter le détail de n'importe quel signalement sur <strong>tous les sites</strong></li>
            </ol>
            <p style="margin-top:8px;padding:8px 12px;background:#fffbeb;border:1px solid #fde68a;border-radius:6px;font-size:12px;color:#92400e;">
                ⚠ Le membre CHSCT <strong>ne peut pas répondre</strong> aux signalements ni modifier les utilisateurs — il a un rôle de consultation uniquement.
            </p>
        </div>
    </div>

    <!-- CU7 : Superviseur gère utilisateurs -->
    <div style="border:1px solid var(--grey-200);border-radius:8px;padding:16px;margin-bottom:16px;">
        <h3 style="margin-bottom:8px;">CU7 — Un superviseur gère les utilisateurs et la configuration</h3>
        <p style="font-size:12px;color:var(--grey-500);margin-bottom:10px;">Profil : Superviseur uniquement</p>
        <div style="font-size:13px;line-height:1.8;">
            <strong>Situation :</strong> Un nouvel agent arrive à l'UR Jura. Il doit pouvoir utiliser l'application.<br><br>
            <strong>Parcours :</strong>
            <ol style="padding-left:20px;">
                <li><strong>En production</strong> : l'agent se connecte → son compte est <strong>automatiquement créé</strong> avec le rôle Agent et sans site attribué.</li>
                <li>L'agent est alors redirigé vers la page <strong>« Choisir mon site »</strong> — il sélectionne son UR parmi les sites actifs. Ce choix est <strong>définitif</strong> pour l'agent (seul un superviseur peut le modifier ensuite).</li>
                <li>Le superviseur peut ensuite modifier le site ou le rôle dans <strong>Utilisateurs</strong> si nécessaire</li>
                <li>Il peut aussi <strong>désactiver</strong> un compte d'agent qui a quitté la structure</li>
            </ol>
            <p style="margin-top:8px;font-size:13px;"><strong>Configuration initiale :</strong></p>
            <ol style="padding-left:20px;">
                <li>Dans <strong>Paramètres → Application</strong>, configurer le nom de l'organisation et le libellé des unités (UR, UD...)</li>
                <li>Dans <strong>Paramètres → Application</strong>, compléter la <strong>liste des logins superviseurs</strong> — ces utilisateurs seront automatiquement promus Superviseur lors de leur première connexion via IIS (utile pour une première installation)</li>
                <li>Dans <strong>Paramètres → SMTP</strong>, configurer le serveur d'envoi d'e-mails</li>
                <li>Dans <strong>Paramètres → Notifications</strong>, ajouter les adresses e-mail à notifier par site et/ou globalement</li>
            </ol>
        </div>
    </div>

    <!-- CU8 : Impression -->
    <div style="border:1px solid var(--grey-200);border-radius:8px;padding:16px;margin-bottom:16px;">
        <h3 style="margin-bottom:8px;">CU8 — Imprimer une fiche de signalement</h3>
        <p style="font-size:12px;color:var(--grey-500);margin-bottom:10px;">Profil : Superviseur uniquement</p>
        <div style="font-size:13px;line-height:1.8;">
            <strong>Situation :</strong> Le superviseur doit archiver une fiche papier ou la transmettre à un service partenaire.<br><br>
            <strong>Parcours :</strong>
            <ol style="padding-left:20px;">
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
<div class="card" style="margin-bottom:20px;">
    <h2 style="margin-bottom:16px;">🔐 Authentification</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;">
        <div style="padding:12px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;">
            <h4 style="margin-bottom:6px;color:#166534;">🖥️ Production (IIS)</h4>
            <p style="font-size:13px;color:var(--grey-600);">
                L'authentification est gérée par <strong>IIS Windows Authentication</strong>. 
                L'utilisateur est automatiquement authentifié via son compte Windows Active Directory.
                Aucun formulaire de login n'est affiché. Son compte est créé automatiquement à la première connexion.
                <br><br>
                <strong>Promotion automatique :</strong> si le login Windows figure dans la liste configurée dans
                <strong>Paramètres → Application → Logins Windows des superviseurs</strong>,
                l'utilisateur est automatiquement promu Superviseur.
            </p>
        </div>
        <div style="padding:12px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;">
            <h4 style="margin-bottom:6px;color:#92400e;">⚙️ Développement</h4>
            <p style="font-size:13px;color:var(--grey-600);">
                En mode développement, un <strong>formulaire de connexion mock</strong> permet de tester les différents profils.
                Les comptes de test sont : <code>admin.dev</code>, <code>agent.dev</code>, <code>chsct.dev</code>.
            </p>
        </div>
    </div>
</div>

<div style="margin-top:8px;">
    <a href="<?php echo url('preamble'); ?>" class="btn btn--outline">📖 Lire le Préambule</a>
</div>
