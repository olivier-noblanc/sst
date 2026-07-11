<?php

/**
 * Choose Site Handler — Thin controller delegating to SiteRepository + UserRepository.
 */

use App\Repository\SiteRepository;
use App\Repository\UserRepository;

validatePostRequest(url('choose_site'));

$siteId = (int) ($_POST['site_id'] ?? 0);

if ($siteId <= 0) {
    setFlash('error', 'Veuillez sélectionner un site.');
    session_write_close();
    redirect(url('choose_site'));
}

/** @var SiteRepository $siteRepo */
$siteRepo = getContainer()->get(SiteRepository::class);
/** @var UserRepository $userRepo */
$userRepo = getContainer()->get(UserRepository::class);

$userId = currentUserId();
$user = currentUser();
$hasExistingSite = !empty($user['site_id']);

// Grace period check
if ($hasExistingSite) {
    $siteChosenAt = $user['site_chosen_at'] ?? null;
    if ($siteChosenAt) {
        $daysSinceChoice = (time() - strtotime((string) $siteChosenAt)) / 86400;
        if ($daysSinceChoice > 7) {
            setFlash('error', 'Le délai de 7 jours pour modifier votre site est dépassé. Contactez votre superviseur pour changer de site.');
            session_write_close();
            redirect(url('home'));
        }
    }
    if ((int) ($user['site_id'] ?? 0) === $siteId) {
        session_write_close();
        redirect(url('home'));
    }
}

$site = $siteRepo->findById($siteId);
if (!$site) {
    setFlash('error', 'Site invalide. Veuillez réessayer.');
    session_write_close();
    redirect(url('choose_site'));
}

$updated = $userRepo->updateSite($userId, $siteId);

if ($updated) {
    refreshCurrentUser(getDB());
    clearIntendedUrl();

    if ($hasExistingSite) {
        setFlash('success', 'Votre site a été modifié : ' . (string) $site['code'] . ' — ' . (string) $site['nom'] . '.');
        auditLog(getDB(), 'user', 'site_change', 'Agent a changé son site : ' . (string) $site['code'] . ' — ' . (string) $site['nom'], $userId, 'user', ['site_id' => $siteId]);
    } else {
        setFlash('success', 'Votre site a été défini : ' . (string) $site['code'] . ' — ' . (string) $site['nom'] . '. Bienvenue !');
    }

    session_write_close();
    redirect(url('home'));
} else {
    error_log("SST App: choose_site_handler failed for user $userId, site_id=$siteId");
    setFlash('error', 'Erreur lors de l\'enregistrement de votre site. Veuillez réessayer.');
    session_write_close();
    redirect(url('choose_site'));
}
