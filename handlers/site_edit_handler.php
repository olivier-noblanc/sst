<?php

/**
 * Site Edit Handler — Thin controller delegating to SiteRepository.
 */

use App\Repository\SiteRepository;

$siteId = (int) ($_POST['site_id'] ?? 0);

if ($siteId <= 0) {
    setFlash('error', 'Site introuvable.');
    redirect(url('settings', ['tab' => 'manage_sites']));
}

/** @var SiteRepository $repo */
$repo = getContainer()->get(SiteRepository::class);
$site = $repo->findById($siteId);

if (!$site) {
    setFlash('error', 'Site introuvable.');
    redirect(url('settings', ['tab' => 'manage_sites']));
}

$code = trim((string) ($_POST['code'] ?? ''));
$nom = trim((string) ($_POST['nom'] ?? ''));
$departement = trim((string) ($_POST['departement'] ?? ''));

$errors = [];
if (empty($code)) {
    $errors['code'] = 'Le code est requis.';
}
if (empty($nom)) {
    $errors['nom'] = 'Le nom est requis.';
}
if (!empty($code) && $code !== (string) ($site['code'] ?? '') && $repo->findByCode($code)) {
    $errors['code'] = 'Un site avec ce code existe déjà.';
}

if (!empty($errors)) {
    setFormErrors($errors);
    setFormData($_POST);
    redirect(url('site_edit', ['id' => $siteId]));
}

$success = $repo->update($siteId, $code, $nom, $departement);

if ($success) {
    auditLog(getDB(), 'site', 'edit', 'Site modifié : ' . $code . ' — ' . $nom, (int) $siteId, 'site');
    setFlash('success', 'Site ' . e($code . ' — ' . $nom) . ' mis à jour avec succès.');
} else {
    error_log('[SST-DB] site_edit failed for site_id=' . $siteId);
    setFlash('error', 'Erreur lors de la mise à jour du site. Veuillez contacter un administrateur.');
}

redirect(url('settings', ['tab' => 'manage_sites']));
