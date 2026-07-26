<?php

/** RegistryCardService — Couche métier pour les cartes de registre. */

namespace App\Services;

use App\Enum\VisibilityMode;
use App\Repository\RegistryRepository;
use App\Repository\ReportRepository;

class RegistryCardService
{
    public function __construct(
        private readonly RegistryRepository $registryRepo,
        private readonly ReportRepository $reportRepo,
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
        $icon = $reg !== null ? ($reg['icon'] ?? '📋') : '📋';
        $cache[$type] = $icon;
        return $icon;
    }

    /**
     * @return list<array{type: string, title: string, subtitle: string, desc: string, count: int, btnLabel: string, btnUrl: string, listUrl: string, listLabel: string}>
     */
    public function buildRegistryCards(): array
    {
        $enabledRegistries = $this->registryRepo->findEnabled();

        $user = new SessionService()->getUserSession();
        /** @var string */
        $userIdStr = $user['id'] ?? '0';
        $userId = (int) $userIdStr;
        /** @var string */
        $siteIdStr = $user['site_id'] ?? '0';
        $userSiteId = (int) $siteIdStr;
        $agentVisibility = $this->accessService->getReportVisibility(null);
        $seeAllSites = $this->accessService->canSeeAllSites();

        $listLabel = static fn(string $type): string => \getReportVisibility($type) === VisibilityMode::Confidential->value
            ? 'Voir mes signalements'
            : 'Voir les signalements';

        $cards = [];
        foreach ($enabledRegistries as $reg) {
            $code = $reg['code'];
            $reportCount = 0;

            if ($agentVisibility === VisibilityMode::Confidential->value || $agentVisibility === VisibilityMode::AgentChoice->value) {
                $reportCount = $this->reportRepo->countVisibleForAgent($code, $userId, $userSiteId, $agentVisibility);
            } else {
                $siteIdFilter = $seeAllSites ? 0 : $userSiteId;
                $reportCount = $this->reportRepo->countActive($code, $siteIdFilter);
            }

            $cards[] = [
                'type'     => $code,
                'title'    => $reg['label'],
                'subtitle' => $reg['short_label'],
                'desc'     => $reg['description'] ?? '',
                'count'    => $reportCount,
                'btnLabel' => $reg['btn_label'] ?? 'Signaler un événement',
                'btnUrl'   => url('report_create', ['type' => $code]),
                'listUrl'  => url('report_list', ['type' => $code]),
                'listLabel' => $listLabel($code),
            ];
        }
        return $cards;
    }
}
