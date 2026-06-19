<!-- 4. Les registres -->
<div id="registres" class="card card--spaced content-section">
    <h2>Les <?php echo $registryCount; ?> registres</h2>
    <p class="help-description">L'application gère <strong><?php echo $registryCount; ?> registre<?php echo $registryCount > 1 ? 's' : ''; ?></strong> distinct<?php echo $registryCount > 1 ? 's' : ''; ?> pour la santé et sécurité au travail.</p>

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

        <?php if ($ramiEnabled): ?>
        <div class="help-profile-card help-profile-card--rami">
            <h3>RAMI</h3>
            <p class="help-description help-description--title">Registre des Actes d'Agressions, de Menaces et d'Incivilités</p>
            <p class="help-description">Signalez les agressions, menaces ou incivilités. Vous pouvez aussi signaler pour un collègue.</p>
            <ul class="help-feature-list">
                <li>🤝 Champ « <strong>Pour le compte de</strong> » : signaler pour un tiers</li>
                <li>🏷️ Nature de l'auteur (usager, collègue…) et type d'acte (verbal, physique…) — optionnels</li>
            </ul>
        </div>
        <?php endif; ?>

        <?php if ($dgiEnabled): ?>
        <div class="help-profile-card help-profile-card--dgi">
            <h3>DGI</h3>
            <p class="help-description help-description--title">Registre de signalement d'un Danger Grave et Imminent</p>
            <p class="help-description">Signalez un danger grave nécessitant une action immédiate. Les superviseurs sont alertés tout de suite.</p>
            <ul class="help-feature-list">
                <li>⚡ Traitement <strong>prioritaire</strong> et notification immédiate</li>
                <li>⚖️ Le formulaire vaut notification au sens <strong>L4131-1</strong> (droit de retrait). La consignation <strong>D4132-1</strong> reste du ressort du <?php echo e(getRoleLabelShort('chsct')); ?>.</li>
            </ul>
        </div>
        <?php endif; ?>
    </div>

    <?php echo helpScreenshot($screenshotBase . '/cu2-creation-rsst.html', "Formulaire de création d'un signalement RSST"); ?>
    <?php if ($ramiEnabled): echo helpScreenshot($screenshotBase . '/cu3-creation-rami.html', "Formulaire de création d'un signalement RAMI avec le champ « Pour le compte de »"); endif; ?>
    <?php if ($dgiEnabled): echo helpScreenshot($screenshotBase . '/cu4-creation-dgi.html', "Formulaire de création d'un signalement DGI avec le bandeau d'avertissement"); endif; ?>
</div>
