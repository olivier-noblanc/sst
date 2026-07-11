<?php

/**
 * Agent Confirmation Handler — Thin controller delegating to ReportRepository.
 */

use App\Repository\ReportRepository;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    return;
}

$token = trim((string) ($_POST['token'] ?? ''));

if (empty($token)) {
    setFlash('error', 'Lien de confirmation invalide.');
    redirect(url('home'));
}

/** @var ReportRepository $repo */
$repo = getContainer()->get(ReportRepository::class);
$invite = $repo->getAgentInviteByToken($token);

if (!$invite) {
    setFlash('error', 'Cette invitation a déjà été confirmée ou est invalide. Si vous venez de cliquer, votre rattachement est déjà actif.');
    redirect(url('home'));
}

$user = currentUser();
if (!$user) {
    setFlash('error', 'Vous devez être connecté pour confirmer votre rattachement.');
    redirect(url('home'));
}

if (strtolower((string) ($user['email'] ?? '')) !== strtolower((string) $invite['email'])) {
    setFlash('error', 'Cette invitation est destinée à ' . e((string) $invite['email']) . '. Vous êtes connecté(e) en tant que ' . e((string) ($user['email'] ?? 'inconnu')) . '.');
    redirect(url('home'));
}

$confirmed = $repo->confirmAgentInvite($token, (int) ($user['id'] ?? 0));

if ($confirmed) {
    $reportUuid = (string) $invite['report_uuid'];
    $report = $repo->findById($reportUuid);
    $ref = $report ? (string) $report['reference'] : $reportUuid;
    auditLog(getDB(), 'report', 'agent_confirm', 'Agent ' . e((string) $user['email']) . ' confirmé rattachement au signalement ' . $ref, 0, 'report', ['reference' => $ref, 'email' => $user['email'] ?? '']);
    setFlash('success', 'Votre rattachement au signalement ' . e($ref) . ' est confirmé.');
    redirect(url('report_view', ['uuid' => $reportUuid]));
} else {
    setFlash('error', 'Erreur lors de la confirmation. Veuillez réessayer.');
    redirect(url('home'));
}
