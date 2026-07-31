<?php

use App\Services\RegistryCardService;
use App\DTO\RegistryCardData;

/** Registry Card Renderer — HTML unifié pour les cartes de registre. */

function getRegistryCardService(): RegistryCardService
{
    return getContainer()->get(RegistryCardService::class);
}

function getRegistryIcon(string $type): string
{
    return getRegistryCardService()->getRegistryIcon($type);
}

function renderRegistryCard(RegistryCardData $card, string $extraClass = '', string $extraContent = ''): string
{
    $cssClass = 'registry-card ' . $card->cardClass;
    if ($extraClass !== '') {
        $cssClass .= ' ' . e($extraClass);
    }
    $count = $card->count;
    $countLabel = $count . ' signalement' . ($count !== 1 ? 's' : '')
                . ' enregistré' . ($count !== 1 ? 's' : '');
    $html = '<div class="' . $cssClass . '">';
    $html .= '<div>';
    $html .= '<div class="registry-card__icon">' . getRegistryIcon($card->type) . '</div>';
    $html .= '<div class="registry-card__title">' . e($card->title) . '</div>';
    $html .= '<div class="registry-card__subtitle">' . e($card->subtitle) . '</div>';
    $html .= '<p class="registry-card__desc">' . e($card->desc) . '</p>';
    $html .= '</div>';
    $html .= '<div>';
    $html .= '<a href="' . e($card->btnUrl) . '" class="registry-card__btn">' . e($card->btnLabel) . '</a>';
    $html .= '<a href="' . e($card->listUrl) . '" class="registry-card__link">' . e($card->listLabel) . '</a>';
    $html .= '<div class="registry-card__stat">' . $countLabel . '</div>';
    $html .= '</div>';
    if ($extraContent !== '') {
        $html .= '<div class="registry-card__extra">' . $extraContent . '</div>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * @param list<RegistryCardData> $cards
 * @param array<string, string> $extraContentMap
 */
function renderRegistryCards(array $cards, string $layout = 'compact', array $extraContentMap = []): string
{
    $gridClass = $layout === 'large' ? 'registry-cards registry-cards--large' : 'registry-cards';
    $extraClass = $layout === 'large' ? 'home-action--large' : '';
    $html = '<div class="' . $gridClass . '">';
    foreach ($cards as $card) {
        $extra = $extraContentMap[$card->type] ?? '';
        $html .= renderRegistryCard($card, $extraClass, $extra);
    }
    $html .= '</div>';
    return $html;
}

/**
 * Build registry cards from the database — works for all registres (core + custom).
 *
 * @return list<RegistryCardData>
 */
function buildRegistryCards(): array
{
    return getRegistryCardService()->buildRegistryCards();
}
