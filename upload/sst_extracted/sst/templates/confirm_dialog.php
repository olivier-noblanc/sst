<?php
/**
 * Confirm Dialog Template — Application SST DREETS BFC
 * 
 * Inline confirmation dialog for abandon/delete actions.
 * Expects $report to be set with at least 'id', 'reference', 'type'.
 */
if (!isset($report) || !$report) {
    return;
}

$csrfToken = generateCsrfToken();
?>
<div class="confirm-box">
    <p>⚠️ Êtes-vous sûr de vouloir abandonner le signalement <strong><?php echo e($report['reference']); ?></strong> ?</p>
    <p style="font-size:13px;color:var(--grey-600);">Cette action est irréversible. Le signalement sera marqué comme abandonné.</p>
    <div class="confirm-box__actions">
        <form method="POST" action="<?php echo url('report_abandon', ['id' => $report['id']]); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
            <input type="hidden" name="report_id" value="<?php echo e($report['id']); ?>">
            <button type="submit" class="btn btn--danger">Oui, abandonner</button>
        </form>
        <a href="#" onclick="document.getElementById('abandon-form').style.display='none';return false;" class="btn btn--secondary">Annuler</a>
    </div>
</div>
