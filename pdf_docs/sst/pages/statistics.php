<?php
/**
 * Statistics Page — Application SST DREETS BFC
 * 
 * KPI dashboard with cards and table by site.
 * Access: superviseur, chsct
 */
requireRole(['superviseur', 'chsct']);

$pdo = getDB();

// Get filter
$year = $_GET['year'] ?? date('Y');
$year = trim($year);

// Get available years
$availableYears = getAvailableYears($pdo);
if (empty($availableYears)) {
    $availableYears = [date('Y')];
}

// Get KPIs
$kpis = getStatisticsKPIs($pdo, $year);

// Get stats by site
$statsBySite = getStatsBySite($pdo, $year);

// Count total active users
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE is_active = 1");
$stmt->execute();
$totalUsers = (int) $stmt->fetchColumn();

// Build table data by site
$sites = getAllSites($pdo);
$tableData = [];
foreach ($sites as $site) {
    $tableData[$site['id']] = [
        'code' => $site['code'],
        'nom'  => $site['nom'],
        'rsst' => 0,
        'dgi'  => 0,
        'rami' => 0,
        'total'=> 0,
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
?>

<h1 class="page-title">Statistiques</h1>

<?php require __DIR__ . '/../templates/alert.php'; ?>

<!-- Year filter -->
<div class="filter-bar">
    <div class="form-group">
        <label for="year">Année</label>
        <select name="year" id="year" onchange="window.location.href='<?php echo url("statistics"); ?>&year='+this.value">
            <?php foreach ($availableYears as $y): ?>
            <option value="<?php echo e($y); ?>" <?php echo $y == $year ? 'selected' : ''; ?>><?php echo e($y); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<!-- KPI Cards -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-card__value"><?php echo $totalUsers; ?></div>
        <div class="kpi-card__label">Nombre d'inscrits</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-card__value"><?php echo $kpis['total_reports']; ?></div>
        <div class="kpi-card__label">Total signalements</div>
        <div class="kpi-card__detail"><?php echo $kpis['total_nouveau']; ?> nouveaux · <?php echo $kpis['total_en_cours']; ?> en cours · <?php echo $kpis['total_traite']; ?> traités</div>
    </div>
    <div class="kpi-card kpi-card--rsst">
        <div class="kpi-card__value"><?php echo $kpis['total_rsst']; ?></div>
        <div class="kpi-card__label">Signalements RSST</div>
    </div>
    <div class="kpi-card kpi-card--dgi">
        <div class="kpi-card__value"><?php echo $kpis['total_dgi']; ?></div>
        <div class="kpi-card__label">Signalements DGI</div>
    </div>
    <div class="kpi-card kpi-card--rami">
        <div class="kpi-card__value"><?php echo $kpis['total_rami']; ?></div>
        <div class="kpi-card__label">Signalements RAMI</div>
    </div>
</div>

<!-- Table: Reports by site and registry -->
<div class="card">
    <h3 style="margin-bottom:16px;">Nombre de signalements réparti par <?php echo e(getConfig('app_label_unite', 'UR')); ?> et par registre</h3>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th><?php echo e(getConfig('app_label_unite', 'UR')); ?></th>
                    <th style="text-align:center;">RSST</th>
                    <th style="text-align:center;">DGI</th>
                    <th style="text-align:center;">RAMI</th>
                    <th style="text-align:center;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tableData as $td): ?>
                <tr>
                    <td><strong><?php echo e($td['code']); ?></strong> — <?php echo e($td['nom']); ?></td>
                    <td style="text-align:center;" class="<?php echo $td['rsst'] > 0 ? 'synthesis-cell-value' : 'synthesis-cell-zero'; ?>"><?php echo $td['rsst']; ?></td>
                    <td style="text-align:center;" class="<?php echo $td['dgi'] > 0 ? 'synthesis-cell-value' : 'synthesis-cell-zero'; ?>"><?php echo $td['dgi']; ?></td>
                    <td style="text-align:center;" class="<?php echo $td['rami'] > 0 ? 'synthesis-cell-value' : 'synthesis-cell-zero'; ?>"><?php echo $td['rami']; ?></td>
                    <td style="text-align:center;" class="synthesis-cell-value"><strong><?php echo $td['total']; ?></strong></td>
                </tr>
                <?php endforeach; ?>
                <!-- Totals row -->
                <tr style="background:var(--grey-100);font-weight:600;">
                    <td><strong>Total</strong></td>
                    <td style="text-align:center;" class="synthesis-cell-value"><?php echo $totalRsst; ?></td>
                    <td style="text-align:center;" class="synthesis-cell-value"><?php echo $totalDgi; ?></td>
                    <td style="text-align:center;" class="synthesis-cell-value"><?php echo $totalRami; ?></td>
                    <td style="text-align:center;" class="synthesis-cell-value"><strong><?php echo $totalAll; ?></strong></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
