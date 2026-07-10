<?php

/**
 * Agent Confirmation Handler — Application SST DREETS BFC
 *
 * Processes the confirmation of an agent invitation to be linked to a report.
 * This is triggered when the agent clicks "Confirmer mon rattachement" in the email,
 * then presses the confirmation button on the page.
 *
 * Flow:
 * 1. Agent receives email with unique token link → agent_confirm?token=xxx
 * 2. Page shows report summary + confirmation button
 * 3. Agent clicks button → POST to this handler
 * 4. Handler validates token, links agent to report
 * 5. Agent is redirected to the report view
 */

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // GET request is handled by the page template (pages/agent_confirm.php)
    return;
}

$token = trim($_POST['token'] ?? '');

if (empty($token)) {
    setFlash('error', 'Lien de confirmation invalide.');
    redirect(url('home'));
}

$pdo = getDB();

// Look up the invitation
$invite = getAgentInviteByToken($pdo, $token);

if (!$invite) {
    setFlash('error', 'Cette invitation a déjà été confirmée ou est invalide. Si vous venez de cliquer, votre rattachement est déjà actif.');
    redirect(url('home'));
}

// Get current user — IIS auth should have logged them in automatically
$user = currentUser();
if (!$user) {
    setFlash('error', 'Vous devez être connecté pour confirmer votre rattachement.');
    redirect(url('home'));
}

// Verify the email matches
if (strtolower($user['email'] ?? '') !== strtolower((string) $invite['email'])) {
    setFlash('error', 'Cette invitation est destinée à ' . e($invite['email']) . '. Vous êtes connecté(e) en tant que ' . e($user['email'] ?? 'inconnu') . '.');
    redirect(url('home'));
}

// Confirm the invitation
$confirmed = confirmAgentInvite($pdo, $token, (int) $user['id']);

if ($confirmed) {
    $report = getReportByUuid($pdo, $invite['report_uuid']);
    $ref = $report ? $report['reference'] : $invite['report_uuid'];
    auditLog($pdo, 'report', 'agent_confirm', 'Agent ' . e($user['email']) . ' confirmé rattachement au signalement ' . $ref, 0, 'report', ['reference' => $ref, 'email' => $user['email']]);
    setFlash('success', 'Votre rattachement au signalement ' . e($ref) . ' est confirmé.');
    redirect(url('report_view', ['uuid' => $invite['report_uuid']]));
} else {
    setFlash('error', 'Erreur lors de la confirmation. Veuillez réessayer.');
    redirect(url('home'));
}
