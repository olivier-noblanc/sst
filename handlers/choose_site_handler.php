<?php
/**
 * Choose Site Handler — Application SST DREETS BFC
 * 
 * Processes the site selection form from first login.
 * The choice is irreversible for the agent.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('choose_site'));
}

// Validate CSRF token
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Erreur de sécurité. Veuillez réessayer.');
    session_write_close();
    redirect(url('choose_site'));
}

// Only allow if user has no site yet
if (!empty($_SESSION['user']['site_id'])) {
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
// NOTE: We do NOT use "AND site_id IS NULL" because:
// - In some edge cases (race conditions, session issues), site_id might already be set in DB
//   but not in the session. Using the strict condition would cause rowCount() = 0,
//   which would make the handler think the update failed, creating an infinite loop.
// - Instead, we always do the UPDATE, then verify by re-reading from DB.
$userId = (int) $_SESSION['user']['id'];
$stmt = $pdo->prepare('UPDATE users SET site_id = :site_id, updated_at = datetime("now") WHERE id = :id');
$stmt->execute([':site_id' => $siteId, ':id' => $userId]);

// Re-read the user from DB to get the authoritative state
$stmt = $pdo->prepare(
    'SELECT u.*, s.code as site_code, s.nom as site_nom 
     FROM users u 
     LEFT JOIN sites s ON u.site_id = s.id 
     WHERE u.id = :id'
);
$stmt->execute([':id' => $userId]);
$updatedUser = $stmt->fetch();

if ($updatedUser && !empty($updatedUser['site_id'])) {
    // Update session with the fresh DB data
    $_SESSION['user'] = $updatedUser;

    // Clear intended URL
    unset($_SESSION['intended_url']);
    setFlash('success', 'Votre site a été défini : ' . $site['code'] . ' — ' . $site['nom'] . '. Bienvenue !');
    
    // IMPORTANT: write session to disk before redirect
    // Without this, some PHP configurations may lose session data on redirect
    session_write_close();
    redirect(url('home'));
} else {
    // DB update failed — this shouldn't happen, but handle gracefully
    error_log("SST App: choose_site_handler failed for user $userId, site_id=$siteId: " . $e->getMessage());
    setFlash('error', 'Erreur lors de l\'enregistrement de votre site : ' . e($e->getMessage()));
    session_write_close();
    redirect(url('choose_site'));
}
