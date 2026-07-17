<?php
/** @var string $screenshotBase */
/** @var bool $dgiEnabled */
/** @var bool $ramiEnabled */
?>
<!-- 5. Cycle de vie d'un signalement -->
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
    <?php if ($ramiEnabled): echo helpScreenshot($screenshotBase . '/consultation-voir-rami.html', "Vue détaillée d'un signalement RAMI"); endif; ?>
    <?php if ($dgiEnabled): echo helpScreenshot($screenshotBase . '/consultation-voir-dgi.html', "Vue détaillée d'un signalement DGI"); endif; ?>
</div>
