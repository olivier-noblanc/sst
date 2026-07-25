<?php

/**
 * Site Edit Handler — Thin controller delegating to SiteRepository.
 */
use App\Services\HttpService;
use App\Services\SessionService;
use App\Repository\SiteRepository;

/** @var array<string, string> $_POST */

$http = new HttpService();
$session = SessionService::getInstance();

$siteId = (int) ($_POST['site_id'] ?? 0);

if ($siteId <= 0) {
    $session->setFlash('error', 'Site introuvable.');
    $http->redirect($http->url('settings', ['tab' => 'manage_sites']));
}

$repo = getContainer()->get(SiteRepository::class);
$site = $repo->findById($siteId);

/** @var array<string, string> $site */

if ($site === null) {
    $session->setFlash('error', 'Site introuvable.');
    $http->redirect($http->url('settings', ['tab' => 'manage_sites']));
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
if (!empty($code) && $code !== (string) ($site['code'] ?? '') && $repo->findByCode($code) !== null) {
    $errors['code'] = 'Un site avec ce code existe déjà.';
}

if (!empty($errors)) {
    setFormErrors($errors);
    setFormData($_POST);
    $http->redirect($http->url('site_edit', ['id' => $siteId]));
}

$success = $repo->update($siteId, $code, $nom, $departement);

if ($success) {
    auditLog(getDB(), 'site', 'edit', 'Site modifié : ' . $code . ' — ' . $nom, $siteId, 'site');
    $session->setFlash('success', 'Site ' . e($code . ' — ' . $nom) . ' mis à jour avec succès.');
} else {
    error_log('[SST-DB] site_edit failed for site_id=' . $siteId);
    $session->setFlash('error', 'Erreur lors de la mise à jour du site. Veuillez contacter un administrateur.');
}

$http->redirect($http->url('settings', ['tab' => 'manage_sites']));
