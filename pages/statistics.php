<?php
/**
 * Statistics Page — Application SST DREETS BFC
 *
 * Tableau de bord avec cartes indicateurs et répartition par site.
 * Access: superviseur, chsct
 */
requireRole([ROLE_SUPERVISEUR]);

$pdo = getDB();
$noSiteMode = isNoSiteMode($pdo);

// Get filter
$year = $_GET['year'] ?? date('Y');
$year = trim($year);

// Get available years
$availableYears = getAvailableYears($pdo);
if (empty($availableYears)) {
    $availableYears = [date('Y')];
}

// Get indicateurs
$indicateurs = getStatisticsIndicateurs($pdo, $year);

// Get stats by site
$statsBySite = getStatsBySite($pdo, $year);

// Get RAMI structured stats
$ramiStats = getRamiStructuredStats($pdo, $year);

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

$ramiEnabled = isRegistryEnabled(TYPE_RAMI);
$dgiEnabled = isRegistryEnabled(TYPE_DGI);
?>

<h1 class="page-title">Statistiques</h1>


<!-- Year filter -->
<form method="GET" action="<?php echo url('statistics'); ?>" class="filter-bar">
    <div class="form-group">
        <label for="year">Année</label>
        <select name="year" id="year">
            <?php foreach ($availableYears as $y): ?>
            <option value="<?php echo e($y); ?>" <?php echo $y == $year ? 'selected' : ''; ?>><?php echo e($y); ?></option>
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
    <h3 class="card__title">Nombre de signalements réparti par <?php echo e(getConfig('app_label_unite', 'UR')); ?> et par registre</h3>
    <div class="table-wrapper">
        <table aria-label="Statistiques des signalements">
            <thead>
                <tr>
                    <th><?php echo e(getConfig('app_label_unite', 'UR')); ?></th>
                    <th class="text-center">RSST</th>
                    <?php if ($dgiEnabled): ?><th class="text-center">DGI</th><?php endif; ?>
                    <?php if ($ramiEnabled): ?><th class="text-center">RAMI</th><?php endif; ?>
                    <th class="text-center">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tableData as $td): ?>
                <tr>
                    <td><strong><?php echo e($td['code']); ?></strong> — <?php echo e($td['nom']); ?></td>
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
    <h3 class="card__title">RAMI — Répartition par nature de l'auteur et type d'acte</h3>
    <p class="text-muted text-small">Statistiques sur les signalements RAMI ayant renseigné les champs « Nature de l'auteur » et « Type d'acte ».</p>
    <div class="help-profiles-grid">
        <?php if (!empty($ramiStats['by_nature_auteur'])): ?>
        <div>
            <h4>Nature de l'auteur</h4>
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
                            <td><?php echo e(RAMI_NATURE_AUTEUR_LABELS[$row['nature_auteur']] ?? $row['nature_auteur']); ?></td>
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
            <h4>Type d'acte</h4>
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
                            <td><?php echo e(RAMI_TYPE_ACTE_LABELS[$row['type_acte']] ?? $row['type_acte']); ?></td>
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
