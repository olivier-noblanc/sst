<?php
/**
 * Synthesis Page — Application SST DREETS BFC
 *
 * Summary table across all registries, showing counts by site, registry type, and state.
 * Access: superviseur, chsct
 */
use App\Enum\ReportState;
use App\Enum\ReportType;

requireRole([\App\Enum\UserRole::Superviseur->value]);

// Service instances (created once for the page)
$fmt = new \App\Services\FormattingService();
$http = new \App\Services\HttpService();
$config = getConfigService();

$noSiteMode = $config->isNoSiteMode();

// Get filter parameters
$year = $_GET['year'] ?? date('Y');
$siteGet = $_GET['site'] ?? '0';
$siteId = (int) $siteGet;

// Get available years
$availableYears = \App\Repository\StatsRepository::instance()->getAvailableYears();
if (empty($availableYears)) {
    $availableYears = [date('Y')];
}

// Get sites for filter
$sites = \App\Repository\SiteRepository::instance()->findAll();
/** @var list<array{id: int, code: string, nom: string}> $sites */

// Get synthesis data
$synthesisData = \App\Repository\StatsRepository::instance()->getSynthesis($year, $siteId);

// Modular-audit P2.4 — iterate over enabled registries dynamically instead of
// hardcoding ReportType::cases(). Custom registries now appear automatically.
$enabledRegistries = \App\Repository\RegistryRepository::instance()->findEnabled();
$registryCodes = array_map(fn($r) => (string) $r['code'], $enabledRegistries);
$emptyStateRow = [
    ReportState::Nouveau->value => 0,
    ReportState::EnCours->value => 0,
    ReportState::Traite->value => 0,
    ReportState::Abandonne->value => 0,
    ReportState::Reouvert->value => 0,
    'total' => 0,
];

// Organize data by site
$siteData = [];
foreach ($sites as $site) {
    $typeData = array_combine(
        $registryCodes,
        array_fill(0, count($registryCodes), $emptyStateRow)
    );
    $siteData[$site['id']] = array_merge(['code' => $site['code'], 'nom' => $site['nom']], $typeData);
}

// Fill in the data
foreach ($synthesisData as $row) {
    $sId = $row->siteId;
    $type = $row->type;
    if (isset($siteData[$sId]) && isset($siteData[$sId][$type])) {
        $siteData[$sId][$type] = [
            ReportState::Nouveau->value   => $row->nouveau,
            ReportState::EnCours->value   => $row->enCours,
            ReportState::Traite->value    => $row->traite,
            ReportState::Abandonne->value => $row->abandonne,
            ReportState::Reouvert->value  => $row->reouvert,
            'total'                       => $row->total,
        ];
    }
}

// Calculate totals
$totals = array_combine(
    $registryCodes,
    array_fill(0, count($registryCodes), $emptyStateRow)
);

foreach ($siteData as $sId => $sd) {
    foreach ($registryCodes as $code) {
        foreach ([ReportState::Nouveau->value, ReportState::EnCours->value, ReportState::Traite->value, ReportState::Abandonne->value, ReportState::Reouvert->value, 'total'] as $state) {
            // Modular-audit P2.4 — defensive access (PHPStan offset safety)
            $siteTypeRow = is_array($sd[$code] ?? null) ? $sd[$code] : [];
            $totals[$code][$state] += $siteTypeRow[$state] ?? 0;
        }
    }
}

$grandTotal = 0;
foreach ($registryCodes as $code) {
    $totalsRow = is_array($totals[$code] ?? null) ? $totals[$code] : [];
    $grandTotal += $totalsRow['total'] ?? 0;
}

$pageTitle = 'Synthèse des signalements';

// Modular-audit P2.4 — build activeTypes dynamically from enabled registries
// (was hardcoded ReportType::Rsst/Rami/Dgi with $ramiEnabled/$dgiEnabled flags)
$activeTypes = [];
foreach ($enabledRegistries as $reg) {
    $activeTypes[(string) $reg['code']] = (string) $reg['short_label'];
}
$colSpan = count($activeTypes) * 4;
?>

<h1 class="page-title">Synthèse des signalements</h1>


<!-- Filter Bar -->
<form method="GET" action="<?php echo $http->url('synthesis'); ?>" class="filter-bar">
    <div class="form-group">
        <label for="year">Année</label>
        <select name="year" id="year">
            <?php foreach ($availableYears as $y): ?>
            <option value="<?php echo $fmt->e($y); ?>" <?php echo $y === $year ? 'selected' : ''; ?>><?php echo $fmt->e($y); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php if (!$noSiteMode): ?>
    <div class="form-group">
        <label for="site"><?php echo $fmt->e($config->get('app_label_unite', 'UR')); ?></label>
        <select name="site" id="site">
            <option value="0" <?php echo $siteId === 0 ? 'selected' : ''; ?>>Tous</option>
            <?php foreach ($sites as $s): ?>
            <option value="<?php echo (int) $s['id']; ?>" <?php echo $siteId === (int) $s['id'] ? 'selected' : ''; ?>><?php echo $fmt->e($s['nom']); ?></option>
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
                    <th rowspan="2"><?php echo $fmt->e($config->get('app_label_unite', 'UR')); ?></th>
                    <?php foreach ($activeTypes as $type => $typeLabel): ?>
                        <th colspan="4" class="synthesis-th--<?php echo e($type); ?>"><?php echo e($typeLabel); ?></th>
                    <?php endforeach; ?>
                    <th rowspan="2">Total</th>
                </tr>
                <tr>
                    <?php foreach ($activeTypes as $type => $typeLabel): ?>
                        <th>Nouv.</th><th>En cours</th><th>Traité</th><th>Total</th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($siteData as $sId => $sd): ?>
                <tr>
                    <td data-label="<?php echo $fmt->e($config->get('app_label_unite', 'UR')); ?>"><strong><?php echo $fmt->e($sd['code']); ?></strong></td>
                    <?php foreach ($activeTypes as $type => $typeLabel): ?>
                        <?php foreach ([ReportState::Nouveau->value => 'Nouv.', ReportState::EnCours->value => 'En cours', ReportState::Traite->value => 'Traité', 'total' => 'Total'] as $state => $stateLabel): ?>
                            <?php
                            // Modular-audit P2.4 — $sd[$type] is an array{nouveau, en_cours, traite, abandonne, reouvert, total}
                            // but PHPStan infers it as a union with string. Cast to array for safety.
                            $typeRow = is_array($sd[$type] ?? null) ? $sd[$type] : [];
                            $cellValue = $typeRow[$state] ?? 0;
                            ?>
                            <td data-label="<?php echo e($typeLabel . ' ' . $stateLabel); ?>" class="<?php echo $cellValue > 0 ? 'synthesis-cell-value' : 'synthesis-cell-zero'; ?>">
                                <?php echo (int) $cellValue; ?>
                            </td>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    <td data-label="Total" class="synthesis-cell-value"><strong><?php
                        $rowTotal = 0;
                    foreach ($activeTypes as $type => $_) {
                        $typeRow = is_array($sd[$type] ?? null) ? $sd[$type] : [];
                        $rowTotal += (int) ($typeRow['total'] ?? 0);
                    }
                    echo $rowTotal;
                    ?></strong></td>
                </tr>
                <?php endforeach; ?>
                <!-- Totals row -->
                <tr class="row--totals">
                    <td data-label="<?php echo $fmt->e($config->get('app_label_unite', 'UR')); ?>"><strong>Total</strong></td>
                    <?php foreach ($activeTypes as $type => $typeLabel): ?>
                        <?php foreach ([ReportState::Nouveau->value => 'Nouv.', ReportState::EnCours->value => 'En cours', ReportState::Traite->value => 'Traité', 'total' => 'Total'] as $state => $stateLabel): ?>
                            <?php
                            $typeTotals = is_array($totals[$type] ?? null) ? $totals[$type] : [];
                            $totalCell = $typeTotals[$state] ?? 0;
                            ?>
                            <td data-label="<?php echo e($typeLabel . ' ' . $stateLabel); ?>" class="synthesis-cell-value"><?php echo (int) $totalCell; ?></td>
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
    <p class="text-muted">Aucun site n'est configuré. La synthèse par site sera disponible dès qu'au moins un site sera activé dans les <a href="<?php echo $http->url('settings'); ?>">paramètres</a>.</p>
</div>
<?php endif; ?>
