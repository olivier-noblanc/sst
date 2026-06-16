<?php
/**
 * Settings Tab: Notifications globales
 *
 * Variables attendues: $globalEmails, $csrfToken
 */
?>
<form method="POST" action="<?php echo url('settings'); ?>" id="settingsForm">
    <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
    <input type="hidden" name="tab" value="global">

    <div class="card">
        <h3 class="card__subtitle">Adresses e-mail de notification globales</h3>
        <p class="text-muted text-small mb-4">Ces adresses recevront des notifications pour tous les sites et tous les registres.</p>
        <div class="form-group">
            <label for="global_emails">Adresses e-mail</label>
            <?php $globalEmailList = []; foreach ($globalEmails as $ge) { $globalEmailList[] = $ge['email']; } ?>
            <textarea id="global_emails" name="global_emails" rows="4" class="form-control"
                      aria-describedby="hint_global_emails"
                      placeholder="Une adresse par ligne&#10;ex: direction@dreets.gouv.fr&#10;chsct@dreets.gouv.fr"><?php echo e(implode("\n", $globalEmailList)); ?></textarea>
            <div class="form-hint" id="hint_global_emails">Une adresse e-mail par ligne. Laissez vide pour aucune notification globale.</div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn--success">Enregistrer les modifications</button>
        <a href="<?php echo url('settings'); ?>" class="btn btn--outline">Annuler</a>
    </div>
</form>
