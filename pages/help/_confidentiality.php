<!-- 3. Confidentialité des signalements -->
<div id="confidentialite" class="card card--spaced content-section">
    <h2>Confidentialité des signalements</h2>
    <p class="help-description">La visibilité des signalements dépend du réglage choisi par le superviseur, par registre (RSST<?php if ($ramiEnabled): ?>, RAMI<?php endif; ?><?php if ($dgiEnabled): ?>, DGI<?php endif; ?>).</p>
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
                    <td><span class="badge badge--chsct"><?php echo e(getRoleLabelShort('chsct')); ?></span></td>
                    <td>Tous les sites</td>
                    <td>Consultation de tous les signalements pour ses missions. Les consultations confidentielles sont enregistrées.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
