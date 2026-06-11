<?php
/**
 * Mail Module — Application SST DREETS BFC
 *
 * Sends emails using SMTP or PHP mail() as fallback.
 * No external dependencies.
 */

/**
 * Send an email using configured SMTP settings.
 * Falls back to PHP mail() if SMTP is not configured.
 *
 * @param string $to      Recipient email
 * @param string $subject Email subject
 * @param string $body    Email body (HTML)
 * @param string $from    Sender email (optional, uses config)
 * @return bool True if sent successfully
 */
function sendMail(string $to, string $subject, string $body, string $from = ''): bool {
    $smtpHost = getConfig('smtp_host', '');
    $smtpFrom = $from ?: getConfig('smtp_from', 'noreply@dreets-bfc.gouv.fr');
    $appName = getConfig('app_nom_organisation', 'DREETS BFC');

    // Build email headers
    $headers = "From: $appName <$smtpFrom>\r\n";
    $headers .= "Reply-To: $smtpFrom\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    // If SMTP is configured, try SMTP
    if (!empty($smtpHost)) {
        $result = sendViaSMTP($to, $subject, $body, $headers);
        if ($result) return true;
        // Fall through to mail() on SMTP failure
        error_log("[SST-MAIL] SMTP send failed for $to, falling back to mail()");
    }

    // Fallback to PHP mail()
    $fullSubject = "[$appName] $subject";
    return mail($to, $fullSubject, $body, $headers);
}

/**
 * Send email via SMTP using raw socket communication.
 * Supports TLS/STARTTLS.
 *
 * @param string $to      Recipient email
 * @param string $subject Email subject
 * @param string $body    Email body (HTML)
 * @param string $headers Email headers
 * @return bool True if sent successfully
 */
function sendViaSMTP(string $to, string $subject, string $body, string $headers): bool {
    $host = getConfig('smtp_host', '');
    $port = (int) getConfig('smtp_port', '25');
    $user = getConfig('smtp_user', '');
    $pass = getConfig('smtp_pass', '');
    $encryption = getConfig('smtp_encryption', 'none');
    $from = getConfig('smtp_from', 'noreply@dreets-bfc.gouv.fr');

    if (empty($host)) return false;

    // Determine connection prefix
    $prefix = '';
    if ($encryption === 'tls') {
        $prefix = 'tls://';
    }

    $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, 10);
    if (!$socket) {
        error_log("[SST-MAIL] fsockopen failed: [$errno] $errstr");
        return false;
    }

    stream_set_timeout($socket, 10);

    $response = fgets($socket);
    if (substr($response, 0, 3) !== '220') {
        error_log("[SST-MAIL] Unexpected greeting: $response");
        fclose($socket);
        return false;
    }

    // EHLO
    fwrite($socket, "EHLO localhost\r\n");
    // Read multi-line EHLO response
    while ($line = fgets($socket)) {
        if (substr($line, 3, 1) === ' ') break; // Last line of multi-line response
    }

    // STARTTLS if needed
    if ($encryption === 'starttls') {
        fwrite($socket, "STARTTLS\r\n");
        $response = fgets($socket);
        if (substr($response, 0, 3) !== '220') {
            error_log("[SST-MAIL] STARTTLS failed: $response");
            fclose($socket);
            return false;
        }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            error_log("[SST-MAIL] stream_socket_enable_crypto failed");
            fclose($socket);
            return false;
        }
        fwrite($socket, "EHLO localhost\r\n");
        while ($line = fgets($socket)) {
            if (substr($line, 3, 1) === ' ') break;
        }
    }

    // AUTH LOGIN if credentials provided
    if (!empty($user) && !empty($pass)) {
        fwrite($socket, "AUTH LOGIN\r\n");
        $response = fgets($socket);
        if (substr($response, 0, 3) !== '334') {
            error_log("[SST-MAIL] AUTH LOGIN rejected: $response");
            fclose($socket);
            return false;
        }
        fwrite($socket, base64_encode($user) . "\r\n");
        fgets($socket);
        fwrite($socket, base64_encode($pass) . "\r\n");
        $response = fgets($socket);
        if (substr($response, 0, 3) !== '235') {
            error_log("[SST-MAIL] SMTP auth failed: $response");
            fclose($socket);
            return false;
        }
    }

    // MAIL FROM
    fwrite($socket, "MAIL FROM:<$from>\r\n");
    $response = fgets($socket);
    if (substr($response, 0, 3) !== '250') {
        error_log("[SST-MAIL] MAIL FROM rejected: $response");
        fclose($socket);
        return false;
    }

    // RCPT TO
    fwrite($socket, "RCPT TO:<$to>\r\n");
    $response = fgets($socket);
    if (substr($response, 0, 3) !== '250') {
        error_log("[SST-MAIL] RCPT TO rejected: $response");
        fclose($socket);
        return false;
    }

    // DATA
    fwrite($socket, "DATA\r\n");
    $response = fgets($socket);
    if (substr($response, 0, 3) !== '354') {
        error_log("[SST-MAIL] DATA rejected: $response");
        fclose($socket);
        return false;
    }

    // Send email content
    $appName = getConfig('app_nom_organisation', 'DREETS BFC');
    fwrite($socket, "Subject: [$appName] $subject\r\n");
    fwrite($socket, "To: $to\r\n");
    fwrite($socket, $headers . "\r\n");
    fwrite($socket, "\r\n");
    fwrite($socket, $body . "\r\n");
    fwrite($socket, ".\r\n");
    $response = fgets($socket);

    // QUIT
    fwrite($socket, "QUIT\r\n");
    fclose($socket);

    $ok = substr($response, 0, 3) === '250';
    if (!$ok) {
        error_log("[SST-MAIL] Message send failed: $response");
    }
    return $ok;
}

/**
 * Notify relevant people about a new report.
 *
 * @param PDO    $pdo       Database connection
 * @param int    $reportId  The new report ID
 * @param string $type      Report type (rsst/rami/dgi)
 * @param int    $siteId    Site ID where report was filed
 */
function notifyNewReport(PDO $pdo, int $reportId, string $type, int $siteId): void {
    $report = getReportById($pdo, $reportId);
    if (!$report) return;

    $registryLabel = REGISTRY_SHORT_LABELS[$type] ?? strtoupper($type);
    $subject = "Nouveau signalement $registryLabel — {$report['reference']}";

    $body = "<html><body>";
    $body .= "<h2>Nouveau signalement enregistré</h2>";
    $body .= "<p><strong>Référence :</strong> " . e($report['reference']) . "</p>";
    $body .= "<p><strong>Registre :</strong> $registryLabel</p>";
    $body .= "<p><strong>Objet :</strong> " . e($report['objet']) . "</p>";
    $body .= "<p><strong>Déclarant :</strong> " . e($report['declarant_prenom'] . ' ' . $report['declarant_nom']) . "</p>";
    $body .= "<p><strong>Date de l'événement :</strong> " . formatDateFR($report['date_evenement']) . "</p>";
    $body .= "<p><a href=\"" . getBaseUrl() . "/" . url('report_view', ['uuid' => getReportById($pdo, $reportId)['uuid']]) . "\">Consulter le signalement</a></p>";
    $body .= "</body></html>";

    // Collect recipients: per-site + global
    $recipients = getNotificationRecipients($pdo, $siteId);

    foreach ($recipients as $email) {
        sendMail($email, $subject, $body);
    }
}

/**
 * Notify the declarant that their report has received a response.
 *
 * @param PDO $pdo          Database connection
 * @param int $reportId     Report ID
 * @param int $respondentId The responding user's ID
 */
function notifyReportResponse(PDO $pdo, int $reportId, int $respondentId): void {
    $report = getReportById($pdo, $reportId);
    if (!$report) return;

    // Get declarant email
    $declarant = getUserById($pdo, (int) $report['declarant_id']);
    if (!$declarant || empty($declarant['email'])) return;

    $registryLabel = REGISTRY_SHORT_LABELS[$report['type']] ?? strtoupper($report['type']);
    $subject = "Réponse à votre signalement $registryLabel — {$report['reference']}";

    $respondent = getUserById($pdo, $respondentId);

    $body = "<html><body>";
    $body .= "<h2>Votre signalement a reçu une réponse</h2>";
    $body .= "<p><strong>Référence :</strong> " . e($report['reference']) . "</p>";
    $body .= "<p><strong>Répondant :</strong> " . e($respondent['prenom'] . ' ' . $respondent['nom']) . "</p>";
    $body .= "<p><strong>Nouvel état :</strong> " . e(ETAT_LABELS[$report['etat']] ?? $report['etat']) . "</p>";
    $body .= "<p><a href=\"" . getBaseUrl() . "/" . url('report_view', ['uuid' => getReportById($pdo, $reportId)['uuid']]) . "\">Consulter le signalement</a></p>";
    $body .= "</body></html>";

    sendMail($declarant['email'], $subject, $body);
}

/**
 * Notify the agent on whose behalf a RAMI report was filed ("pour le compte de").
 *
 * @param PDO $pdo       Database connection
 * @param int $reportId  Report ID
 */
function notifyPourCompte(PDO $pdo, int $reportId): void {
    $report = getReportById($pdo, $reportId);
    if (!$report || empty($report['pour_compte_nom'])) return;

    // Try to find the agent by name
    $stmt = $pdo->prepare("SELECT * FROM users WHERE nom = :nom AND prenom = :prenom AND is_active = 1 LIMIT 1");
    $stmt->execute([':nom' => $report['pour_compte_nom'], ':prenom' => $report['pour_compte_prenom']]);
    $agent = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$agent || empty($agent['email'])) return;

    $subject = "Un signalement RAMI a été déposé pour vous — {$report['reference']}";

    $body = "<html><body>";
    $body .= "<h2>Un signalement a été déposé en votre nom</h2>";
    $body .= "<p><strong>Référence :</strong> " . e($report['reference']) . "</p>";
    $body .= "<p><strong>Registre :</strong> RAMI</p>";
    $body .= "<p><strong>Objet :</strong> " . e($report['objet']) . "</p>";
    $body .= "<p><strong>Déposé par :</strong> " . e($report['declarant_prenom'] . ' ' . $report['declarant_nom']) . "</p>";
    $body .= "<p><a href=\"" . getBaseUrl() . "/" . url('report_view', ['uuid' => getReportById($pdo, $reportId)['uuid']]) . "\">Consulter le signalement</a></p>";
    $body .= "</body></html>";

    sendMail($agent['email'], $subject, $body);
}

/**
 * Get all notification recipients for a given site.
 *
 * @param PDO $pdo     Database connection
 * @param int $siteId  Site ID
 * @return array       Array of email strings
 */
function getNotificationRecipients(PDO $pdo, int $siteId): array {
    $emails = [];

    // Per-site
    $stmt = $pdo->prepare("SELECT DISTINCT email FROM notification_settings WHERE site_id = :site_id AND type = 'site'");
    $stmt->execute([':site_id' => $siteId]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $email) {
        $emails[] = $email;
    }

    // Global
    $stmt = $pdo->prepare("SELECT DISTINCT email FROM notification_settings WHERE type = 'global'");
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $email) {
        if (!in_array($email, $emails)) {
            $emails[] = $email;
        }
    }

    return $emails;
}

/**
 * Get base URL for links in emails.
 *
 * @return string
 */
function getBaseUrl(): string {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return "$protocol://$host";
}
