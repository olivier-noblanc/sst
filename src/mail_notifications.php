<?php

/**
 * Mail Notification Functions — Application SST DREETS BFC
 *
 * Notification dispatch functions for email.
 * Split from mail.php for readability.
 * Email body builders are in mail_templates.php.
 */

require_once __DIR__ . '/mail_templates.php';
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
    $report = getReportByUuid($pdo, $reportUuid);
    if (!$report) {
        return;
    }
    $registryLabel = REGISTRY_SHORT_LABELS[$type] ?? strtoupper($type);
    $subject = "Nouveau signalement $registryLabel — {$report['reference']}";
    $body = '<html><body>';
    $body .= '<h2>Nouveau signalement enregistré</h2>';
    $body .= '<p><strong>Référence :</strong> ' . e($report['reference']) . '</p>';
    $body .= "<p><strong>Registre :</strong> $registryLabel</p>";
    $body .= '<p><strong>Objet :</strong> ' . e($report['objet']) . '</p>';
    $body .= '<p><strong>Déclarant :</strong> ' . e($report['declarant_prenom'] . ' ' . $report['declarant_nom']) . '</p>';
    $body .= "<p><strong>Date de l'événement :</strong> " . formatDateFR($report['date_evenement']) . '</p>';
    $body .= '<p><a href="' . getBaseUrl() . '/' . url('report_view', ['uuid' => $reportUuid]) . '">Consulter le signalement</a></p>';
    $body .= '</body></html>';
    // Collect recipients: per-site + global
    $recipients = getNotificationRecipients($pdo, $siteId);
    foreach ($recipients as $email) {
        sendMail($email, $subject, $body);
    }
    // DGI: notify CSA/CHSCT members (article L4131-2 Code du travail)
    if ($type === TYPE_DGI && getConfig('app_dgi_notify_csa', '1') === '1') {
        $csaUsers = getUsersByRole($pdo, ROLE_CHSCT);
        foreach ($csaUsers as $csaUser) {
            if (!empty($csaUser['email']) && !in_array($csaUser['email'], $recipients)) {
                $csaSubject = 'Signalement DGI — Notification ' . getRoleLabelShort('chsct') . " — {$report['reference']}";
                $csaBody = '<html><body>';
                $csaBody .= '<h2>Notification DGI — Article L4131-2 du Code du travail</h2>';
                $csaBody .= '<p>Conformément à l\'article L4131-2 du Code du travail, vous êtes informé(e) de la création d\'un signalement relatif à un danger grave et imminent.</p>';
                $csaBody .= '<p><strong>Référence :</strong> ' . e($report['reference']) . '</p>';
                $csaBody .= '<p><strong>Objet :</strong> ' . e($report['objet']) . '</p>';
                $csaBody .= '<p><strong>Déclarant :</strong> ' . e($report['declarant_prenom'] . ' ' . $report['declarant_nom']) . '</p>';
                $csaBody .= '<p><a href="' . getBaseUrl() . '/' . url('report_view', ['uuid' => $reportUuid]) . '">Consulter le signalement</a></p>';
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
    $report = getReportByUuid($pdo, $reportUuid);
    if (!$report) {
        return;
    }
    // Get declarant email
    $declarant = getUserById($pdo, (int) $report['declarant_id']);
    if (!$declarant || empty($declarant['email'])) {
        return;
    }
    $registryLabel = REGISTRY_SHORT_LABELS[$report['type']] ?? strtoupper((string) $report['type']);
    $subject = "Réponse à votre signalement $registryLabel — {$report['reference']}";
    $respondent = getUserById($pdo, $respondentId);
    if (!$respondent) {
        return;
    }
    $body = '<html><body>';
    $body .= '<h2>Votre signalement a reçu une réponse</h2>';
    $body .= '<p><strong>Référence :</strong> ' . e($report['reference']) . '</p>';
    $body .= '<p><strong>Répondant :</strong> ' . e($respondent['prenom'] . ' ' . $respondent['nom']) . '</p>';
    $body .= '<p><strong>Nouvel état :</strong> ' . e(ETAT_LABELS[$report['etat']] ?? $report['etat']) . '</p>';
    $body .= '<p><a href="' . getBaseUrl() . '/' . url('report_view', ['uuid' => $reportUuid]) . '">Consulter la réponse</a></p>';
    $body .= '</body></html>';
    sendMail($declarant['email'], $subject, $body);

    // Also notify linked/confirmed agents
    $linkedAgents = getLinkedAgents($pdo, $reportUuid);
    foreach ($linkedAgents as $linkedAgent) {
        if (!empty($linkedAgent['email']) && $linkedAgent['email'] !== $declarant['email']) {
            $linkedSubject = "Réponse au signalement $registryLabel — {$report['reference']}";
            $linkedBody = buildEmailBody(
                'Réponse au signalement',
                '<p>Bonjour ' . e($linkedAgent['prenom'] ?? '') . ',</p>'
                . '<p>Le signalement <strong>' . e($report['reference']) . '</strong> auquel vous êtes rattaché(e) a reçu une réponse.</p>'
                . '<p><strong>Répondant :</strong> ' . e($respondent['prenom'] . ' ' . $respondent['nom']) . '</p>'
                . '<p><strong>Nouvel état :</strong> ' . e(ETAT_LABELS[$report['etat']] ?? $report['etat']) . '</p>'
                . '<p><a href="' . getBaseUrl() . '/' . url('report_view', ['uuid' => $reportUuid]) . '">Consulter la réponse</a></p>'
            );
            sendMail($linkedAgent['email'], $linkedSubject, $linkedBody);
        }
    }
}

/**
 * Notify the agent on whose behalf a RAMI report was filed ("pour le compte de").
 *
 * @param PDO    $pdo        Database connection
 * @param string $reportUuid Report UUID
 */
function notifyPourCompte(PDO $pdo, string $reportUuid): void
{
    $report = getReportByUuid($pdo, $reportUuid);
    if (!$report || empty($report['pour_compte_nom'])) {
        return;
    }
    // Try to find the agent by name
    $stmt = $pdo->prepare('SELECT * FROM users WHERE nom = :nom AND prenom = :prenom AND is_active = 1 LIMIT 1');
    $stmt->execute([':nom' => $report['pour_compte_nom'], ':prenom' => $report['pour_compte_prenom']]);
    $agent = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$agent || empty($agent['email'])) {
        return;
    }
    $subject = "Un signalement RAMI a été déposé pour vous — {$report['reference']}";
    $body = '<html><body>';
    $body .= '<h2>Un signalement a été déposé en votre nom</h2>';
    $body .= '<p><strong>Référence :</strong> ' . e($report['reference']) . '</p>';
    $body .= '<p><strong>Registre :</strong> RAMI</p>';
    $body .= '<p><strong>Objet :</strong> ' . e($report['objet']) . '</p>';
    $body .= '<p><strong>Déposé par :</strong> ' . e($report['declarant_prenom'] . ' ' . $report['declarant_nom']) . '</p>';
    $body .= '<p><a href="' . getBaseUrl() . '/' . url('report_view', ['uuid' => $reportUuid]) . '">Consulter le signalement</a></p>';
    $body .= '</body></html>';
    sendMail($agent['email'], $subject, $body);
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
    $stmt = $pdo->prepare("SELECT DISTINCT email FROM notification_settings WHERE site_id = :site_id AND type = 'site'");
    $stmt->execute([':site_id' => $siteId]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $email) {
        $lower = strtolower((string) $email);
        $seen[] = $lower;
        $emails[] = $email;
    }
    // Global
    $stmt = $pdo->prepare("SELECT DISTINCT email FROM notification_settings WHERE type = 'global'");
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $email) {
        $lower = strtolower((string) $email);
        if (!in_array($lower, $seen)) {
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
    $user = getUserById($pdo, $userId);
    if (!$user || empty($user['email'])) {
        return;
    }
    $appName = getConfig('app_nom_organisation', 'DREETS BFC');
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
    if ($newRole === ROLE_SUPERVISEUR) {
        $body .= "<p>En tant que <strong>Superviseur</strong>, vous pouvez désormais : répondre aux signalements, gérer les utilisateurs, consulter la synthèse et les statistiques, exporter les données, et configurer les paramètres de l'application.</p>";
    } elseif ($newRole === ROLE_CHSCT) {
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
    $report = getReportByUuid($pdo, $reportUuid);
    if (!$report) {
        return;
    }
    foreach ($emails as $email) {
        $email = trim($email);
        if (empty($email)) {
            continue;
        }
        // Create invite token
        $token = createAgentInvite($pdo, $reportUuid, $email);
        // Build confirmation link
        $confirmUrl = url('agent_confirm', ['token' => $token]);
        $subject = 'Vous avez été rattaché(e) au signalement ' . $report['reference'];
        $body = buildEmailBody(
            'Confirmation de rattachement',
            '<p>Bonjour,</p>'
            . '<p>Vous avez été rattaché(e) au signalement <strong>' . e($report['reference']) . '</strong> par le déclarant.</p>'
            . '<p><strong>Objet :</strong> ' . e($report['objet']) . '</p>'
            . '<p>Pour confirmer votre rattachement, cliquez sur le bouton ci-dessous :</p>'
            . '<p style="text-align:center; margin:16px 0;">'
            . '<a href="' . $confirmUrl . '" style="display:inline-block; padding:12px 24px; background:#2563eb; color:#fff; text-decoration:none; border-radius:6px; font-weight:600;">Confirmer mon rattachement</a>'
            . '</p>'
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
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return "$protocol://$host";
}
