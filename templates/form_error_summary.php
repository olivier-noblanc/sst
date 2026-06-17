<?php
/**
 * Form Error Summary Template — Application SST DREETS BFC
 *
 * Displays a summary of all form validation errors at the top of a form.
 * Designed for accessibility: focus is moved to this element on page load
 * so screen readers announce the errors immediately.
 *
 * Required variables:
 *   $formErrors — Associative array of field => error message
 *
 * CSS-only, zero JavaScript. The autofocus attribute is used for focus management.
 */
if (empty($formErrors) || !is_array($formErrors)) {
    return;
}
?>
<div class="form-error-summary" role="alert" tabindex="-1" autofocus>
    <p class="form-error-summary__title">&#x26A0;&#xFE0F; Le formulaire contient <?php echo count($formErrors); ?> erreur<?php echo count($formErrors) > 1 ? 's' : ''; ?></p>
    <ul class="form-error-summary__list">
        <?php foreach ($formErrors as $field => $message): ?>
        <li><a href="#<?php echo e($field); ?>" class="form-error-summary__link"><?php echo e($message); ?></a></li>
        <?php endforeach; ?>
    </ul>
</div>
