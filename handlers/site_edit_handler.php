<?php
/**
 * Site Edit Handler — Application SST DREETS BFC
 *
 * POST handler: update an existing site's code, name, and department.
 * Access: superviseur only
 */

validatePostRequest(url('settings', ['tab' => 'manage_sites']), ['superviseur']);

$siteId = (int) ($_POST['site_id'] ?? 0);

if ($siteId <= 0) {
    setFlash('error', 'Site introuvable.');
    redirect(url('settings', ['tab' => 'manage_sites']));
}

$pdo = getDB();

// Verify site exists
$site = getSiteById($pdo, $siteId);
if (!$site) {
    setFlash('error', 'Site introuvable.');
    redirect(url('settings', ['tab' => 'manage_sites']));
}

// Validate input
$code = trim($_POST['code'] ?? '');
$nom = trim($_POST['nom'] ?? '');
$departement = trim($_POST['departement'] ?? '');

$errors = [];
if (empty($code)) {
    $errors['code'] = 'Le code est requis.';
}
if (empty($nom)) {
    $errors['nom'] = 'Le nom est requis.';
}

// Check for duplicate code (exclude current site)
if (!empty($code) && $code !== $site['code']) {
    $existing = getSiteByCode($pdo, $code);
    if ($existing) {
        $errors['code'] = 'Un site avec ce code existe déjà.';
    }
}

if (!empty($errors)) {
    setFormErrors($errors);
    setFormData($_POST);
    redirect(url('site_edit', ['id' => $siteId]));
}

// Update site
$success = updateSite($pdo, $siteId, $code, $nom, $departement);

if ($success) {
    auditLog($pdo, 'site', 'edit', 'Site modifié : ' . $code . ' — ' . $nom, (int) $siteId, 'site');
    setFlash('success', 'Site ' . e($code . ' — ' . $nom) . ' mis à jour avec succès.');
} else {
    error_log('[SST-DB] site_edit failed for site_id=' . $siteId);
    setFlash('error', 'Erreur lors de la mise à jour du site. (site_id=' . $siteId . ')');
}

redirect(url('settings', ['tab' => 'manage_sites']));
