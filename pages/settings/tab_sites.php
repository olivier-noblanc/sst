<?php
/**
 * Settings Tab: Notifications par site
 *
 * Variables attendues: $sites, $siteEmails, $csrfToken
 *
 * @var string $csrfToken
 * @var list<array{id: int, code: string, nom: string}> $sites
 * @var array<int, list<array{id: int, email: string}>> $siteEmails
 */
?>
<form method="POST" action="<?php echo new \App\Services\HttpService()->url('settings'); ?>" id="settingsForm">
    <input type="hidden" name="csrf_token" value="<?php echo new \App\Services\FormattingService()->e($csrfToken); ?>">
    <input type="hidden" name="tab" value="sites">

    <?php foreach ($sites as $site): ?>
        <?php
        $siteIdStr = $site['id'] ?? '0';
        $sId = (int) $siteIdStr;
        $existingEmails = [];
        if (isset($siteEmails[$sId])) {
            foreach ($siteEmails[$sId] as $se) {
                $existingEmails[] = $se['email'];
            }
        }
        ?>
        <div class="card mb-4">
            <h3 class="card__subtitle">
                <span class="badge badge--rsst badge--sm"><?php echo new \App\Services\FormattingService()->e($site['code']); ?></span>
                <?php echo new \App\Services\FormattingService()->e($site['nom']); ?>
            </h3>
            <div class="form-group">
                <label for="site_emails_<?php echo new \App\Services\FormattingService()->e((string) $sId); ?>">Adresses e-mail de notification</label>
                <textarea id="site_emails_<?php echo new \App\Services\FormattingService()->e((string) $sId); ?>" name="site_emails[<?php echo new \App\Services\FormattingService()->e((string) $sId); ?>]"
                          rows="3" class="form-control"
                          aria-describedby="hint_site_emails_<?php echo new \App\Services\FormattingService()->e((string) $sId); ?>"
                          placeholder="Une adresse par ligne&#10;ex: jean.martin@dreets.gouv.fr&#10;sophie.dupont@dreets.gouv.fr"><?php echo new \App\Services\FormattingService()->e(implode("\n", $existingEmails)); ?></textarea>
                <div class="form-hint" id="hint_site_emails_<?php echo new \App\Services\FormattingService()->e((string) $sId); ?>">Une adresse e-mail par ligne. Laissez vide pour aucune notification.</div>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="form-actions">
        <button type="submit" class="btn btn--success">Enregistrer les modifications</button>
        <a href="<?php echo new \App\Services\HttpService()->url('settings'); ?>" class="btn btn--outline">Annuler</a>
    </div>
</form>
