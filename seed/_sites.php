<?php
global $pdo;

// Additional sites (7 more to supplement the 2 default ones = 9 total)
$additionalSites = [
    ['UR25B', 'UR Doubs — Antenne', 'Doubs'],           // site_id 3
    ['UR39', 'UR Jura', 'Jura'],                       // site_id 4
    ['UR58', 'UR Nièvre', 'Nièvre'],                   // site_id 5
    ['UR70', 'UR Haute-Saône', 'Haute-Saône'],         // site_id 6
    ['UR71', 'UR Saône-et-Loire', 'Saône-et-Loire'],   // site_id 7
    ['UR89', 'UR Yonne', 'Yonne'],                      // site_id 8
    ['UR90', 'UR Territoire de Belfort', 'Territoire de Belfort'], // site_id 9
];

$siteStmt = $pdo->prepare('INSERT INTO sites (code, nom, departement) VALUES (:code, :nom, :departement)');
foreach ($additionalSites as $site) {
    $siteStmt->execute([':code' => $site[0], ':nom' => $site[1], ':departement' => $site[2]]);
}
echo "Created " . count($additionalSites) . " additional sites (9 total with defaults).\n";
