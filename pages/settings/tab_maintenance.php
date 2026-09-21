<?php
/**
 * Settings Tab: Maintenance — purge supervisée des signalements.
 *
 * Onglet accessible uniquement via pages/settings.php, lui-même réservé au
 * rôle Superviseur. Le bouton est TOUJOURS visible : la présence de la
 * sentinelle erase.txt est vérifiée au dernier moment, côté POST
 * (handlers/purge_reports_handler.php), pas au rendu.
 *
 * Variables attendues: $csrfToken
 */
use App\Services\FormattingService;
use App\Services\HttpService;

$fmt = new FormattingService();
$http = new HttpService();
/** @var string $csrfToken */
?>
<div class="card card--danger">
    <h3 class="card__title">&#x2622;&#xFE0F; Purge des signalements</h3>
    <p class="text-muted text-small mb-5">
        Supprime définitivement tous les signalements et leurs données liées
        (réponses, rattachements, invitations, historique, valeurs de champs de
        registre et journal d'audit), ainsi que la file d'attente des e-mails et
        les sessions. Les comptes utilisateurs, les
        <?php echo $fmt->e(getConfigService()->get('app_label_unite', 'UR')); ?>s,
        la configuration et les registres sont conservés.
    </p>

    <div class="info-panel info-panel--warning" role="note">
        &#x26A0;&#xFE0F; <strong>Opération irréversible.</strong>
        Elle n'est possible que si un technicien a préalablement déposé un fichier
        <code>erase.txt</code> à la racine de l'application. Ce fichier est
        supprimé automatiquement après une purge réussie ; en son absence, la
        purge est refusée et rien n'est supprimé.
    </div>

    <form method="POST" action="<?php echo $fmt->e($http->url('purge_reports')); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo $fmt->e($csrfToken); ?>">
        <div class="form-group">
            <label class="toggle-switch-label">
                <input type="checkbox" name="confirm_purge" value="1" required>
                <span>Je confirme vouloir purger définitivement tous les signalements.</span>
            </label>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn--danger">Purger les signalements</button>
        </div>
    </form>
</div>