<?php
/**
 * Help / Documentation Page — Application SST DREETS BFC
 * 
 * Refonte complète de la documentation intégrée.
 * Captures d'écran PNG annotées (flèches + labels numérotés).
 * Imprimables, sans iframe — compatibles CSP frame-ancestors 'none'.
 * Navigation par ancres — contenu toujours visible, rien de pliable.
 */
$pageTitle = 'Documentation';
$userRole = currentUserRole() ?: 'agent';
$labelUnite = e(getConfig('app_label_unite', 'UR'));
$screenshotBase = 'asset.php?f=screenshots';
$isAgent = ($userRole === 'agent');
$hotlineNumber = getConfig('app_hotline_number', '');
$hotlineEnabled = (!empty($hotlineNumber));

// Screenshot helper — must be defined BEFORE any HTML output
function helpImg(string $name, string $alt, string $base): string {
    $src = $base . '/' . $name;
    return '<img src="' . $src . '" alt="' . e($alt) . '" style="max-width:100%;border:1px solid #ddd;border-radius:8px;margin:8px 0;">';
}
?>

<?php if ($isAgent): ?>
<!-- ============================================================ -->
<!-- AIDE SIMPLIFIÉE — Agent (Monsieur Robert)                    -->
<!-- ============================================================ -->
<h1 class="page-title">Aide</h1>

<?php if ($hotlineEnabled): ?>
<div class="help-contact-banner" role="complementary" aria-label="Hotline">
    <span class="help-contact-banner__icon" aria-hidden="true">📞</span>
    <div class="help-contact-banner__text">
        <strong>Besoin d'aide ?</strong> Appelez la hotline au <strong style="font-size:1.3em;"><?php echo e($hotlineNumber); ?></strong><br>
        <small>Un conseiller vous répondra directement pour vous guider.</small>
    </div>
</div>
<?php else: ?>
<div class="help-contact-banner" role="complementary" aria-label="Contact administrateur">
    <span class="help-contact-banner__icon" aria-hidden="true">📞</span>
    <div class="help-contact-banner__text">
        <strong>Besoin d'aide ?</strong> Contactez votre administrateur au <strong>poste interne</strong>.<br>
        <small>Un humain vous répondra directement pour vous guider.</small>
    </div>
</div>
<?php endif; ?>

<!-- Que pouvez-vous faire ? -->
<div class="card card--spaced content-section">
    <h2>Que pouvez-vous faire ?</h2>
    <p class="help-description">En tant qu'agent, vous avez 3 actions principales :</p>

    <div class="help-profiles-grid">
        <div class="help-profile-card" style="border-left: 5px solid var(--color-primary);">
            <h3>✏️ Signaler un événement</h3>
            <p>Vous avez vu un problème de sécurité, une agression ou un danger grave ? Cliquez sur le bouton <strong>« Signaler un événement »</strong> sous le registre correspondant sur la page d'accueil.</p>
            <p class="help-description">3 registres possibles :</p>
            <ul class="help-feature-list">
                <li>🔵 <strong>RSST</strong> — Santé et Sécurité au Travail (ex : escalier cassé, équipement défectueux)</li>
                <li>🟡 <strong>RAMI</strong> — Agressions, Menaces, Incivilités (ex : insultes, harcèlement)</li>
                <li>🔴 <strong>DGI</strong> — Danger Grave et Imminent (ex : risque d'accident immédiat)</li>
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
            <p>Allez dans <strong>RSST</strong>, <strong>RAMI</strong> ou <strong>DGI</strong> dans le menu de gauche pour voir la liste de vos signalements.</p>
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
            <li><strong>Choisissez le bon registre</strong> sur la page d'accueil (RSST, RAMI ou DGI)</li>
            <li><strong>Remplissez le formulaire</strong> — Les champs avec une étoile * sont obligatoires, les autres sont optionnels</li>
            <li><strong>Envoyez</strong> — Un bandeau vert confirme que c'est bien enregistré. Votre superviseur est prévenu automatiquement.</li>
        </ol>
    </div>

    <p style="text-align:center;margin:16px 0 8px 0;font-size:17px;"><strong>Voici les 3 étapes en images :</strong></p>

    <div class="help-profiles-grid" style="grid-template-columns: 1fr;">
        <div class="help-profile-card" style="text-align:center;border-left:5px solid var(--color-primary);">
            <h3 style="text-align:left;">Étape 1 — Choisissez le bon registre</h3>
            <?php echo helpImg('cu1-accueil.png', "Page d'accueil avec les 3 registres", $screenshotBase); ?>
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

    <div>
        <p style="font-size:17px;font-weight:600;margin:0 0 6px 0;">Puis-je signaler pour un collègue ?</p>
        <p style="margin:0;color:#555;">Oui, dans les registres RSST et RAMI, cochez la case « Signalement pour le compte de quelqu'un d'autre » et indiquez le nom de la personne concernée.</p>
    </div>
</div>

<!-- Guide rapide -->
<div class="card card--spaced content-section">
    <h2>Guide rapide imprimable</h2>
    <p class="help-description">Vous pouvez imprimer un guide en 3 étapes avec des captures d'écran :</p>
    <a href="<?php echo url('guide'); ?>" class="btn btn--primary" style="font-size:17px;min-height:48px;display:inline-flex;gap:8px;">
        📄 Ouvrir le guide rapide
    </a>
</div>

<?php else: ?>

<!-- ============================================================ -->
<!-- AIDE COMPLÈTE — Superviseur / CHSCT                          -->
<!-- ============================================================ -->

<h1 class="page-title">Documentation</h1>

<!-- ============================================================ -->
<!-- Bandeau d'aide humaine                                        -->
<!-- ============================================================ -->
<?php if ($hotlineEnabled): ?>
<div class="help-contact-banner" role="complementary" aria-label="Hotline">
    <span class="help-contact-banner__icon" aria-hidden="true">📞</span>
    <div class="help-contact-banner__text">
        <strong>Besoin d'aide ?</strong> Appelez la hotline au <strong style="font-size:1.3em;"><?php echo e($hotlineNumber); ?></strong><br>
        <small>Un conseiller vous répondra directement pour vous guider.</small>
    </div>
</div>
<?php else: ?>
<div class="help-contact-banner" role="complementary" aria-label="Contact administrateur">
    <span class="help-contact-banner__icon" aria-hidden="true">📞</span>
    <div class="help-contact-banner__text">
        <strong>Besoin d'aide ?</strong> Contactez votre administrateur au <strong>poste interne</strong>.<br>
        <small>Un humain vous répondra directement pour vous guider.</small>
    </div>
</div>
<?php endif; ?>

<!-- ============================================================ -->
<!-- Sommaire                                                      -->
<!-- ============================================================ -->
<nav class="help-toc" aria-label="Sommaire de la documentation">
    <h2 class="help-toc__title">📑 Sommaire</h2>
    <ol class="help-toc__list">
        <li><a href="#profils"><span class="help-toc__num">1</span> Profils utilisateurs</a></li>
        <li><a href="#droits"><span class="help-toc__num">2</span> Tableau des droits</a></li>
        <li><a href="#confidentialite"><span class="help-toc__num">3</span> Confidentialité des signalements</a></li>
        <li><a href="#registres"><span class="help-toc__num">4</span> Les 3 registres</a></li>
        <li><a href="#cycle-vie"><span class="help-toc__num">5</span> Cycle de vie d'un signalement</a></li>
        <li><a href="#cas-usage"><span class="help-toc__num">6</span> Cas d'usage</a>
            <ol>
                <li><a href="#cu1"><span class="help-toc__num-sub">6a</span> Signaler un événement RSST</a></li>
                <li><a href="#cu2"><span class="help-toc__num-sub">6b</span> Signalement RAMI pour un collègue</a></li>
                <li><a href="#cu3"><span class="help-toc__num-sub">6c</span> Danger Grave et Imminent (DGI)</a></li>
                <li><a href="#cu4"><span class="help-toc__num-sub">6d</span> Traiter un signalement</a></li>
                <li><a href="#cu5"><span class="help-toc__num-sub">6e</span> Abandonner un signalement</a></li>
                <li><a href="#cu6"><span class="help-toc__num-sub">6f</span> Consulter la synthèse (CSA/CHSCT)</a></li>
                <li><a href="#cu7"><span class="help-toc__num-sub">6g</span> Gérer les utilisateurs et la configuration</a></li>
                <li><a href="#cu8"><span class="help-toc__num-sub">6h</span> Imprimer une fiche de signalement</a></li>
            </ol>
        </li>
        <li><a href="#auth"><span class="help-toc__num">7</span> Connexion</a></li>
    </ol>
</nav>

<!-- ============================================================ -->
<!-- 1. Profils utilisateurs                                       -->
<!-- ============================================================ -->
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
                <li>🏠 Accéder à l'accueil avec les 3 cartes de registres</li>
                <li>✏️ Créer un signalement (RSST, RAMI, DGI)</li>
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
                <span class="badge badge--chsct badge--sm">Membre CSA/CHSCT</span>
            </h3>
            <p class="help-description">Membre de la Commission Santé, Sécurité et Conditions de Travail. Consultez les signalements de tous les sites pour votre mission de suivi, sans modifier les données.</p>
            <ul class="help-feature-list">
                <li>✅ <strong>Tout ce que l'Agent peut faire</strong>, plus :</li>
                <li>👁️ Voir les signalements de <strong>tous les sites</strong></li>
                <li>📊 Accéder à la <strong>Synthèse</strong> et aux <strong>Statistiques</strong></li>
                <li>📥 <strong>Exporter</strong> les données (fichier tableur pour Excel)</li>
            </ul>
            <p class="help-note">
                👁️ Rôle de consultation uniquement — pas de réponse aux signalements ni de gestion des utilisateurs.
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
    <p class="help-description">Ce qui est accessible selon votre profil. Le Superviseur peut faire tout ce que l'Agent fait. Le CSA/CHSCT peut consulter sans agir.</p>
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
    <p class="help-description">La visibilité des signalements dépend du réglage choisi par le superviseur, par registre (RSST, RAMI, DGI).</p>
    <div class="table-wrapper">
        <table class="table table--compact" aria-label="Modes de visibilité des signalements">
            <thead>
                <tr>
                    <th>Mode</th>
                    <th>Ce que voit l'agent</th>
                    <th>Explication</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>🔒 Confidentiel</strong></td>
                    <td>Ses signalements uniquement</td>
                    <td>Vous ne voyez que vos propres signalements. Les autres agents n'y ont pas accès. Mode le plus restrictif, adapté aux situations sensibles (RAMI par ex.).</td>
                </tr>
                <tr>
                    <td><strong>👁️ Choix de l'agent</strong></td>
                    <td>Dépend de votre choix</td>
                    <td>Vous choisissez la visibilité à chaque création (public ou confidentiel). Vous voyez les signalements publics de votre <?php echo $labelUnite; ?> et les vôtres.</td>
                </tr>
                <tr>
                    <td><strong>👁️‍🗨️ Public</strong></td>
                    <td>Tous les signalements du site</td>
                    <td>Tous les agents du site voient tous les signalements. Conforme au décret 82-453 pour le registre RSST.</td>
                </tr>
                <tr class="help-separator-row">
                    <td><span class="badge badge--superviseur">Superviseur</span></td>
                    <td>Tous les sites</td>
                    <td>Accès à tous les signalements de tous les sites, y compris les confidentiels. Les consultations sont enregistrées dans le journal de suivi.</td>
                </tr>
                <tr>
                    <td><span class="badge badge--chsct">CSA/CHSCT</span></td>
                    <td>Tous les sites</td>
                    <td>Consultation de tous les signalements pour ses missions. Les consultations confidentielles sont enregistrées.</td>
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
    <p class="help-description">L'application gère <strong>3 registres</strong> distincts pour la santé et sécurité au travail.</p>

    <div class="help-profiles-grid">

        <div class="help-profile-card help-profile-card--rsst">
            <h3>RSST</h3>
            <p class="help-description help-description--title">Registre de Santé et de Sécurité au Travail</p>
            <p class="help-description">Signalez tout événement lié à la santé ou la sécurité au travail.</p>
            <ul class="help-feature-list">
                <li>🏢 Exemple : rampe desserrée, équipement défectueux, problème d'ergonomie</li>
                <li>📍 Champ spécifique : <strong>Lieu</strong> de l'événement</li>
                <li>👁️ Visibilité par défaut : <strong>public</strong> (décret 82-453)</li>
            </ul>
        </div>

        <div class="help-profile-card help-profile-card--rami">
            <h3>RAMI</h3>
            <p class="help-description help-description--title">Registre des Actes d'Agressions, de Menaces et d'Incivilités</p>
            <p class="help-description">Signalez les agressions, menaces ou incivilités. Vous pouvez aussi signaler pour un collègue.</p>
            <ul class="help-feature-list">
                <li>🤝 Champ « <strong>Pour le compte de</strong> » : signaler pour un tiers</li>
                <li>🏷️ Nature de l'auteur (usager, collègue…) et type d'acte (verbal, physique…) — optionnels</li>
            </ul>
        </div>

        <div class="help-profile-card help-profile-card--dgi">
            <h3>DGI</h3>
            <p class="help-description help-description--title">Registre de signalement d'un Danger Grave et Imminent</p>
            <p class="help-description">Signalez un danger grave nécessitant une action immédiate. Les superviseurs sont alertés tout de suite.</p>
            <ul class="help-feature-list">
                <li>⚡ Traitement <strong>prioritaire</strong> et notification immédiate</li>
                <li>⚖️ Le formulaire vaut notification au sens <strong>L4131-1</strong> (droit de retrait). La consignation <strong>D4132-1</strong> reste du ressort du CSA/CHSCT.</li>
            </ul>
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
    <p class="help-description">Chaque signalement suit un parcours en <strong>4 étapes</strong>. Chaque changement est enregistré.</p>
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
                    <td>Vous venez de créer le signalement. Un e-mail prévient les superviseurs du site.</td>
                    <td>État initial à la création</td>
                </tr>
                <tr>
                    <td><span class="badge badge--en-cours">En cours</span></td>
                    <td>Un superviseur a commencé à traiter le signalement et a écrit une première réponse.</td>
                    <td>Superviseur (via « Répondre »)</td>
                </tr>
                <tr>
                    <td><span class="badge badge--traite">Traité</span></td>
                    <td>Le signalement est résolu. Le superviseur a apporté une réponse finale. Vous pouvez la consulter.</td>
                    <td>Superviseur (via « Répondre »)</td>
                </tr>
                <tr>
                    <td><span class="badge badge--abandonne">Abandonné</span></td>
                    <td>Le signalement a été abandonné avec un motif (doublon, erreur, hors sujet…). Il reste visible mais n'est plus actif.</td>
                    <td>Superviseur (via « Abandonner »)</td>
                </tr>
            </tbody>
        </table>
    </div>

    <?php echo helpScreenshot($screenshotBase . '/consultation-liste-signalements.html', "Liste des signalements avec filtres et badges d'état"); ?>
    <?php echo helpScreenshot($screenshotBase . '/consultation-voir-rsst.html', "Vue détaillée d'un signalement RSST avec son historique"); ?>
    <?php echo helpScreenshot($screenshotBase . '/consultation-voir-rami.html', "Vue détaillée d'un signalement RAMI"); ?>
    <?php echo helpScreenshot($screenshotBase . '/consultation-voir-dgi.html', "Vue détaillée d'un signalement DGI"); ?>
</div>

<!-- ============================================================ -->
<!-- 6. Cas d'usage                                                -->
<!-- ============================================================ -->
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
            <?php echo helpScreenshot($screenshotBase . '/cu1-accueil.html', "Page d'accueil de l'agent avec les 3 cartes de registre"); ?>
            <?php echo helpScreenshot($screenshotBase . '/cu2-creation-rsst.html', "Formulaire de création d'un signalement RSST"); ?>
        </div>
    </div>

    <!-- CU2 : RAMI pour un tiers -->
    <div id="cu2" class="help-profile-card card--spaced">
        <h3>CU2 — Signalement RAMI pour le compte d'un collègue</h3>
        <p class="text-small text-muted help-case-label">Profil : Agent &bull; Registre : RAMI</p>
        <div class="help-feature-list help-case-body">
            <strong>🎯 Situation :</strong> Sophie est témoin d'une agression verbale envers son collègue Pierre. Pierre est trop choqué pour signaler. Sophie le fait pour lui.<br><br>
            <strong>📝 Étapes :</strong>
            <ol>
                <li>🖱️ Sophie clique sur <strong>« Signaler un événement »</strong> sur la carte RAMI (carte grise)</li>
                <li>🤝 Elle sélectionne Pierre dans le champ <strong>« Pour le compte de »</strong></li>
                <li>🏷️ Elle indique la nature de l'auteur (ex : Usager) et le type d'acte (ex : Verbal)</li>
                <li>✏️ Elle décrit les faits avec date, heure et lieu</li>
                <li>✅ Le signalement est enregistré — Sophie est déclarante, Pierre est « pour le compte de »</li>
            </ol>
            <p class="help-note help-note--green">
                <strong>💬 Après ?</strong> Votre signalement est envoyé aux superviseurs par e-mail. Un superviseur le prend en charge, puis le passe à « En cours » puis « Traité » avec une réponse. Vous suivez l'avancement dans la liste.
            </p>
            <?php echo helpScreenshot($screenshotBase . '/cu3-creation-rami.html', "Formulaire RAMI avec le champ « Pour le compte de » et les listes déroulantes nature_auteur et type_acte"); ?>
        </div>
    </div>

    <!-- CU3 : DGI urgence -->
    <div id="cu3" class="help-profile-card card--spaced">
        <h3>CU3 — Signalement d'un Danger Grave et Imminent (DGI)</h3>
        <p class="text-small text-muted help-case-label">Profil : Agent &bull; Registre : DGI</p>
        <div class="help-feature-list help-case-body">
            <strong>🎯 Situation :</strong> Marc découvre une fuite de gaz. Danger immédiat pour tous les occupants du bâtiment.<br><br>
            <strong>📝 Étapes :</strong>
            <ol>
                <li>🖱️ Marc clique sur <strong>« Signaler un événement »</strong> sur la carte DGI (carte rouge)</li>
                <li>⚠️ Un bandeau rappelle la procédure d'urgence DGI</li>
                <li>✏️ Il décrit le danger : nature, lieu exact et heure</li>
                <li>⚡ Le signalement est créé (<code>dgi-26-001</code>) et les superviseurs sont <strong>prévenus immédiatement</strong></li>
                <li>🔴 Le traitement est <strong>prioritaire</strong> — réponse dans les plus brefs délais</li>
            </ol>
            <?php echo helpScreenshot($screenshotBase . '/cu4-creation-dgi.html', "Formulaire DGI avec le bandeau d'avertissement sur la procédure prioritaire"); ?>
        </div>
    </div>

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
            <?php echo helpScreenshot($screenshotBase . '/cu4-repondre-signalement.html', "Formulaire de réponse du superviseur avec changement de statut En cours ou Traité"); ?>
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
            <?php echo helpScreenshot($screenshotBase . '/cu5-liste-signalements-sup.html', "Liste des signalements vue par le superviseur avec les actions Répondre et Abandonner"); ?>
            <?php echo helpScreenshot($screenshotBase . '/cu1-accueil-superviseur.html', "Page d'accueil du superviseur avec accès à tous les registres et sites"); ?>
        </div>
    </div>

    <!-- CU6 : CHSCT consulte -->
    <div id="cu6" class="help-profile-card card--spaced">
        <h3>CU6 — Un membre CSA/CHSCT consulte les signalements</h3>
        <p class="text-small text-muted help-case-label">Profil : Membre CSA/CHSCT</p>
        <div class="help-feature-list help-case-body">
            <strong>🎯 Situation :</strong> Philippe, membre CSA/CHSCT, veut voir l'activité des 3 registres sur tous les sites pour préparer la réunion trimestrielle.<br><br>
            <strong>📝 Étapes :</strong>
            <ol>
                <li>📊 Il ouvre la <strong>Synthèse</strong> pour voir les signalements par registre, site et état</li>
                <li>📈 Il consulte les <strong>Statistiques</strong> (évolution mensuelle, répartition, types d'actes)</li>
                <li>📥 Il <strong>exporte</strong> les données en fichier tableur pour les analyser dans Excel</li>
                <li>👀 Il peut consulter n'importe quel signalement sur <strong>tous les sites</strong>, même les confidentiels (consultation enregistrée)</li>
            </ol>
            <p class="help-warning-callout">
                👁️ Le membre CSA/CHSCT <strong>ne peut pas répondre</strong> aux signalements ni gérer les utilisateurs. Pour faire traiter un signalement, demandez à un superviseur.
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

<!-- ============================================================ -->
<!-- 7. Connexion                                                 -->
<!-- ============================================================ -->
<div id="auth" class="card card--spaced content-section">
    <h2>Connexion</h2>
    <p class="help-description">La connexion fonctionne différemment selon l'environnement :</p>
    <div class="help-profiles-grid">
        <div class="help-auth-card--prod">
            <h4>🖥️ Ordinateur du travail</h4>
            <p class="help-description">
                🔑 Connexion automatique avec votre <strong>compte Windows</strong>. Pas de mot de passe à taper. Votre compte est créé à la première connexion.
                <br><br>
                ⬆️ Si votre identifiant figure dans la liste des superviseurs, vous êtes automatiquement promu Superviseur.
            </p>
        </div>
        <div class="help-auth-card--dev">
            <h4>🧪 Mode test</h4>
            <p class="help-description">
                Un <strong>formulaire de connexion</strong> permet de tester les profils :
                <ul class="help-feature-list">
                    <li><code>admin.dev</code> → superviseur</li>
                    <li><code>agent.dev</code> → agent</li>
                    <li><code>chsct.dev</code> → CSA/CHSCT</li>
                </ul>
            </p>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- Liens utiles                                                  -->
<!-- ============================================================ -->
<div class="card--spaced-top help-cards-row">
    <a href="<?php echo url('preamble'); ?>" class="btn btn--outline">Lire le Préambule</a>
    <a href="<?php echo url('changelog'); ?>" class="btn btn--outline">Journal des modifications</a>
</div>

<?php
/**
 * Generate a visible screenshot block (no collapsible details).
 * Renders an annotated PNG image — always visible, never folded, printable.
 * Converts .html source path to .png automatically.
 */
function helpScreenshot(string $src, string $alt): string {
    $id = 'ss-' . substr(md5($src), 0, 8);
    // Convert .html extension to .png for image path
    // Serve via asset.php (IIS Windows Auth blocks direct static file access)
    $imgSrc = preg_replace('/\.html$/', '.png', $src);
    return <<<HTML
    <div class="help-screenshot-block" id="{$id}">
        <p class="help-screenshot-label">{$alt}</p>
        <div class="help-screenshot-wrapper">
            <img src="{$imgSrc}" alt="{$alt}" class="help-screenshot-img" loading="lazy" />
        </div>
    </div>
    HTML;
}
?>

<?php endif; ?>
