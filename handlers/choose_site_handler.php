<?php
/**
 * Choose Site Handler — Application SST DREETS BFC
 * 
 * Processes the site selection form from first login.
 * The choice is irreversible for the agent.
 */

validatePostRequest(url('choose_site'));

// Only allow if user has no site yet
if (currentUserHasSite()) {
    session_write_close();
    redirect(url('home'));
}

$siteId = (int) ($_POST['site_id'] ?? 0);

if ($siteId <= 0) {
    setFlash('error', 'Veuillez sélectionner un site.');
    session_write_close();
    redirect(url('choose_site'));
}

$pdo = getDB();

// Verify the site exists and is active
$site = getSiteById($pdo, $siteId);
if (!$site) {
    setFlash('error', 'Site invalide. Veuillez réessayer.');
    session_write_close();
    redirect(url('choose_site'));
}

// Update the user's site in DB
$userId = currentUserId();
$stmt = $pdo->prepare('UPDATE users SET site_id = :site_id, updated_at = datetime("now") WHERE id = :id');
$stmt->execute([':site_id' => $siteId, ':id' => $userId]);

// Re-read the user from DB to get the authoritative state
refreshCurrentUser($pdo);
$updatedUser = currentUser();

if ($updatedUser && !empty($updatedUser['site_id'])) {
    // Clear intended URL
    clearIntendedUrl();
    setFlash('success', 'Votre site a été défini : ' . $site['code'] . ' — ' . $site['nom'] . '. Bienvenue !');
    
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
