<?php

/**
 * Rendering helpers — Application SST DREETS BFC
 *
 * Extracted from formatting.php for single-responsibility clarity.
 */

/**
 * Render a breadcrumb navigation bar.
 *
 * Replaces the duplicated breadcrumb HTML pattern across 6 files.
 * Each item is either a link ['url' => ..., 'label' => ...] or
 * a plain text current item ['label' => ...] (rendered as <span>).
 *
 * @param array<int, array{url?: string, label: string}> $items  Ordered list of breadcrumb items
 * @return string  HTML for the breadcrumb nav
 */
function renderBreadcrumb(array $items): string
{
    $html = '<nav class="breadcrumb" aria-label="Fil d\'Ariane">';
    $last = count($items) - 1;
    foreach ($items as $i => $item) {
        if ($i === $last) {
            // Current page — plain text
            $html .= '<span class="breadcrumb__current">' . e($item['label']) . '</span>';
        } else {
            // Clickable link
            $html .= '<a href="' . e($item['url']) . '" class="breadcrumb__item">' . e($item['label']) . '</a>';
            $html .= '<span class="breadcrumb__separator">/</span>';
        }
    }
    $html .= '</nav>';
    return $html;
}

/**
 * Render a template with variables.
 * @param array<string, mixed> $data  Variables available in the template
 */
function render(string $template, array $data = []): void
{
    extract($data);
    require __DIR__ . '/../../templates/' . $template . '.php';
}
