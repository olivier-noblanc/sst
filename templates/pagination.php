<?php
/**
 * Pagination Template — Application SST DREETS BFC
 * 
 * Reusable pagination component.
 * 
 * Required variables:
 *   $totalItems  — Total number of items
 *   $currentPage — Current page number (1-based)
 *   $perPage     — Items per page
 *   $baseUrl     — Base URL for pagination links (without &p=)
 */

if (!isset($totalItems) || !isset($currentPage) || !isset($perPage) || !isset($baseUrl)) {
    return;
}

/** @var int $totalItems */
/** @var int $currentPage */
/** @var int $perPage */
/** @var string $baseUrl */
$totalPages = (int) ceil($totalItems / $perPage);

if ($totalPages <= 1) {
    return;
}

// Clamp current page
$currentPage = max(1, min($currentPage, $totalPages));

// Build page links
$pages = [];
$startPage = max(1, $currentPage - 2);
$endPage = min($totalPages, $currentPage + 2);

// Always show first page
if ($startPage > 1) {
    $pages[] = 1;
    if ($startPage > 2) {
        $pages[] = '...';
    }
}

for ($i = $startPage; $i <= $endPage; $i++) {
    $pages[] = $i;
}

// Always show last page
if ($endPage < $totalPages) {
    if ($endPage < $totalPages - 1) {
        $pages[] = '...';
    }
    $pages[] = $totalPages;
}
?>
<nav class="pagination" role="navigation" aria-label="Pagination">
    <?php if ($currentPage > 1): ?>
        <a href="<?php echo e($baseUrl); ?>&p=<?php echo $currentPage - 1; ?>" class="pagination__link" aria-label="Page précédente">← Précédent</a>
    <?php endif; ?>

    <?php foreach ($pages as $page): ?>
        <?php if ($page === '...'): ?>
            <span class="pagination__ellipsis">…</span>
        <?php elseif ($page == $currentPage): ?>
            <span class="pagination__current" aria-current="page"><?php echo $page; ?></span>
        <?php else: ?>
            <a href="<?php echo e($baseUrl); ?>&p=<?php echo $page; ?>" class="pagination__link" aria-label="Page <?php echo $page; ?>"><?php echo $page; ?></a>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php if ($currentPage < $totalPages): ?>
        <a href="<?php echo e($baseUrl); ?>&p=<?php echo $currentPage + 1; ?>" class="pagination__link" aria-label="Page suivante">Suivant →</a>
    <?php endif; ?>
</nav>
