<?php

use App\Repository\AnonymizationPolicy;

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
 * Injectable mailer seam — tests uniquement.
 *
 * Quand un callable est posé, sendMail() lui délègue l'essai de transport et
 * retourne son verdict booléen (la sentinelle d'anonymisation est toujours
 * court-circuitée AVANT). Null = transport réel (SMTP puis repli mail()).
 * Permet de tester la consommation des verdicts sans socket SMTP.
 *
 * @param (callable(string, string, string, string): bool)|null $seam
 */
function setMailerSeam(?callable $seam): void
{
    $GLOBALS['__sst_mailer_seam'] = $seam;
}

/**
 * @return (callable(string, string, string, string): bool)|null
 */
function getMailerSeam(): ?callable
{
    $seam = $GLOBALS['__sst_mailer_seam'] ?? null;
    return is_callable($seam) ? $seam : null;
}

/**
 * Build the shared MIME headers block.
 *
 * Source unique des en-têtes (chokepoint sendMail() + envois directs SMTP).
 * Ne porte PAS de CRLF terminal : l'appelant DATA ajoute la ligne vide
 * séparatrice.
 */
function buildMailHeaders(string $from = ''): string
{
    $smtpFrom = $from !== '' ? $from : getConfigService()->get('smtp_from', 'noreply@dreets-bfc.gouv.fr');
    $appName = str_replace(["\r", "\n"], '', getConfigService()->get('app_nom_organisation', 'DREETS BFC'));

    $headers = "From: $appName <$smtpFrom>\r\n";
    $headers .= "Reply-To: $smtpFrom\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= 'X-Mailer: PHP/' . phpversion();
    return $headers;
}

/**
 * Normalise un payload SMTP DATA (helper PUR, sans I/O).
 *
 * 1. Uniformise les fins de ligne en CRLF (RFC 5321) ;
 * 2. « Dot-stuffing » (RFC 5321 §4.5.2) : toute ligne commençant par '.'
 *    reçoit un '.' supplémentaire, sinon une ligne de corps valant '.' serait
 *    interprétée par le serveur comme la fin du message (troncature).
 */
function normalizeSmtpData(string $data): string
{
    $normalized = preg_replace("/\r\n|\r|\n/", "\r\n", $data);
    $normalized ??= $data;
    $stuffed = preg_replace('/^\./m', '..', $normalized);
    return $stuffed ?? $normalized;
}

/**
 * Send an email using configured SMTP settings.
 *
 * CONTRAIT (décision Oracle SMTP) : best-effort, retourne TOUJOURS un bool,
 * ne laisse JAMAIS remonter d'exception transport. Le repli PHP mail() est
 * utilisé par défaut ; passer $allowFallback = false (voir sendSmtpTest) pour
 * obtenir un verdict SMTP strict.
 *
 * @param string $to            Recipient email
 * @param string $subject       Email subject
 * @param string $body          Email body (HTML)
 * @param string $from          Sender email (optional, uses config)
 * @param bool   $allowFallback Autorise le repli PHP mail() si SMTP échoue
 * @return bool True if sent successfully
 */
function sendMail(string $to, string $subject, string $body, string $from = '', bool $allowFallback = true): bool
{
    // Invariant sentinelle (décision produit) — la sentinelle d'anonymisation
    // (AnonymizationPolicy::ANONYMIZED_EMAIL, domaine .invalid RFC 2606) ne
    // doit JAMAIS recevoir de mail. Le chokepoint neutralise tout envoi :
    // aucun échec (succès sémantique « aucun envoi requis ») + journalisation.
    if (AnonymizationPolicy::isAnonymizedEmail($to)) {
        error_log('[SST-MAIL] Envoi bloqué vers la sentinelle d\'anonymisation — aucun envoi requis.');
        return true;
    }

    try {
        $seam = getMailerSeam();
        if ($seam !== null) {
            return $seam($to, $subject, $body, $from);
        }

        $appName = str_replace(["\r", "\n"], '', getConfigService()->get('app_nom_organisation', 'DREETS BFC'));
        $smtpHost = getConfigService()->get('smtp_host', '');
        $headers = buildMailHeaders($from);

        // If SMTP is configured, try SMTP
        if (!empty($smtpHost)) {
            if (sendViaSMTP($to, $subject, $body, $headers)) {
                return true;
            }
            error_log($allowFallback
                ? "[SST-MAIL] SMTP send failed for $to, falling back to mail()"
                : "[SST-MAIL] SMTP send failed for $to (repli mail() désactivé)");
            if (!$allowFallback) {
                return false;
            }
        } elseif (!$allowFallback) {
            error_log("[SST-MAIL] SMTP non configuré — envoi direct impossible pour $to");
            return false;
        }

        // Fallback to PHP mail()
        $fullSubject = "[$appName] $subject";
        return mail($to, $fullSubject, $body, $headers);
    } catch (Throwable $e) {
        // @silent-ok: best-effort transport — le contrat sendMail() interdit
        // tout throw (crash hard impossible depuis un shutdown handler) ;
        // l'échec est journalisé puis retourné à l'appelant (false).
        error_log('[SST-MAIL] Transport failure (aucun throw ne doit remonter) : ' . $e->getMessage());
        return false;
    }
}

/**
 * Envoi strictement SMTP — sans repli PHP mail().
 *
 * Réservé aux boutons « tester la configuration SMTP » : un repli mail()
 * masquerait un échec SMTP et donnerait un faux succès.
 */
function sendSmtpTest(string $to, string $subject, string $body, string $from = ''): bool
{
    return sendMail($to, $subject, $body, $from, false);
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
    $host = getConfigService()->get('smtp_host', '');
    $port = (int) getConfigService()->get('smtp_port', '25');
    $user = getConfigService()->get('smtp_user', '');
    $pass = decryptConfigValue(getConfigService()->get('smtp_pass', ''));
    $encryption = getConfigService()->get('smtp_encryption', 'none');
    $from = getConfigService()->get('smtp_from', 'noreply@dreets-bfc.gouv.fr');

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

    // Send email content — payload SMTP DATA normalisé (CRLF + dot-stuffing).
    // Le dot-stuffing empêche une ligne de corps valant '.' d'être prise pour
    // la fin du message par le serveur.
    $appName = getConfigService()->get('app_nom_organisation', 'DREETS BFC');
    $message = "Subject: [$appName] $subject\r\n"
        . "To: $to\r\n"
        . $headers . "\r\n"
        . "\r\n"
        . $body;
    fwrite($socket, normalizeSmtpData($message) . "\r\n.\r\n");
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
