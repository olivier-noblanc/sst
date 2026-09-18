<?php

/** RegistryCardService — Couche métier pour les cartes de registre. */

namespace App\Services;

use App\DTO\RegistryCardData;
use App\Enum\UserRole;
use App\Enum\VisibilityMode;
use App\Repository\RegistryRepository;
use App\Repository\ReportAgentRepository;
use App\Repository\StatsRepository;

class RegistryCardService
{
    public function __construct(
        private readonly RegistryRepository $registryRepo,
        private readonly AccessService $accessService,
    ) {}

    public function getRegistryIcon(string $type): string
    {
        // Audit #71 — Cache registry data within a single request to avoid
        // repeated DB queries. Before this fix, each call to getRegistryIcon
        // (called multiple times per page load) hit the DB separately.
        static $cache = [];
        if (isset($cache[$type])) {
            return $cache[$type];
        }
        $reg = $this->registryRepo->findByCode($type);
        $icon = $reg !== null ? $reg['icon'] : '📋';
        $cache[$type] = $icon;
        return $icon;
    }

    /**
     * @return list<RegistryCardData>
     */
    public function buildRegistryCards(): array
    {
        $enabledRegistries = $this->registryRepo->findEnabled();

        $user = new SessionService()->getUserSession();
        $userId = $user->id ?? 0;
        $userSiteId = $user->siteId ?? 0;
        $seeAllSites = $this->accessService->canSeeAllSites();

        // Périmètre CHSCT (app_chsct_report_scope=consent_only) : la liste
        // (AccessService::buildListFilter) restreint le CHSCT aux signalements
        // consentis (consent_syndicat = 1). Le compteur de carte doit appliquer
        // le même filtre, sinon il annonce des signalements absents de la liste.
        // Un superviseur n'est jamais soumis à cette portée.
        $chsctConsentOnly = ($user->role ?? '') === UserRole::Chsct->value
            && $this->accessService->getChsctReportScope() === 'consent_only';

        $listLabel = static fn(string $type): string => \getReportVisibility($type) === VisibilityMode::Confidential->value
            ? 'Voir mes signalements'
            : 'Voir les signalements';

        $cards = [];
        foreach ($enabledRegistries as $reg) {
            $code = $reg['code'];
            $reportCount = 0;

            // Visibilité PAR REGISTRE, comme report_list.php : un registre peut
            // avoir sa propre app_report_visibility_{code} (ou
            // registries.default_visibility pour un registre personnalisé), qui
            // diffère de la visibilité globale. Utiliser null ici appliquait la
            // visibilité globale à toutes les cartes et faisait diverger le
            // compteur du contenu réel de la liste liée.
            $agentVisibility = $this->accessService->getReportVisibility($code);

            if ($agentVisibility === VisibilityMode::Confidential->value || $agentVisibility === VisibilityMode::AgentChoice->value) {
                $reportCount = ReportAgentRepository::instance()->countVisibleForAgent($code, $userId, $userSiteId, $agentVisibility);
            } else {
                $siteIdFilter = $seeAllSites ? 0 : $userSiteId;
                $reportCount = StatsRepository::instance()->countActive($code, $siteIdFilter, 0, false, $chsctConsentOnly);
            }

            $colorTheme = $reg['color_theme'];
            $cards[] = RegistryCardData::create(
                type: $code,
                cardClass: RegistryRepository::themeClasses($colorTheme)['registry_card'],
                title: $reg['label'],
                subtitle: $reg['short_label'],
                desc: $reg['description'],
                count: $reportCount,
                btnLabel: $reg['btn_label'] ?? 'Signaler un événement',
                btnUrl: url('report_create', ['type' => $code]),
                listUrl: url('report_list', ['type' => $code]),
                listLabel: $listLabel($code),
            );
        }
        return $cards;
    }
}
