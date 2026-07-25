<?php

/**
 * Agent Confirmation Handler — Thin controller delegating to ReportRepository.
 */

use App\Repository\ReportRepository;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    return;
}

/** @var string */
$tokenRaw = $_POST['token'] ?? '';
$token = trim($tokenRaw);

$http = new \App\Services\HttpService();
$session = \App\Services\SessionService::getInstance();

if (empty($token)) {
    $session->setFlash('error', 'Lien de confirmation invalide.');
    $http->redirect($http->url('home'));
}

$repo = getContainer()->get(ReportRepository::class);
$invite = $repo->getAgentInviteByToken($token);

if ($invite === null) {
    $session->setFlash('error', 'Cette invitation a déjà été confirmée ou est invalide. Si vous venez de cliquer, votre rattachement est déjà actif.');
    $http->redirect($http->url('home'));
}
/** @var array{email: string, report_uuid: string} $invite */

$user = $session->getUserSession();
if ($user === null) {
    $session->setFlash('error', 'Vous devez être connecté pour confirmer votre rattachement.');
    $http->redirect($http->url('home'));
}
/** @var array{id: int|string, email: string} $user */

if (strtolower((string) ($user['email'] ?? '')) !== strtolower((string) $invite['email'])) {
    $session->setFlash('error', 'Cette invitation est destinée à ' . e((string) $invite['email']) . '. Vous êtes connecté(e) en tant que ' . e((string) ($user['email'] ?? 'inconnu')) . '.');
    $http->redirect($http->url('home'));
}

$confirmed = $repo->confirmAgentInvite($token, (int) ((string) ($user['id'] ?? '0')));

if ($confirmed) {
    $reportUuid = (string) $invite['report_uuid'];
    $report = $repo->findById($reportUuid);
    /** @var array{reference?: string}|null $report */
    $ref = $report !== null ? (string) ($report['reference'] ?? '') : $reportUuid;
    auditLog(getDB(), 'report', 'agent_confirm', 'Agent ' . e($user['email'] ?? '') . ' confirmé rattachement au signalement ' . $ref, null, 'report', ['reference' => $ref, 'email' => $user['email'] ?? ''], $reportUuid);
    $session->setFlash('success', 'Votre rattachement au signalement ' . e($ref) . ' est confirmé.');
    $http->redirect($http->url('report_view', ['uuid' => $reportUuid]));
} else {
    $session->setFlash('error', 'Erreur lors de la confirmation. Veuillez réessayer.');
    $http->redirect($http->url('home'));
}
