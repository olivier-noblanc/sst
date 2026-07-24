<?php

use App\Enum\ReportType;
use App\Enum\VisibilityMode;

/** Registry Card Renderer — HTML unifié pour les cartes de registre. */

function getRegistryIcon(string $type): string
{
    return match ($type) {
        ReportType::Rsst->value => '📋',
        ReportType::Rami->value => '⚠️',
        ReportType::Dgi->value  => '🔴',
        default => '📋',
    };
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
 * @return list<array{type: string, title: string, subtitle: string, desc: string, count: int, btnLabel: string, btnUrl: string, listUrl: string, listLabel: string}>
 */
function buildRegistryCards(int $rsstCount, int $ramiCount, int $dgiCount, bool $ramiEnabled, bool $dgiEnabled): array
{
    // "Voir mes signalements" only when this user's effective visibility for
    // that registry type is 'confidential' — the one mode where the report
    // list is actually filtered to their own reports only (own_only, see
    // pages/report_list.php). getReportVisibility() (not
    // reportVisibilityIsConfidential(), which ignores role and just reads
    // the raw config) already returns 'all' for superviseur/chsct
    // regardless of the agent-facing config, so they always get "Voir les
    // signalements". 'agent_choice' and 'public' both show a wider set for
    // agents too (linked/non-confidential reports, or everyone at the
    // site) — "Voir les signalements" stays accurate for those.
    $listLabel = static fn(string $type): string => \getReportVisibility($type) === VisibilityMode::Confidential->value
        ? 'Voir mes signalements'
        : 'Voir les signalements';

    $cards = [];
    $cards[] = [
        'type' => ReportType::Rsst->value, 'title' => 'Registre de Santé et de Sécurité au Travail',
        'subtitle' => 'RSST', 'desc' => getConfig('app_rsst_description', 'Risques liés aux locaux, équipements, ergonomie, conditions environnementales'),
        'count' => $rsstCount, 'btnLabel' => 'Déposer un signalement',
        'btnUrl' => url('report_create', ['type' => ReportType::Rsst->value]), 'listUrl' => url('report_list', ['type' => ReportType::Rsst->value]),
        'listLabel' => $listLabel(ReportType::Rsst->value),
    ];
    if ($ramiEnabled) {
        $cards[] = [
            'type' => ReportType::Rami->value, 'title' => 'Registre des Actes d\'Agressions, de Menaces et d\'Incivilités',
            'subtitle' => 'RAMI', 'desc' => 'Agressions physiques ou verbales, menaces, incivilités, harcèlement',
            'count' => $ramiCount, 'btnLabel' => 'Signaler une agression',
            'btnUrl' => url('report_create', ['type' => ReportType::Rami->value]), 'listUrl' => url('report_list', ['type' => ReportType::Rami->value]),
            'listLabel' => $listLabel(ReportType::Rami->value),
        ];
    }
    if ($dgiEnabled) {
        $cards[] = [
            'type' => ReportType::Dgi->value, 'title' => 'Registre de signalement d\'un Danger Grave et Imminent',
            'subtitle' => 'DGI', 'desc' => 'Danger nécessitant une action immédiate, droit de retrait',
            'count' => $dgiCount, 'btnLabel' => 'Signaler un danger urgent',
            'btnUrl' => url('report_create', ['type' => ReportType::Dgi->value]), 'listUrl' => url('report_list', ['type' => ReportType::Dgi->value]),
            'listLabel' => $listLabel(ReportType::Dgi->value),
        ];
    }
    return $cards;
}
