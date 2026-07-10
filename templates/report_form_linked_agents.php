<?php
/**
 * Linked agents field — included by report_form.php
 *
 * Variables (inherited from parent scope via require):
 *   $user      — Current user array
 *   $isEdit    — Whether this is an edit form
 *   $report    — Existing report data for edit
 *   $formData  — Submitted form data for repopulation
 *   $formErrors — Array of field errors
 */
$declarantEmail = $user['email'] ?? '';
$emailDomain = '';
if ($declarantEmail && str_contains((string) $declarantEmail, '@')) {
    $emailDomain = substr((string) $declarantEmail, strrpos((string) $declarantEmail, '@') + 1);
}
$linkedEmails = '';
if ($isEdit && $report) {
    $existing = getLinkedAgents($pdo ?? getDB(), $report['uuid']);
    $linkedEmails = implode(', ', array_map(fn($a) => $a['email'], $existing));
}
if (isset($formData['linked_emails'])) {
    $linkedEmails = $formData['linked_emails'];
}
?>
        <div class="form-group form-grid__full">
            <label for="linked_emails">Rattacher des collègues au signalement</label>
            <input type="text" id="linked_emails" name="linked_emails"
                   class="form-control"
                   value="<?php echo e($linkedEmails); ?>"
                   placeholder="prenom.nom@<?php echo e($emailDomain); ?>"
                   autocomplete="off"
                   aria-describedby="hint_linked_emails">
            <span class="form-hint" id="hint_linked_emails">
                Adresses e-mail séparées par des virgules. Domaine autorisé : <strong>@<?php echo e($emailDomain); ?></strong>.
                Chaque collègue recevra un e-mail de confirmation — il devra cliquer pour confirmer son rattachement.
            </span>
            <?php if (isset($formErrors['linked_emails'])): ?>
                <span class="form-error"><?php echo e($formErrors['linked_emails']); ?></span>
            <?php endif; ?>
        </div>
