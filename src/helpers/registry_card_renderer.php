<?php

use App\Services\RegistryCardService;

/** Registry Card Renderer — HTML unifié pour les cartes de registre. */

function getRegistryIcon(string $type): string
{
    return new RegistryCardService()->getRegistryIcon($type);
}

/**
 * @param array<string, mixed> $card
 */
function renderRegistryCard(array $card, string $extraClass = '', string $extraContent = ''): string
{
    $cssClass = 'registry-card registry-card--' . e($card['type']);
    if ($extraClass !== '') {
        $cssClass .= ' ' . e($extraClass);
    }
    $count = $card['count'];
    $countLabel = $count . ' signalement' . ($count !== 1 ? 's' : '')
                . ' enregistré' . ($count !== 1 ? 's' : '');
    $html = '<div class="' . $cssClass . '">';
    $html .= '<div>';
    $html .= '<div class="registry-card__icon">' . getRegistryIcon($card['type']) . '</div>';
    $html .= '<div class="registry-card__title">' . e($card['title']) . '</div>';
    $html .= '<div class="registry-card__subtitle">' . e($card['subtitle']) . '</div>';
    $html .= '<p class="registry-card__desc">' . e($card['desc']) . '</p>';
    $html .= '</div>';
    $html .= '<div>';
    $html .= '<a href="' . e($card['btnUrl']) . '" class="registry-card__btn">' . e($card['btnLabel']) . '</a>';
    $html .= '<a href="' . e($card['listUrl']) . '" class="registry-card__link">' . e($card['listLabel']) . '</a>';
    $html .= '<div class="registry-card__stat">' . $countLabel . '</div>';
    $html .= '</div>';
    if ($extraContent !== '') {
        $html .= '<div class="registry-card__extra">' . $extraContent . '</div>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * @param list<array<string, mixed>> $cards
 * @param array<string, string> $extraContentMap
 */
function renderRegistryCards(array $cards, string $layout = 'compact', array $extraContentMap = []): string
{
    $gridClass = $layout === 'large' ? 'registry-cards registry-cards--large' : 'registry-cards';
    $extraClass = $layout === 'large' ? 'home-action--large' : '';
    $html = '<div class="' . $gridClass . '">';
    foreach ($cards as $card) {
        $extra = $extraContentMap[$card['type']] ?? '';
        $html .= renderRegistryCard($card, $extraClass, $extra);
    }
    $html .= '</div>';
    return $html;
}

/**
 * Build registry cards from the database — works for all registres (core + custom).
 *
 * @return list<array{type: string, title: string, subtitle: string, desc: string, count: int, btnLabel: string, btnUrl: string, listUrl: string, listLabel: string}>
 */
function buildRegistryCards(): array
{
    return new RegistryCardService()->buildRegistryCards();
}
