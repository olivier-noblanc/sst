<?php
/**
 * Settings Tab: Configuration SMTP
 *
 * Variables attendues: $csrfToken
 */
?>
<form method="POST" action="<?php echo (new \App\Services\HttpService())->url('settings'); ?>">
    <input type="hidden" name="csrf_token" value="<?php echo (new \App\Services\FormattingService())->e($csrfToken); ?>">
    <input type="hidden" name="tab" value="smtp">

    <div class="card">
        <h3 class="card__title">&#x1F4E7; Configuration SMTP</h3>
        <p class="text-muted text-small mb-5">Configurez le serveur SMTP pour l'envoi des e-mails de notification.</p>

        <?php if (!(new \App\Services\CryptoService())->isEncryptionAvailable()): ?>
        <div class="alert alert--warning mb-5">
            <strong>Chiffrement non actif.</strong> La variable d'environnement <code>SST_SECRET_KEY</code> n'est pas configurée ou est trop courte (32 caractères minimum). Le mot de passe SMTP sera stocké en clair dans la base de données. Configurez cette variable dans IIS pour activer le chiffrement AES-256-CBC.
        </div>
        <?php endif; ?>

        <div class="form-row form-row--2-1">
            <div class="form-group">
                <label for="smtp_host">Serveur SMTP</label>
                <input type="text" id="smtp_host" name="smtp_host" class="form-control"
                       value="<?php echo (new \App\Services\FormattingService())->e(\App\Services\ConfigService::getInstance()->get('smtp_host')); ?>"
                       placeholder="smtp.example.com">
            </div>
            <div class="form-group">
                <label for="smtp_port">Port SMTP</label>
                <input type="number" id="smtp_port" name="smtp_port" class="form-control"
                       value="<?php echo (new \App\Services\FormattingService())->e(\App\Services\ConfigService::getInstance()->get('smtp_port', '25')); ?>"
                       placeholder="25">
            </div>
        </div>

        <div class="form-row form-row--1-1">
            <div class="form-group">
                <label for="smtp_user">Utilisateur SMTP</label>
                <input type="text" id="smtp_user" name="smtp_user" class="form-control"
                       value="<?php echo (new \App\Services\FormattingService())->e(\App\Services\ConfigService::getInstance()->get('smtp_user')); ?>"
                       placeholder="utilisateur@exemple.com">
            </div>
            <div class="form-group">
                <label for="smtp_pass">Mot de passe SMTP</label>
                <input type="password" id="smtp_pass" name="smtp_pass" class="form-control"
                       value=""
                       placeholder="<?php echo \App\Services\ConfigService::getInstance()->get('smtp_pass') ? '•••••••• (laisser vide pour conserver)' : 'Non défini'; ?>">
            </div>
        </div>

        <div class="form-row form-row--1-1">
            <div class="form-group">
                <label for="smtp_from">Adresse d'expédition</label>
                <input type="email" id="smtp_from" name="smtp_from" class="form-control"
                       value="<?php echo (new \App\Services\FormattingService())->e(\App\Services\ConfigService::getInstance()->get('smtp_from')); ?>"
                       placeholder="noreply@dreets-bfc.gouv.fr">
            </div>
            <div class="form-group">
                <label for="smtp_encryption">Chiffrement</label>
                <select id="smtp_encryption" name="smtp_encryption" class="form-control">
                    <?php
                        $currentEncryption = \App\Services\ConfigService::getInstance()->get('smtp_encryption', 'none');
$options = ['none' => 'Aucun', 'tls' => 'TLS', 'starttls' => 'STARTTLS'];
?>
                    <?php foreach ($options as $val => $label): ?>
                    <option value="<?php echo (new \App\Services\FormattingService())->e($val); ?>" <?php echo $currentEncryption === $val ? 'selected' : ''; ?>>
                        <?php echo (new \App\Services\FormattingService())->e($label); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn--success">Enregistrer les modifications</button>
        <a href="<?php echo (new \App\Services\HttpService())->url('settings', ['tab' => 'smtp']); ?>" class="btn btn--outline">Annuler</a>
    </div>
</form>

<!-- SMTP Test (separate form — POST + redirect, no JavaScript) -->
<form method="POST" action="<?php echo (new \App\Services\HttpService())->url('smtp_test'); ?>" class="smtp-test-section">
    <input type="hidden" name="csrf_token" value="<?php echo (new \App\Services\FormattingService())->e($csrfToken); ?>">
    <div class="card smtp-test-section">
        <h4 class="card__subtitle">&#x1F9EA; Test d'envoi SMTP</h4>
        <p class="text-muted text-small mb-3">Envoyez un e-mail de test pour vérifier la configuration SMTP ci-dessus.</p>
        <div class="smtp-test-row">
            <div class="form-group smtp-test-field">
                <label for="smtp_test_to">Adresse destinataire</label>
                <input type="email" id="smtp_test_to" name="smtp_test_to" class="form-control"
                       placeholder="destinataire@exemple.com" required>
            </div>
            <button type="submit" class="btn btn--outline">Envoyer un e-mail de test</button>
        </div>
    </div>
</form>
