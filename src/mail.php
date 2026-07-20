<?php

/**
 * Mail Module — Application SST DREETS BFC
 *
 * Sends emails using SMTP or PHP mail() as fallback.
 * No external dependencies.
 *
 * Notification dispatch functions are in mail_notifications.php.
 * Email body wrapper is renderEmailBody() in src/mail/email_renderer.php.
 */

require_once __DIR__ . '/mail_notifications.php';

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
function sendMail(string $to, string $subject, string $body, string $from = ''): bool
{
    $smtpHost = getConfig('smtp_host', '');
    $smtpFrom = $from !== '' ? $from : getConfig('smtp_from', 'noreply@dreets-bfc.gouv.fr');
    $appName = str_replace(["\r", "\n"], '', getConfig('app_nom_organisation', 'DREETS BFC'));

    // Build email headers
    $headers = "From: $appName <$smtpFrom>\r\n";
    $headers .= "Reply-To: $smtpFrom\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= 'X-Mailer: PHP/' . phpversion();

    // If SMTP is configured, try SMTP
    if (!empty($smtpHost)) {
        $result = sendViaSMTP($to, $subject, $body, $headers);
        if ($result) {
            return true;
        }
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
function sendViaSMTP(string $to, string $subject, string $body, string $headers): bool
{
    $host = getConfig('smtp_host', '');
    $port = (int) getConfig('smtp_port', '25');
    $user = getConfig('smtp_user', '');
    $pass = decryptConfigValue(getConfig('smtp_pass', ''));
    $encryption = getConfig('smtp_encryption', 'none');
    $from = getConfig('smtp_from', 'noreply@dreets-bfc.gouv.fr');

    if (empty($host)) {
        return false;
    }

    // CRLF injection prevention: reject email addresses containing CR/LF
    if (str_contains($to, "\r") || str_contains($to, "\n") || str_contains($from, "\r") || str_contains($from, "\n")) {
        error_log('[SST-MAIL] CRLF injection attempt blocked in email address');
        return false;
    }

    // Determine connection prefix
    $prefix = '';
    if ($encryption === 'tls') {
        $prefix = 'tls://';
    }

    $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, 10);
    if ($socket === false) {
        error_log("[SST-MAIL] fsockopen failed: [$errno] $errstr");
        return false;
    }

    stream_set_timeout($socket, 10);

    $response = fgets($socket);
    if ($response === false || !str_starts_with($response, '220')) {
        error_log("[SST-MAIL] Unexpected greeting: $response");
        fclose($socket);
        return false;
    }

    // EHLO
    fwrite($socket, "EHLO localhost\r\n");
    // Read multi-line EHLO response
    while ($line = fgets($socket)) {
        if (substr($line, 3, 1) === ' ') {
            break;
        } // Last line of multi-line response
    }

    // STARTTLS if needed
    if ($encryption === 'starttls') {
        fwrite($socket, "STARTTLS\r\n");
        $response = fgets($socket);
        if ($response === false || !str_starts_with($response, '220')) {
            error_log("[SST-MAIL] STARTTLS failed: $response");
            fclose($socket);
            return false;
        }
        if (stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true) {
            error_log('[SST-MAIL] stream_socket_enable_crypto failed');
            fclose($socket);
            return false;
        }
        fwrite($socket, "EHLO localhost\r\n");
        while ($line = fgets($socket)) {
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }
    }

    // AUTH LOGIN if credentials provided
    if (!empty($user) && !empty($pass)) {
        fwrite($socket, "AUTH LOGIN\r\n");
        $response = fgets($socket);
        if ($response === false || !str_starts_with($response, '334')) {
            error_log("[SST-MAIL] AUTH LOGIN rejected: $response");
            fclose($socket);
            return false;
        }
        fwrite($socket, base64_encode($user) . "\r\n");
        fgets($socket);
        fwrite($socket, base64_encode($pass) . "\r\n");
        $response = fgets($socket);
        if ($response === false || !str_starts_with($response, '235')) {
            error_log("[SST-MAIL] SMTP auth failed: $response");
            fclose($socket);
            return false;
        }
    }

    // MAIL FROM
    fwrite($socket, "MAIL FROM:<$from>\r\n");
    $response = fgets($socket);
    if ($response === false || !str_starts_with($response, '250')) {
        error_log("[SST-MAIL] MAIL FROM rejected: $response");
        fclose($socket);
        return false;
    }

    // RCPT TO
    fwrite($socket, "RCPT TO:<$to>\r\n");
    $response = fgets($socket);
    if ($response === false || !str_starts_with($response, '250')) {
        error_log("[SST-MAIL] RCPT TO rejected: $response");
        fclose($socket);
        return false;
    }

    // DATA
    fwrite($socket, "DATA\r\n");
    $response = fgets($socket);
    if ($response === false || !str_starts_with($response, '354')) {
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

    $ok = $response !== false && str_starts_with($response, '250');
    if (!$ok) {
        error_log("[SST-MAIL] Message send failed: $response");
    }
    return $ok;
}
