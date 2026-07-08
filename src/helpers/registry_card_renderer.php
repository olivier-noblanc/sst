<?php
/** Registry Card Renderer — HTML unifié pour les cartes de registre. */

function getRegistryIcon(string $type): string
{
    return match ($type) {
        'rsst' => '📋',
        'rami' => '⚠️',
        'dgi'  => '🔴',
        default => '📋',
    };
}

function renderRegistryCard(array $card, string $extraClass = ''): string
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
    $html .= '<a href="' . e($card['listUrl']) . '" class="registry-card__link">Voir les signalements</a>';
    $html .= '<div class="registry-card__stat">' . $countLabel . '</div>';
    $html .= '</div>';
    $html .= '</div>';
    return $html;
}

function renderRegistryCards(array $cards, string $layout = 'compact'): string
{
    $gridClass = $layout === 'large' ? 'registry-cards registry-cards--large' : 'registry-cards';
    $extraClass = $layout === 'large' ? 'home-action--large' : '';
    $html = '<div class="' . $gridClass . '">';
    foreach ($cards as $card) {
        $html .= renderRegistryCard($card, $extraClass);
    }
    $html .= '</div>';
    return $html;
}

function buildRegistryCards(int $rsstCount, int $ramiCount, int $dgiCount, bool $ramiEnabled, bool $dgiEnabled): array
{
    $cards = [];
    $cards[] = [
        'type' => 'rsst', 'title' => 'Registre de Santé et de Sécurité au Travail',
        'subtitle' => 'RSST', 'desc' => getConfig('app_rsst_description', 'Risques liés aux locaux, équipements, ergonomie, conditions environnementales'),
        'count' => $rsstCount, 'btnLabel' => 'Déposer un signalement',
        'btnUrl' => url('report_create', ['type' => TYPE_RSST]), 'listUrl' => url('report_list', ['type' => TYPE_RSST]),
    ];
    if ($ramiEnabled) {
        $cards[] = [
            'type' => 'rami', 'title' => 'Registre des Actes d\'Agressions, de Menaces et d\'Incivilités',
            'subtitle' => 'RAMI', 'desc' => 'Agressions physiques ou verbales, menaces, incivilités, harcèlement',
            'count' => $ramiCount, 'btnLabel' => 'Signaler une agression',
            'btnUrl' => url('report_create', ['type' => TYPE_RAMI]), 'listUrl' => url('report_list', ['type' => TYPE_RAMI]),
        ];
    }
    if ($dgiEnabled) {
        $cards[] = [
            'type' => 'dgi', 'title' => 'Registre de signalement d\'un Danger Grave et Imminent',
            'subtitle' => 'DGI', 'desc' => 'Danger nécessitant une action immédiate, droit de retrait',
            'count' => $dgiCount, 'btnLabel' => 'Signaler un danger urgent',
            'btnUrl' => url('report_create', ['type' => TYPE_DGI]), 'listUrl' => url('report_list', ['type' => TYPE_DGI]),
        ];
    }
    return $cards;
}
