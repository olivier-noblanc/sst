<?php

use App\Repository\ReportRepository;
use App\Repository\UserRepository;
use App\Enum\UserRole;
use App\Enum\ReportType;
use App\Repository\RegistryRepository;
use App\Repository\NotificationRepository;

/**
 * Mail Notification Functions — Application SST DREETS BFC
 *
 * Notification dispatch functions for email.
 * Split from mail.php for readability.
 * Email body builders are in mail_templates.php + mail/email_renderer.php.
 */

require_once __DIR__ . '/mail_templates.php';
require_once __DIR__ . '/mail/email_renderer.php';
/**
 * Notify relevant people about a new report.
 *
 * @param PDO    $pdo        Database connection
 * @param string $reportUuid The new report UUID
 * @param string $type       Report type (rsst/rami/dgi)
 * @param int    $siteId     Site ID where report was filed
 */
function notifyNewReport(PDO $pdo, string $reportUuid, string $type, int $siteId): void
{
    $report = ReportRepository::instance()->findById($reportUuid);
    if ($report === null) {
        return;
    }
    $registryLabel = getRegistryShortLabel($type);
    $subject = "Nouveau signalement $registryLabel — {$report->reference}";
    $reportUrl = absoluteUrl('report_view', ['uuid' => $reportUuid]);
    $body = '<html><body>';
    $body .= '<h2>Nouveau signalement enregistré</h2>';
    $body .= renderEmailField('Référence', $report->reference);
    $body .= renderEmailField('Registre', $registryLabel);
    $body .= renderEmailField('Objet', $report->objet);
    $body .= renderEmailField('Déclarant', $report->declarantPrenom . ' ' . $report->declarantNom);
    $body .= renderEmailField('Date de l\'événement', formatDateFR($report->dateEvenement));
    $body .= renderEmailLink($reportUrl, 'Consulter le signalement');
    $body .= '</body></html>';
    // Collect recipients: per-site + global
    $recipients = getNotificationRecipients($pdo, $siteId);
    foreach ($recipients as $email) {
        sendMail($email, $subject, $body);
    }
    // Modular-audit P1.3 — DGI notification au CSA/CHSCT n'était déclenchée
    // qu'en checkant `$type === ReportType::Dgi->value`. Or la colonne
    // `registries.notify_chsct` existe déjà (persistée par le handler
    // settings_handler_registres.php:172) et permet à l'admin de configurer
    // n'importe quel registre (custom inclus) pour notifier le CSA à la création.
    // On lit maintenant notify_chsct depuis la DB. Fallback: si colonne absente
    // ou registre introuvable, on garde la compatibilité arrière (DGI only).
    $notifyChsct = false;
    try {
        $registry = RegistryRepository::instance()->findByCode($type);
        if ($registry !== null && (int) ($registry['notify_chsct'] ?? 0) === 1) {
            $notifyChsct = true;
        } elseif ($registry === null && $type === ReportType::Dgi->value) {
            // Compatibilité : registre custom DGI sans ligne en DB (edge case)
            $notifyChsct = getConfigService()->get('app_dgi_notify_csa', '1') === '1';
        }
    } catch (\Throwable $e) {
        // Pre-migration (colonne notify_chsct absente) — fallback ancien comportement
        $notifyChsct = ($type === ReportType::Dgi->value && getConfigService()->get('app_dgi_notify_csa', '1') === '1');
    }
    if ($notifyChsct) {
        $csaUsers = UserRepository::instance()->findByRole(UserRole::Chsct->value);
        foreach ($csaUsers as $csaUser) {
            /** @var array<string, mixed> $csaUser */
            if (!empty($csaUser['email']) && !in_array($csaUser['email'], $recipients, true)) {
                $registryLabel = getRegistryShortLabel($type);
                $csaSubject = 'Signalement ' . $registryLabel . ' — Notification ' . getRoleLabelShort('chsct') . " — {$report->reference}";
                $csaBody = '<html><body>';
                $csaBody .= '<h2>Notification ' . $registryLabel . ' — Article L4131-2 du Code du travail</h2>';
                $csaBody .= '<p>Conformément à l\'article L4131-2 du Code du travail, vous êtes informé(e) de la création d\'un signalement relatif à un danger grave et imminent.</p>';
                $csaBody .= renderEmailField('Référence', $report->reference);
                $csaBody .= renderEmailField('Objet', $report->objet);
                $csaBody .= renderEmailField('Déclarant', $report->declarantPrenom . ' ' . $report->declarantNom);
                $csaBody .= renderEmailLink($reportUrl, 'Consulter le signalement');
                $csaBody .= '</body></html>';
                sendMail($csaUser['email'], $csaSubject, $csaBody);
            }
        }
    }
}
/**
 * Notify the declarant that their report has received a response.
 *
 * @param PDO    $pdo          Database connection
 * @param string $reportUuid   Report UUID
 * @param int    $respondentId The responding user's ID
 */
function notifyReportResponse(PDO $pdo, string $reportUuid, int $respondentId): void
{
    $report = ReportRepository::instance()->findById($reportUuid);
    if ($report === null) {
        return;
    }
    // Get declarant email
    /** @var int */
    $declarantId = $report->declarantId;
    $declarant = UserRepository::instance()->findById($declarantId);
    if ($declarant === null || empty($declarant['email'])) {
        return;
    }
    /** @var string */
    $reportType = $report->type;
    $registryLabel = getRegistryShortLabel($reportType);
    $subject = "Réponse à votre signalement $registryLabel — {$report->reference}";
    $respondent = UserRepository::instance()->findById($respondentId);
    if ($respondent === null) {
        return;
    }
    /** @var array<string, mixed> $respondent */
    $reportUrl = absoluteUrl('report_view', ['uuid' => $reportUuid]);
    $body = '<html><body>';
    $body .= '<h2>Votre signalement a reçu une réponse</h2>';
    $body .= renderEmailField('Référence', $report->reference);
    $body .= renderEmailField('Répondant', $respondent['prenom'] . ' ' . $respondent['nom']);
    $body .= renderEmailField('Nouvel état', ETAT_LABELS[$report->etat] ?? $report->etat);
    $body .= renderEmailLink($reportUrl, 'Consulter la réponse');
    $body .= '</body></html>';
    sendMail($declarant['email'], $subject, $body);

    // Also notify linked/confirmed agents
    $linkedAgents = ReportRepository::instance()->getLinkedAgents($reportUuid);
    foreach ($linkedAgents as $linkedAgent) {
        if (!empty($linkedAgent['email']) && $linkedAgent['email'] !== $declarant['email']) {
            $linkedSubject = "Réponse au signalement $registryLabel — {$report->reference}";
            $linkedReportUrl = absoluteUrl('report_view', ['uuid' => $reportUuid]);
            $linkedBody = '<html><body>';
            $linkedBody .= '<h2>Réponse au signalement</h2>';
            $linkedBody .= '<p>Bonjour ' . e($linkedAgent['prenom'] ?? '') . ',</p>';
            $linkedBody .= '<p>Le signalement <strong>' . e($report->reference) . '</strong> auquel vous êtes rattaché(e) a reçu une réponse.</p>';
            $linkedBody .= renderEmailField('Répondant', $respondent['prenom'] . ' ' . $respondent['nom']);
            $linkedBody .= renderEmailField('Nouvel état', ETAT_LABELS[$report->etat] ?? $report->etat);
            $linkedBody .= renderEmailLink($linkedReportUrl, 'Consulter la réponse');
            $linkedBody .= '</body></html>';
            sendMail($linkedAgent['email'], $linkedSubject, $linkedBody);
        }
    }
}

/**
 * Get all notification recipients for a given site.
 *
 * @param PDO $pdo     Database connection
 * @param int $siteId  Site ID
 * @return array<int, string>       Array of email strings
 */
function getNotificationRecipients(PDO $pdo, int $siteId): array
{
    $emails = [];
    $seen = [];
    // Per-site
    $siteEmails = NotificationRepository::instance()->findSiteEmails($siteId);
    foreach ($siteEmails as $email) {
        /** @var string */
        $emailStr = $email ?? '';
        $lower = strtolower($emailStr);
        $seen[] = $lower;
        $emails[] = $email;
    }
    // Global
    $globalEmails = NotificationRepository::instance()->findGlobalEmails();
    foreach ($globalEmails as $email) {
        /** @var string */
        $emailStr = $email ?? '';
        $lower = strtolower($emailStr);
        if (!in_array($lower, $seen, true)) {
            $seen[] = $lower;
            $emails[] = $email;
        }
    }
    return $emails;
}

/**
 * Notify a user that their role has been changed.
 *
 * @param PDO    $pdo     Database connection
 * @param int    $userId  The user whose role changed
 * @param string $oldRole Previous role
 * @param string $newRole New role
 */
function notifyRoleChange(PDO $pdo, int $userId, string $oldRole, string $newRole): void
{
    $user = UserRepository::instance()->findById($userId);
    if ($user === null || empty($user['email'])) {
        return;
    }
    $appName = getConfigService()->get('app_nom_organisation', 'DREETS BFC');
    $oldLabel = ROLE_LABELS[$oldRole] ?? $oldRole;
    $newLabel = ROLE_LABELS[$newRole] ?? $newRole;
    $subject = "Changement de votre rôle dans $appName";
    $body = '<html><body>';
    $body .= '<h2>Changement de rôle</h2>';
    $body .= '<p>Bonjour ' . htmlspecialchars($user['prenom'] . ' ' . $user['nom']) . ',</p>';
    $body .= "<p>Votre rôle dans l'application <strong>" . htmlspecialchars($appName) . '</strong> a été modifié par un administrateur :</p>';
    $body .= '<table style="border-collapse:collapse; font-family:sans-serif; font-size:14px; margin:16px 0;">';
    $body .= '<tr><td style="padding:6px 16px; color:#888;">Ancien rôle</td><td style="padding:6px 16px;">' . htmlspecialchars($oldLabel) . '</td></tr>';
    $body .= '<tr><td style="padding:6px 16px; color:#888;">Nouveau rôle</td><td style="padding:6px 16px;"><strong>' . htmlspecialchars($newLabel) . '</strong></td></tr>';
    $body .= '</table>';
    if ($newRole === UserRole::Superviseur->value) {
        $body .= "<p>En tant que <strong>Superviseur</strong>, vous pouvez désormais : répondre aux signalements, gérer les utilisateurs, consulter la synthèse et les statistiques, exporter les données, et configurer les paramètres de l'application.</p>";
    } elseif ($newRole === UserRole::Chsct->value) {
        $body .= '<p>En tant que <strong>' . e(getRoleLabel('chsct')) . '</strong>, vous pouvez consulter tous les signalements (y compris confidentiels), la synthèse, les statistiques et les exports.</p>';
    } else {
        $body .= "<p>En tant qu'<strong>Agent</strong>, vous pouvez créer des signalements et suivre leurs réponses.</p>";
    }
    $body .= '<p>Si vous pensez que cette modification est une erreur, veuillez contacter votre administrateur.</p>';
    $body .= '<hr style="margin:16px 0; border:none; border-top:1px solid #ddd;">';
    $body .= "<p style=\"font-size:12px; color:#888;\">Cet e-mail a été envoyé automatiquement par l'application $appName. Ne pas répondre directement à ce message.</p>";
    $body .= '</body></html>';
    sendMail($user['email'], $subject, $body);
}

/**
 * Send confirmation emails to agents invited to be linked to a report.
 * Each agent receives a unique token link they must click to confirm.
 * @param array<string> $emails  List of email addresses
 */
function sendAgentInviteEmails(PDO $pdo, string $reportUuid, array $emails): void
{
    $report = ReportRepository::instance()->findById($reportUuid);
    if ($report === null) {
        return;
    }
    foreach ($emails as $email) {
        $email = trim($email);
        if (empty($email)) {
            continue;
        }
        // Create invite token
        $token = ReportRepository::instance()->createAgentInvite($reportUuid, $email);
        // Build confirmation link
        $confirmUrl = absoluteUrl('agent_confirm', ['token' => $token]);
        $subject = 'Vous avez été rattaché(e) au signalement ' . $report->reference;
        $body = renderEmailBody(
            'Confirmation de rattachement',
            '<p>Bonjour,</p>'
            . '<p>Vous avez été rattaché(e) au signalement <strong>' . e($report->reference) . '</strong> par le déclarant.</p>'
            . renderEmailField('Objet', $report->objet)
            . '<p>Pour confirmer votre rattachement, cliquez sur le bouton ci-dessous :</p>'
            . renderEmailButton($confirmUrl, 'Confirmer mon rattachement')
            . '<p style="font-size:13px; color:#888;">Si vous ne souhaitez pas être rattaché(e), ignorez cet e-mail. Aucune action ne sera effectuée.</p>'
        );
        sendMail($email, $subject, $body);
    }
}

/**
 * Get base URL for links in emails.
 *
 * @return string
 */
function getBaseUrl(): string
{
    // Explicit config takes priority — $_SERVER['HTTP_HOST'] isn't reliable
    // in every context email gets sent from (missing entirely outside a
    // real HTTP request, or reflecting an internal hostname behind a
    // reverse proxy) and falls back to 'localhost', producing a link that
    // only resolves on the server itself — broken for every recipient.
    $configured = trim(getConfigService()->get('app_base_url', ''));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // The app doesn't have to be deployed at the domain root — IIS commonly
    // mounts it as an "application" under a site, e.g.
    // https://server/sst/index.php. url() always returns a path relative
    // to index.php ("index.php?page=..."), which works fine for in-app
    // navigation (the browser resolves it against whatever URL it's
    // already on) but not for an email, which needs the full path
    // including that subfolder. SCRIPT_NAME carries it (e.g.
    // "/sst/index.php"); PHP's dirname() returns "/" for a root
    // deployment ("/index.php") — normalize that to '' so this doesn't
    // produce a trailing-slash-only segment.
    $scriptDir = \dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $subfolder = ($scriptDir === '/' || $scriptDir === '\\' || $scriptDir === '.') ? '' : $scriptDir;
    return "$protocol://$host$subfolder";
}

/**
 * Build an absolute URL (with scheme + host) for use in an email — url()
 * alone returns a path relative to the current page ("index.php?..."),
 * which has no meaning in an email client (there's no "current page" to
 * resolve it against). Prefer this over combining getBaseUrl() and url()
 * by hand at each call site — that pattern already caused a real bug once
 * (sendAgentInviteEmails() built a plain url() with no host at all).
 *
 * @param array<string, mixed> $params
 */
function absoluteUrl(string $page, array $params = []): string
{
    return getBaseUrl() . '/' . url($page, $params);
}
