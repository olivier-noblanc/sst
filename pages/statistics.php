<?php
/**
 * Statistics Page — Application SST DREETS BFC
 *
 * Tableau de bord avec cartes indicateurs et répartition par site.
 * Access: superviseur, chsct
 */
requireRole([ROLE_SUPERVISEUR]);

// Service instances (created once for the page)
$fmt = new \App\Services\FormattingService();
$http = new \App\Services\HttpService();
$config = \App\Services\ConfigService::getInstance();

$noSiteMode = $config->isNoSiteMode();

// Get filter
$yearGet = $_GET['year'] ?? date('Y');
$year = trim((string) $yearGet);

// Get available years
$availableYears = \App\Repository\StatsRepository::instance()->getAvailableYears();
if (empty($availableYears)) {
    $availableYears = [date('Y')];
}

// Get indicateurs
/** @var array<string, int> $indicateurs */
$indicateurs = \App\Repository\StatsRepository::instance()->getIndicateurs($year);

// Get stats by site
/** @var list<array{code: string, rsst: int, rami: int, dgi: int, total: int}> $statsBySite */
$statsBySite = \App\Repository\StatsRepository::instance()->getBySite($year);

// Get RAMI structured stats
/** @var array{by_nature_auteur: list<array{nature_auteur: string, count: int}>, by_type_acte: list<array{type_acte: string, count: int}>} $ramiStats */
$ramiStats = \App\Repository\StatsRepository::instance()->getRamiStructuredStats($year);

// Build table data by site
/** @var list<array{id: int, code: string, nom: string}> $sites */
$sites = \App\Repository\SiteRepository::instance()->findAll();
$tableData = [];
foreach ($sites as $site) {
    $tableData[$site['id']] = [
        'code' => $site['code'],
        'nom'  => $site['nom'],
        'rsst' => 0,
        'dgi'  => 0,
        'rami' => 0,
        'total' => 0,
    ];
}

foreach ($statsBySite as $row) {
    // Find matching site
    foreach ($tableData as $sId => &$td) {
        if ($td['code'] === $row['code']) {
            $td['rsst']  = (int) ($row['rsst'] ?? 0);
            $td['rami']  = (int) ($row['rami'] ?? 0);
            $td['dgi']   = (int) ($row['dgi'] ?? 0);
            $td['total'] = (int) ($row['total'] ?? 0);
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
    $totalRsst += $td['rsst'];
    $totalRami += $td['rami'];
    $totalDgi  += $td['dgi'];
    $totalAll  += $td['total'];
}

$pageTitle = 'Statistiques';

$ramiEnabled = $config->isRegistryEnabled(TYPE_RAMI);
$dgiEnabled = $config->isRegistryEnabled(TYPE_DGI);
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
        <div class="indicateur-card__value"><?php echo $indicateurs['total_reports']; ?></div>
        <div class="indicateur-card__label">Total signalements</div>
        <div class="indicateur-card__detail"><?php echo $indicateurs['total_nouveau']; ?> nouveaux · <?php echo $indicateurs['total_en_cours']; ?> en cours · <?php echo $indicateurs['total_traite']; ?> traités</div>
    </div>
    <div class="indicateur-card indicateur-card--rsst">
        <div class="indicateur-card__value"><?php echo $indicateurs['total_rsst']; ?></div>
        <div class="indicateur-card__label">Signalements RSST</div>
    </div>
    <?php if ($dgiEnabled): ?>
    <div class="indicateur-card indicateur-card--dgi">
        <div class="indicateur-card__value"><?php echo $indicateurs['total_dgi']; ?></div>
        <div class="indicateur-card__label">Signalements DGI</div>
    </div>
    <?php endif; ?>
    <?php if ($ramiEnabled): ?>
    <div class="indicateur-card indicateur-card--rami">
        <div class="indicateur-card__value"><?php echo $indicateurs['total_rami']; ?></div>
        <div class="indicateur-card__label">Signalements RAMI</div>
    </div>
    <?php endif; ?>
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
                    <td class="text-center <?php echo $td['rsst'] > 0 ? 'synthesis-cell-value' : 'synthesis-cell-zero'; ?>"><?php echo $td['rsst']; ?></td>
                    <?php if ($dgiEnabled): ?><td class="text-center <?php echo $td['dgi'] > 0 ? 'synthesis-cell-value' : 'synthesis-cell-zero'; ?>"><?php echo $td['dgi']; ?></td><?php endif; ?>
                    <?php if ($ramiEnabled): ?><td class="text-center <?php echo $td['rami'] > 0 ? 'synthesis-cell-value' : 'synthesis-cell-zero'; ?>"><?php echo $td['rami']; ?></td><?php endif; ?>
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
<?php if ($ramiEnabled && (!empty($ramiStats['by_nature_auteur']) || !empty($ramiStats['by_type_acte']))): ?>
<div class="card card--mt">
    <h2 class="card__title">RAMI — Répartition par nature de l'auteur et type d'acte</h2>
    <p class="text-muted text-small">Statistiques sur les signalements RAMI ayant renseigné les champs « Nature de l'auteur » et « Type d'acte ».</p>
    <div class="help-profiles-grid">
        <?php if (!empty($ramiStats['by_nature_auteur'])): ?>
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
                        <?php foreach ($ramiStats['by_nature_auteur'] as $row): ?>
                        <tr>
                            <td><?php echo $fmt->e(RAMI_NATURE_AUTEUR_LABELS[$row['nature_auteur']] ?? $row['nature_auteur']); ?></td>
                            <td class="text-center"><?php echo (int) $row['count']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        <?php if (!empty($ramiStats['by_type_acte'])): ?>
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
                        <?php foreach ($ramiStats['by_type_acte'] as $row): ?>
                        <tr>
                            <td><?php echo $fmt->e(RAMI_TYPE_ACTE_LABELS[$row['type_acte']] ?? $row['type_acte']); ?></td>
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
