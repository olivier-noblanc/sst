<?php
/**
 * Synthesis Page — Application SST DREETS BFC
 * 
 * Summary table across all registries, showing counts by site, registry type, and state.
 * Access: superviseur, chsct
 */
requireRole(['superviseur', 'chsct']);

$pdo = getDB();

// Get filter parameters
$year = $_GET['year'] ?? date('Y');
$siteId = (int) ($_GET['site'] ?? 0);

// Get available years
$availableYears = getAvailableYears($pdo);
if (empty($availableYears)) {
    $availableYears = [date('Y')];
}

// Get sites for filter
$sites = getAllSites($pdo);

// Get synthesis data
$synthesisData = getSynthesisData($pdo, $year, $siteId);

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
    $type = $row['type'];
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
?>

<h1 class="page-title">Synthèse des signalements</h1>

<?php require __DIR__ . '/../templates/alert.php'; ?>

<!-- Filter Bar -->
<form method="GET" action="<?php echo url('synthesis'); ?>" class="filter-bar">
    <div class="form-group">
        <label for="year">Année</label>
        <select name="year" id="year">
            <?php foreach ($availableYears as $y): ?>
            <option value="<?php echo e($y); ?>" <?php echo $y == $year ? 'selected' : ''; ?>><?php echo e($y); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="site">Site</label>
        <select name="site" id="site">
            <option value="0" <?php echo $siteId === 0 ? 'selected' : ''; ?>>Tous les sites</option>
            <?php foreach ($sites as $s): ?>
            <option value="<?php echo (int) $s['id']; ?>" <?php echo $siteId === (int) $s['id'] ? 'selected' : ''; ?>><?php echo e($s['nom']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group align-self-end">
        <button type="submit" class="btn btn--outline">Filtrer</button>
    </div>
</form>

<!-- Synthesis Table -->
<div class="card">
    <div class="table-wrapper">
        <table aria-label="Synthèse des signalements par site">
            <thead>
                <tr>
                    <th rowspan="2"><?php echo e(getConfig('app_label_unite', 'UR')); ?></th>
                    <th colspan="4" class="synthesis-th-rsst">RSST</th>
                    <th colspan="4" class="synthesis-th-rami">RAMI</th>
                    <th colspan="4" class="synthesis-th-dgi">DGI</th>
                    <th rowspan="2">Total</th>
                </tr>
                <tr>
                    <th>Nouv.</th><th>En cours</th><th>Traité</th><th>Total</th>
                    <th>Nouv.</th><th>En cours</th><th>Traité</th><th>Total</th>
                    <th>Nouv.</th><th>En cours</th><th>Traité</th><th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($siteData as $sId => $sd): ?>
                <tr>
                    <td><strong><?php echo e($sd['code']); ?></strong></td>
                    <?php foreach (['rsst', 'rami', 'dgi'] as $type): ?>
                        <?php foreach (['nouveau', 'en_cours', 'traite', 'total'] as $state): ?>
                            <td class="<?php echo $sd[$type][$state] > 0 ? 'synthesis-cell-value' : 'synthesis-cell-zero'; ?>">
                                <?php echo $sd[$type][$state]; ?>
                            </td>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    <td class="synthesis-cell-value"><strong><?php echo $sd['rsst']['total'] + $sd['rami']['total'] + $sd['dgi']['total']; ?></strong></td>
                </tr>
                <?php endforeach; ?>
                <!-- Totals row -->
                <tr class="row--totals">
                    <td><strong>Total</strong></td>
                    <?php foreach (['rsst', 'rami', 'dgi'] as $type): ?>
                        <?php foreach (['nouveau', 'en_cours', 'traite', 'total'] as $state): ?>
                            <td class="synthesis-cell-value"><?php echo $totals[$type][$state]; ?></td>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    <td class="synthesis-cell-value"><strong><?php echo $grandTotal; ?></strong></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
