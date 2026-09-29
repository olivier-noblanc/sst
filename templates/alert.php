<?php
/**
 * Alert Template — Application SST DREETS BFC
 * 
 * Displays flash messages (success, error, warning, info).
 * Called by the layout from inside <main id="main-content">, so the alert
 * inherits the content area's sidebar offset and padding instead of being
 * hidden underneath the fixed sidebar.
 */
$flash = getFlash();
if ($flash !== null):
    $type = $flash->type;
    $message = $flash->message;
?>
    <div class="alert alert--<?php echo e($type); ?>" role="alert">
        <?php echo e($message); ?>
    </div>
<?php endif; ?>
