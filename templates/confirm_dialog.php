<?php
/**
 * Confirm Dialog Template — Application SST DREETS BFC
 *
 * Native HTML5 <dialog> content for abandon actions.
 * Expects $report to be set with at least 'uuid', 'reference', 'type'.
 * The parent <dialog> handles show/close natively.
 */
if (!isset($report) || !$report) {
    return;
}

$csrfToken = generateCsrfToken();
?>
<p>&#9888; Êtes-vous sûr de vouloir abandonner le signalement <strong><?php echo e($report['reference']); ?></strong> ?</p>
<p class="text-small text-muted">Cette action est irréversible. Le signalement sera marqué comme abandonné.</p>
<div class="confirm-dialog__actions">
    <form method="dialog" class="form--inline">
        <button type="submit" value="cancel" class="btn btn--secondary">Annuler</button>
    </form>
    <form method="POST" action="<?php echo url('report_abandon', ['uuid' => $report['uuid']]); ?>" class="form--inline">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
        <input type="hidden" name="report_uuid" value="<?php echo e($report['uuid']); ?>">
        <button type="submit" class="btn btn--danger">Oui, abandonner</button>
    </form>
</div>
