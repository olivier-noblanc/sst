<?php
/**
 * Attachment field — composant partagé d'upload de fichier.
 *
 * Utilisé par `templates/report_form.php` (création/édition) et
 * `pages/report_respond.php`. Il rend un `<input type="file">` HTML5 **visible**
 * (plus de faux bouton label/JS) : le contrôle natif du navigateur est le
 * bouton. Il l'accompagne du texte d'aide, de l'affichage du nom du fichier
 * sélectionné et d'un bouton local « Annuler la sélection » qui remet
 * uniquement `input.value = ''`.
 *
 * Aucun gestionnaire inline (`onclick=`) ni `<script>` inline : le seul script,
 * `public/js/attachment-input.js`, est servi par `js.php` (CSP
 * `script-src 'self'`). Le composant est pleinement fonctionnel sans JavaScript
 * (le contrôle natif et sa soumission ne dépendent d'aucun script).
 *
 * Variables (héritées de la portée parente via require) :
 *   $attachmentInputId           — id ET name de l'input (ex. 'attachment')
 *   $attachmentFieldLabel        — libellé visible du champ (ex. 'Pièce jointe')
 *   $attachmentHintText          — texte d'aide sous le contrôle
 *   $attachmentFilenameId        — id de l'affichage du nom du fichier choisi
 *   $attachmentGroupClass        — classe du conteneur (ex. 'form-group form-grid__full')
 *   $attachmentError             — message d'erreur de validation ('' si aucun)
 *   $attachmentCurrentName       — nom de la pièce jointe déjà stockée ('' si aucune)
 *   $attachmentShowRemoveCurrent — afficher la case « Supprimer la pièce jointe actuelle »
 */

/** @var string $attachmentInputId */
/** @var string $attachmentFieldLabel */
/** @var string $attachmentHintText */
/** @var string $attachmentFilenameId */
/** @var string $attachmentGroupClass */
/** @var string $attachmentError */
/** @var string $attachmentCurrentName */
/** @var bool $attachmentShowRemoveCurrent */

$attachmentHintId = 'hint_' . $attachmentInputId;
$attachmentErrorId = 'err_' . $attachmentInputId;
$attachmentResetId = $attachmentInputId . '_clear';
$attachmentHasError = $attachmentError !== '';
$attachmentDescribedBy = $attachmentHasError ? $attachmentErrorId : $attachmentHintId;
?>
<div class="<?php echo e($attachmentGroupClass); ?>">
    <label for="<?php echo e($attachmentInputId); ?>"><?php echo e($attachmentFieldLabel); ?></label>
    <div class="file-upload-wrapper">
        <input type="file"
               id="<?php echo e($attachmentInputId); ?>"
               name="<?php echo e($attachmentInputId); ?>"
               accept=".jpg,.jpeg,.png,.gif,.pdf"
               class="file-upload-wrapper__input"
               aria-describedby="<?php echo e($attachmentDescribedBy); ?>"
               <?php echo $attachmentHasError ? 'aria-invalid="true"' : ''; ?>>
        <span class="file-upload-wrapper__filename" id="<?php echo e($attachmentFilenameId); ?>" aria-live="polite">Aucun fichier sélectionné</span>
        <button type="button"
                id="<?php echo e($attachmentResetId); ?>"
                class="btn btn--secondary btn--sm file-upload-wrapper__reset"
                hidden>
            Annuler la sélection
        </button>
    </div>
    <span class="form-hint" id="<?php echo e($attachmentHintId); ?>"><?php echo e($attachmentHintText); ?></span>
    <?php if ($attachmentShowRemoveCurrent && $attachmentCurrentName !== ''): ?>
        <div class="attachment-preview">
            <span class="badge badge--confidential">&#128206; <?php echo e($attachmentCurrentName); ?></span>
            <label class="attachment-remove-label">
                <input type="checkbox" name="remove_attachment" value="1"> Supprimer la pièce jointe actuelle
            </label>
        </div>
    <?php endif; ?>
    <?php if ($attachmentHasError): ?>
        <span class="form-error" id="<?php echo e($attachmentErrorId); ?>"><?php echo e($attachmentError); ?></span>
    <?php endif; ?>
</div>