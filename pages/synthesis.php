<?php
/**
 * Synthesis Page — Application SST DREETS BFC
 *
 * Summary table across all registries, showing counts by site, registry type, and state.
 * Access: superviseur, chsct
 */
requireRole([ROLE_SUPERVISEUR]);

$noSiteMode = \App\Services\ConfigService::getInstance()->isNoSiteMode();

// Get filter parameters
$year = $_GET['year'] ?? date('Y');
$siteId = (int) ($_GET['site'] ?? 0);

// Get available years
$availableYears = \App\Repository\StatsRepository::instance()->getAvailableYears();
if (empty($availableYears)) {
    $availableYears = [date('Y')];
}

// Get sites for filter
$sites = \App\Repository\SiteRepository::instance()->findAll();

// Get synthesis data
$synthesisData = \App\Repository\StatsRepository::instance()->getSynthesis($year, $siteId);

// Organize data by site
$siteData = [];
foreach ($sites as $site) {
    $siteData[$site['id']] = [
        'code' => $site['code'],
        'nom' => $site['nom'],
        'rsst' => ['nouveau' => 0, 'en_cours' => 0, 'traite' => 0, 'abandonne' => 0, 'total' => 0],
        'rami' => ['nouveau' => 0, 'en_cours' => 0, 'traite' => 0, 'abandonne' => 0, 'total' => 0],
        'dgi'  => ['nouveau' => 0, 'en_cours' => 0, 'traite' => 0, 'abandonne' => 0, 'total' => 0],
    ];
}

// Fill in the data
foreach ($synthesisData as $row) {
    $sId = (int) $row['site_id'];
    $type = $row['type'] ?? '';
    if (isset($siteData[$sId]) && isset($siteData[$sId][$type])) {
        $siteData[$sId][$type] = [
            'nouveau'   => (int) $row['nouveau'],
            'en_cours'  => (int) $row['en_cours'],
            'traite'    => (int) $row['traite'],
            'abandonne' => (int) $row['abandonne'],
            'total'     => (int) $row['total'],
        ];
    }
}

// Calculate totals
$totals = [
    'rsst' => ['nouveau' => 0, 'en_cours' => 0, 'traite' => 0, 'abandonne' => 0, 'total' => 0],
    'rami' => ['nouveau' => 0, 'en_cours' => 0, 'traite' => 0, 'abandonne' => 0, 'total' => 0],
    'dgi'  => ['nouveau' => 0, 'en_cours' => 0, 'traite' => 0, 'abandonne' => 0, 'total' => 0],
];

foreach ($siteData as $sId => $sd) {
    foreach (['rsst', 'rami', 'dgi'] as $type) {
        foreach (['nouveau', 'en_cours', 'traite', 'abandonne', 'total'] as $state) {
            $totals[$type][$state] += $sd[$type][$state];
        }
    }
}

$grandTotal = 0;
foreach (['rsst', 'rami', 'dgi'] as $type) {
    $grandTotal += $totals[$type]['total'];
}

$pageTitle = 'Synthèse des signalements';

$ramiEnabled = \App\Services\ConfigService::getInstance()->isRegistryEnabled(TYPE_RAMI);
$dgiEnabled = \App\Services\ConfigService::getInstance()->isRegistryEnabled(TYPE_DGI);

// Build list of active registry types for the table columns
$activeTypes = ['rsst' => 'RSST'];
if ($ramiEnabled) {
    $activeTypes['rami'] = 'RAMI';
}
if ($dgiEnabled) {
    $activeTypes['dgi'] = 'DGI';
}
$colSpan = count($activeTypes) * 4;
?>

<h1 class="page-title">Synthèse des signalements</h1>


<!-- Filter Bar -->
<form method="GET" action="<?php echo (new \App\Services\HttpService())->url('synthesis'); ?>" class="filter-bar">
    <div class="form-group">
        <label for="year">Année</label>
        <select name="year" id="year">
            <?php foreach ($availableYears as $y): ?>
            <option value="<?php echo (new \App\Services\FormattingService())->e($y); ?>" <?php echo $y == $year ? 'selected' : ''; ?>><?php echo (new \App\Services\FormattingService())->e($y); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php if (!$noSiteMode): ?>
    <div class="form-group">
        <label for="site"><?php echo (new \App\Services\FormattingService())->e(\App\Services\ConfigService::getInstance()->get('app_label_unite', 'UR')); ?></label>
        <select name="site" id="site">
            <option value="0" <?php echo $siteId === 0 ? 'selected' : ''; ?>>Tous</option>
            <?php foreach ($sites as $s): ?>
            <option value="<?php echo (int) $s['id']; ?>" <?php echo $siteId === (int) $s['id'] ? 'selected' : ''; ?>><?php echo (new \App\Services\FormattingService())->e($s['nom']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <div class="form-group align-self-end">
        <button type="submit" class="btn btn--outline">Filtrer</button>
    </div>
</form>

<!-- Synthesis Table -->
<?php if (!$noSiteMode): ?>
<div class="card">
    <div class="table-wrapper">
        <table class="synthesis-table" aria-label="Synthèse des signalements par site">
            <thead>
                <tr>
                    <th rowspan="2"><?php echo (new \App\Services\FormattingService())->e(\App\Services\ConfigService::getInstance()->get('app_label_unite', 'UR')); ?></th>
                    <th colspan="4" class="synthesis-th-rsst">RSST</th>
                    <?php if ($ramiEnabled): ?><th colspan="4" class="synthesis-th-rami">RAMI</th><?php endif; ?>
                    <?php if ($dgiEnabled): ?><th colspan="4" class="synthesis-th-dgi">DGI</th><?php endif; ?>
                    <th rowspan="2">Total</th>
                </tr>
                <tr>
                    <th>Nouv.</th><th>En cours</th><th>Traité</th><th>Total</th>
                    <?php if ($ramiEnabled): ?><th>Nouv.</th><th>En cours</th><th>Traité</th><th>Total</th><?php endif; ?>
                    <?php if ($dgiEnabled): ?><th>Nouv.</th><th>En cours</th><th>Traité</th><th>Total</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($siteData as $sId => $sd): ?>
                <tr>
                    <td data-label="<?php echo (new \App\Services\FormattingService())->e(\App\Services\ConfigService::getInstance()->get('app_label_unite', 'UR')); ?>"><strong><?php echo (new \App\Services\FormattingService())->e($sd['code']); ?></strong></td>
                    <?php foreach ($activeTypes as $type => $typeLabel): ?>
                        <?php foreach (['nouveau' => 'Nouv.', 'en_cours' => 'En cours', 'traite' => 'Traité', 'total' => 'Total'] as $state => $stateLabel): ?>
                            <td data-label="<?php echo $typeLabel . ' ' . $stateLabel; ?>" class="<?php echo $sd[$type][$state] > 0 ? 'synthesis-cell-value' : 'synthesis-cell-zero'; ?>">
                                <?php echo $sd[$type][$state]; ?>
                            </td>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    <td data-label="Total" class="synthesis-cell-value"><strong><?php
                        $rowTotal = 0;
                    foreach ($activeTypes as $type => $_) {
                        $rowTotal += $sd[$type]['total'];
                    }
                    echo $rowTotal;
                    ?></strong></td>
                </tr>
                <?php endforeach; ?>
                <!-- Totals row -->
                <tr class="row--totals">
                    <td data-label="<?php echo (new \App\Services\FormattingService())->e(\App\Services\ConfigService::getInstance()->get('app_label_unite', 'UR')); ?>"><strong>Total</strong></td>
                    <?php foreach ($activeTypes as $type => $typeLabel): ?>
                        <?php foreach (['nouveau' => 'Nouv.', 'en_cours' => 'En cours', 'traite' => 'Traité', 'total' => 'Total'] as $state => $stateLabel): ?>
                            <td data-label="<?php echo $typeLabel . ' ' . $stateLabel; ?>" class="synthesis-cell-value"><?php echo $totals[$type][$state]; ?></td>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    <td data-label="Total" class="synthesis-cell-value"><strong><?php echo $grandTotal; ?></strong></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="card">
    <p class="text-muted">Aucun site n'est configuré. La synthèse par site sera disponible dès qu'au moins un site sera activé dans les <a href="<?php echo (new \App\Services\HttpService())->url('settings'); ?>">paramètres</a>.</p>
</div>
<?php endif; ?>
