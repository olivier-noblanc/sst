<!-- 2. Tableau récapitulatif des droits -->
<div id="droits" class="card card--spaced content-section">
    <h2>Tableau des droits</h2>
    <p class="help-description">Ce qui est accessible selon votre profil. Le Superviseur peut faire tout ce que l'Agent fait. Le <?php echo e(getRoleLabelShort('chsct')); ?> peut consulter sans agir.</p>
    <div class="table-wrapper">
        <table class="table table--compact help-rights-table" aria-label="Permissions par profil">
            <thead>
                <tr>
                    <th class="text-left">Fonctionnalité</th>
                    <th class="text-center">Agent</th>
                    <th class="text-center">Superviseur</th>
                    <th class="text-center"><?php echo e(getRoleLabelShort('chsct')); ?></th>
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
