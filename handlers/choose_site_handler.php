<?php

/**
 * Choose Site Handler — Thin controller delegating to SiteRepository + UserRepository.
 */
use App\Services\HttpService;
use App\Services\SessionService;
use App\Repository\SiteRepository;
use App\Repository\UserRepository;

// CSRF already validated by public/index.php before this handler is
// required (see the choose_site special-case block) — same
// double-consumption bug as report_create_handler.php, see there for
// the full explanation.

/** @var string */
$siteIdRaw = $_POST['site_id'] ?? '0';
$siteId = (int) $siteIdRaw;

$http = new HttpService();
$session = SessionService::getInstance();

if ($siteId <= 0) {
    $session->setFlash('error', 'Veuillez sélectionner un site.');
    session_write_close();
    $http->redirect($http->url('choose_site'));
}

$siteRepo = getContainer()->get(SiteRepository::class);
$userRepo = getContainer()->get(UserRepository::class);

$user = $session->getUserSession();
if ($user === null) {
    $session->setFlash('error', 'Session invalide.');
    session_write_close();
    $http->redirect($http->url('home'));
    return;
}
$userId = $user->id;
$hasExistingSite = $user->siteId !== null;

// Grace period check
if ($hasExistingSite) {
    $siteChosenAt = $user->siteChosenAt;
    if ($siteChosenAt !== null) {
        $timestamp = strtotime($siteChosenAt);
        if ($timestamp !== false) {
            $daysSinceChoice = (time() - $timestamp) / 86400;
        } else {
            $daysSinceChoice = 999;
        }
        if ($daysSinceChoice > 7) {
            $session->setFlash('error', 'Le délai de 7 jours pour modifier votre site est dépassé. Contactez votre superviseur pour changer de site.');
            session_write_close();
            $http->redirect($http->url('home'));
            return;
        }
    }
    if ($user->siteId === $siteId) {
        session_write_close();
        $http->redirect($http->url('home'));
        return;
    }
}

$site = $siteRepo->findById($siteId);
if ($site === null || empty($site['is_active'])) {
    $session->setFlash('error', 'Site invalide ou désactivé.');
    session_write_close();
    $http->redirect($http->url('choose_site'));
    return;
}
$updated = $userRepo->updateSite($userId, $siteId);

if ($updated) {
    refreshCurrentUser(getDB());
    clearIntendedUrl();

    if ($hasExistingSite) {
        $session->setFlash('success', 'Votre site a été modifié : ' . (string) $site['code'] . ' — ' . (string) $site['nom'] . '.');
        auditLog(getDB(), 'user', 'site_change', 'Agent a changé son site : ' . (string) $site['code'] . ' — ' . (string) $site['nom'], $userId, 'user', ['site_id' => $siteId]);
    } else {
        $session->setFlash('success', 'Votre site a été défini : ' . (string) $site['code'] . ' — ' . (string) $site['nom'] . '. Bienvenue !');
    }

    session_write_close();
    $http->redirect($http->url('home'));
} else {
    error_log("SST App: choose_site_handler failed for user $userId, site_id=$siteId");
    $session->setFlash('error', 'Erreur lors de l\'enregistrement de votre site. Veuillez réessayer.');
    session_write_close();
    $http->redirect($http->url('choose_site'));
}
