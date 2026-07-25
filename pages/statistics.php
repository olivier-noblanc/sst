<?php
/**
 * Statistics Page — Application SST DREETS BFC
 *
 * Tableau de bord avec cartes indicateurs et répartition par site.
 * Access: superviseur, chsct
 */
requireRole([\App\Enum\UserRole::Superviseur->value]);

use App\Enum\ReportType;

// Service instances (created once for the page)
$fmt = new \App\Services\FormattingService();
$http = new \App\Services\HttpService();
$config = getConfigService();

$noSiteMode = $config->isNoSiteMode();

// Get filter
$yearGet = $_GET['year'] ?? date('Y');
$year = trim((string) $yearGet);

// Get statistics via StatisticsService
$statsService = getContainer()->get(\App\Services\StatisticsService::class);
$availableYears = $statsService->getAvailableYears();
$stats = $statsService->getStatistics($year);
$indicateurs = $stats->indicateurs;
$statsBySite = $stats->statsBySite;
$ramiStats = $stats->ramiStats;

// Build table data by site
/** @var list<array{id: int, code: string, nom: string}> $sites */
$sites = \App\Repository\SiteRepository::instance()->findAll();
$tableData = [];
foreach ($sites as $site) {
    $tableData[$site['id']] = [
        'code' => $site['code'],
        'nom'  => $site['nom'],
        ReportType::Rsst->value => 0,
        ReportType::Dgi->value  => 0,
        ReportType::Rami->value => 0,
        'total' => 0,
    ];
}

foreach ($statsBySite as $row) {
    // Find matching site
    foreach ($tableData as $sId => &$td) {
        if ($td['code'] === $row->code) {
            $td[ReportType::Rsst->value]  = $row->getCount(ReportType::Rsst->value);
            $td[ReportType::Rami->value]  = $row->getCount(ReportType::Rami->value);
            $td[ReportType::Dgi->value]   = $row->getCount(ReportType::Dgi->value);
            $td['total'] = $row->total;
            break;
        }
    }
}
unset($td);

// Calculate totals
$totalRsst = 0;
$totalRami = 0;
$totalDgi = 0;
$totalAll = 0;
foreach ($tableData as $td) {
    $totalRsst += $td[ReportType::Rsst->value];
    $totalRami += $td[ReportType::Rami->value];
    $totalDgi  += $td[ReportType::Dgi->value];
    $totalAll  += $td['total'];
}

$pageTitle = 'Statistiques';

$ramiEnabled = $config->isRegistryEnabled(\App\Enum\ReportType::Rami->value);
$dgiEnabled = $config->isRegistryEnabled(\App\Enum\ReportType::Dgi->value);
?>

<h1 class="page-title">Statistiques</h1>


<!-- Year filter -->
<form method="GET" action="<?php echo $http->url('statistics'); ?>" class="filter-bar">
    <div class="form-group">
        <label for="year">Année</label>
        <select name="year" id="year">
            <?php foreach ($availableYears as $y): ?>
            <option value="<?php echo $fmt->e($y); ?>" <?php echo $y === $year ? 'selected' : ''; ?>><?php echo $fmt->e($y); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group align-self-end">
        <button type="submit" class="btn btn--outline">Filtrer</button>
    </div>
</form>

<!-- Cartes indicateurs -->
<div class="indicateur-grid">
    <div class="indicateur-card">
        <div class="indicateur-card__value"><?php echo $indicateurs->totalReports; ?></div>
        <div class="indicateur-card__label">Total signalements</div>
        <div class="indicateur-card__detail"><?php echo $indicateurs->totalNouveau; ?> nouveaux · <?php echo $indicateurs->totalEnCours; ?> en cours · <?php echo $indicateurs->totalTraite; ?> traités</div>
    </div>
    <?php
    $registryRepo = \App\Repository\RegistryRepository::instance();
foreach ($registryRepo->findEnabled() as $reg):
    $code = (string) $reg['code'];
    $classes = \App\Repository\RegistryRepository::themeClasses((string) $reg['color_theme']);
    $total = $indicateurs->getRegistryTotal($code);
    ?>
    <div class="indicateur-card <?php echo e($classes['indicateur']); ?>">
        <div class="indicateur-card__value"><?php echo $total; ?></div>
        <div class="indicateur-card__label">Signalements <?php echo e((string) $reg['short_label']); ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Table: Reports by site and registry -->
<?php if (!$noSiteMode): ?>
<div class="card">
    <h2 class="card__title">Nombre de signalements réparti par <?php echo $fmt->e($config->get('app_label_unite', 'UR')); ?> et par registre</h2>
    <div class="table-wrapper">
        <table aria-label="Statistiques des signalements">
            <thead>
                <tr>
                    <th><?php echo $fmt->e($config->get('app_label_unite', 'UR')); ?></th>
                    <th class="text-center">RSST</th>
                    <?php if ($dgiEnabled): ?><th class="text-center">DGI</th><?php endif; ?>
                    <?php if ($ramiEnabled): ?><th class="text-center">RAMI</th><?php endif; ?>
                    <th class="text-center">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tableData as $td): ?>
                <tr>
                    <td><strong><?php echo $fmt->e($td['code']); ?></strong> — <?php echo $fmt->e($td['nom']); ?></td>
                    <td class="text-center <?php echo $td[ReportType::Rsst->value] > 0 ? 'synthesis-cell-value' : 'synthesis-cell-zero'; ?>"><?php echo $td[ReportType::Rsst->value]; ?></td>
                    <?php if ($dgiEnabled): ?><td class="text-center <?php echo $td[ReportType::Dgi->value] > 0 ? 'synthesis-cell-value' : 'synthesis-cell-zero'; ?>"><?php echo $td[ReportType::Dgi->value]; ?></td><?php endif; ?>
                    <?php if ($ramiEnabled): ?><td class="text-center <?php echo $td[ReportType::Rami->value] > 0 ? 'synthesis-cell-value' : 'synthesis-cell-zero'; ?>"><?php echo $td[ReportType::Rami->value]; ?></td><?php endif; ?>
                    <td class="text-center synthesis-cell-value"><strong><?php echo $td['total']; ?></strong></td>
                </tr>
                <?php endforeach; ?>
                <!-- Totals row -->
                <tr class="row--totals">
                    <td><strong>Total</strong></td>
                    <td class="text-center synthesis-cell-value"><?php echo $totalRsst; ?></td>
                    <?php if ($dgiEnabled): ?><td class="text-center synthesis-cell-value"><?php echo $totalDgi; ?></td><?php endif; ?>
                    <?php if ($ramiEnabled): ?><td class="text-center synthesis-cell-value"><?php echo $totalRami; ?></td><?php endif; ?>
                    <td class="text-center synthesis-cell-value"><strong><?php echo $totalAll; ?></strong></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- RAMI: Répartition par nature de l'auteur et type d'acte -->
<?php if ($ramiEnabled && $ramiStats->hasData()): ?>
<div class="card card--mt">
    <h2 class="card__title">RAMI — Répartition par nature de l'auteur et type d'acte</h2>
    <p class="text-muted text-small">Statistiques sur les signalements RAMI ayant renseigné les champs « Nature de l'auteur » et « Type d'acte ».</p>
    <div class="help-profiles-grid">
        <?php if (!empty($ramiStats->byNatureAuteur)): ?>
        <div>
            <h3>Nature de l'auteur</h3>
            <div class="table-wrapper">
                <table aria-label="RAMI par nature de l'auteur">
                    <thead>
                        <tr>
                            <th>Nature</th>
                            <th class="text-center">Nombre</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ramiStats->byNatureAuteur as $row): ?>
                        <tr>
                            <td><?php echo $fmt->e(getRegistryFieldOptions('rami', 'nature_auteur')[$row['nature_auteur']] ?? $row['nature_auteur']); ?></td>
                            <td class="text-center"><?php echo (int) $row['count']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        <?php if (!empty($ramiStats->byTypeActe)): ?>
        <div>
            <h3>Type d'acte</h3>
            <div class="table-wrapper">
                <table aria-label="RAMI par type d'acte">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th class="text-center">Nombre</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ramiStats->byTypeActe as $row): ?>
                        <tr>
                            <td><?php echo $fmt->e(getRegistryFieldOptions('rami', 'type_acte')[$row['type_acte']] ?? $row['type_acte']); ?></td>
                            <td class="text-center"><?php echo (int) $row['count']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
