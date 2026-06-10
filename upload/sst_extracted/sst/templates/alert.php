<?php
/**
 * Alert Template — Application SST DREETS BFC
 * 
 * Displays flash messages (success, error, warning, info).
 * Call this template in the main layout after the sidebar.
 */
$flash = getFlash();
if ($flash):
    $type = $flash['type'] ?? 'info';
    $message = $flash['message'] ?? '';
?>
    <div class="alert alert--<?php echo e($type); ?>" role="alert">
        <?php echo e($message); ?>
    </div>
<?php endif; ?>
