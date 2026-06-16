<?php
/**
 * Choose Site Handler — Application SST DREETS BFC
 * 
 * Processes the site selection form from first login.
 * Agents can change their site within 7 days of first selection.
 * After 7 days, only a supervisor can change it.
 */

validatePostRequest(url('choose_site'));

$siteId = (int) ($_POST['site_id'] ?? 0);

if ($siteId <= 0) {
    setFlash('error', 'Veuillez sélectionner un site.');
    session_write_close();
    redirect(url('choose_site'));
}

$pdo = getDB();
$userId = currentUserId();
$user = currentUser();

// Determine if this is a new selection or a change within grace period
$hasExistingSite = !empty($user['site_id']);
$isWithinGracePeriod = false;

if ($hasExistingSite) {
    // Check grace period: agent can change site within 7 days of first choice
    $siteChosenAt = $user['site_chosen_at'] ?? null;
    if ($siteChosenAt) {
        $chosenTime = strtotime($siteChosenAt);
        $daysSinceChoice = (time() - $chosenTime) / 86400;
        $isWithinGracePeriod = $daysSinceChoice <= 7;
    }

    if (!$isWithinGracePeriod) {
        setFlash('error', 'Le délai de 7 jours pour modifier votre site est dépassé. Contactez votre superviseur pour changer de site.');
        session_write_close();
        redirect(url('home'));
    }

    // If changing to the same site, no-op
    if ((int) $user['site_id'] === $siteId) {
        session_write_close();
        redirect(url('home'));
    }
}

// Verify the site exists and is active
$site = getSiteById($pdo, $siteId);
if (!$site) {
    setFlash('error', 'Site invalide. Veuillez réessayer.');
    session_write_close();
    redirect(url('choose_site'));
}

// Update the user's site in DB
$stmt = $pdo->prepare('UPDATE users SET site_id = :site_id, site_chosen_at = datetime("now"), updated_at = datetime("now") WHERE id = :id');
$stmt->execute([':site_id' => $siteId, ':id' => $userId]);

// Re-read the user from DB to get the authoritative state
refreshCurrentUser($pdo);
$updatedUser = currentUser();

if ($updatedUser && !empty($updatedUser['site_id'])) {
    // Clear intended URL
    clearIntendedUrl();
    
    if ($hasExistingSite) {
        setFlash('success', 'Votre site a été modifié : ' . $site['code'] . ' — ' . $site['nom'] . '.');
        auditLog($pdo, 'user', 'site_change', 'Agent a changé son site : ' . $site['code'] . ' — ' . $site['nom'], $userId, 'user', ['site_id' => $siteId]);
    } else {
        setFlash('success', 'Votre site a été défini : ' . $site['code'] . ' — ' . $site['nom'] . '. Bienvenue !');
    }
    
    // IMPORTANT: write session to disk before redirect
    // Without this, some PHP configurations may lose session data on redirect
    session_write_close();
    redirect(url('home'));
} else {
    // DB update failed — this shouldn't happen, but handle gracefully
    error_log("SST App: choose_site_handler failed for user $userId, site_id=$siteId");
    setFlash('error', 'Erreur lors de l\'enregistrement de votre site. Veuillez réessayer.');
    session_write_close();
    redirect(url('choose_site'));
}
