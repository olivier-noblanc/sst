<?php
// Enable RAMI and DGI registries for test data
$pdo->prepare("INSERT OR IGNORE INTO config_app (cle, valeur, type, categorie, libelle, modifiable) VALUES (?, ?, '', '', '', 1)")
    ->execute(['app_registry_rami_enabled', '1']);
$pdo->prepare("INSERT OR IGNORE INTO config_app (cle, valeur, type, categorie, libelle, modifiable) VALUES (?, ?, '', '', '', 1)")
    ->execute(['app_registry_dgi_enabled', '1']);
echo "RAMI and DGI registries enabled for test data.\n";
