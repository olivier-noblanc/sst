<?php
/**
 * SMTP Test Handler — Application SST DREETS BFC
 *
 * POST handler: sends a real test email to a specified recipient
 * using the saved SMTP configuration.
 * Redirects back to SMTP settings with a flash message (no JavaScript needed).
 * Access: superviseur only.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('settings', ['tab' => 'smtp']));
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Jeton CSRF invalide. Actualisez la page.');
    redirect(url('settings', ['tab' => 'smtp']));
}

if (!hasRole('superviseur')) {
    setFlash('error', 'Accès refusé.');
    redirect(url('settings', ['tab' => 'smtp']));
}

// ── Parameters ────────────────────────────────────────────────────────────────

$to         = trim($_POST['smtp_test_to']   ?? '');
$host       = trim(getConfig('smtp_host', ''));
$port       = (int) getConfig('smtp_port', 25);
$user       = trim(getConfig('smtp_user', ''));
$pass       = trim(getConfig('smtp_pass', ''));
$encryption = trim(getConfig('smtp_encryption', 'none'));
$from       = trim(getConfig('smtp_from', ''));
$appName    = getConfig('app_nom_organisation', 'DREETS BFC');

if (!in_array($encryption, ['none', 'tls', 'starttls'], true)) {
    $encryption = 'none';
}

// ── Validation ────────────────────────────────────────────────────────────────

if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    setFlash('error', 'Adresse destinataire invalide.');
    redirect(url('settings', ['tab' => 'smtp']));
}

if (empty($host)) {
    setFlash('error', 'Hôte SMTP non renseigné. Enregistrez d\'abord la configuration SMTP.');
    redirect(url('settings', ['tab' => 'smtp']));
}

if (empty($from) || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
    setFlash('error', 'Adresse d\'expédition (smtp_from) invalide. Enregistrez d\'abord la configuration SMTP.');
    redirect(url('settings', ['tab' => 'smtp']));
}

if ($port <= 0 || $port > 65535) {
    $port = 25;
}

// ── Socket connection ─────────────────────────────────────────────────────────

$prefix = ($encryption === 'tls') ? 'tls://' : '';
$socket = @fsockopen($prefix . $host, $port, $errno, $errstr, 10);
if (!$socket) {
    setFlash('error', "Impossible de joindre $host:$port — [$errno] $errstr");
    redirect(url('settings', ['tab' => 'smtp']));
}

stream_set_timeout($socket, 10);

$greeting = fgets($socket);
if (substr($greeting, 0, 3) !== '220') {
    fclose($socket);
    setFlash('error', 'Réponse inattendue du serveur : ' . trim($greeting));
    redirect(url('settings', ['tab' => 'smtp']));
}

// EHLO
fwrite($socket, "EHLO localhost\r\n");
while ($line = fgets($socket)) {
    if (substr($line, 3, 1) === ' ') break;
}

// STARTTLS upgrade
if ($encryption === 'starttls') {
    fwrite($socket, "STARTTLS\r\n");
    $r = fgets($socket);
    if (substr($r, 0, 3) !== '220') {
        fwrite($socket, "QUIT\r\n"); fclose($socket);
        setFlash('error', 'STARTTLS refusé : ' . trim($r));
        redirect(url('settings', ['tab' => 'smtp']));
    }
    if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        fclose($socket);
        setFlash('error', 'Échec de la négociation TLS.');
        redirect(url('settings', ['tab' => 'smtp']));
    }
    fwrite($socket, "EHLO localhost\r\n");
    while ($line = fgets($socket)) {
        if (substr($line, 3, 1) === ' ') break;
    }
}

// AUTH LOGIN
if ($user !== '' && $pass !== '') {
    fwrite($socket, "AUTH LOGIN\r\n");
    $r = fgets($socket);
    if (substr($r, 0, 3) !== '334') {
        fwrite($socket, "QUIT\r\n"); fclose($socket);
        setFlash('error', 'AUTH LOGIN refusé : ' . trim($r));
        redirect(url('settings', ['tab' => 'smtp']));
    }
    fwrite($socket, base64_encode($user) . "\r\n");
    fgets($socket);
    fwrite($socket, base64_encode($pass) . "\r\n");
    $r = fgets($socket);
    if (substr($r, 0, 3) !== '235') {
        fwrite($socket, "QUIT\r\n"); fclose($socket);
        setFlash('error', 'Authentification échouée : ' . trim($r));
        redirect(url('settings', ['tab' => 'smtp']));
    }
}

// MAIL FROM
fwrite($socket, "MAIL FROM:<$from>\r\n");
$r = fgets($socket);
if (substr($r, 0, 3) !== '250') {
    fwrite($socket, "QUIT\r\n"); fclose($socket);
    setFlash('error', 'MAIL FROM refusé : ' . trim($r));
    redirect(url('settings', ['tab' => 'smtp']));
}

// RCPT TO
fwrite($socket, "RCPT TO:<$to>\r\n");
$r = fgets($socket);
if (substr($r, 0, 3) !== '250') {
    fwrite($socket, "QUIT\r\n"); fclose($socket);
    setFlash('error', "RCPT TO <$to> refusé : " . trim($r));
    redirect(url('settings', ['tab' => 'smtp']));
}

// DATA
fwrite($socket, "DATA\r\n");
$r = fgets($socket);
if (substr($r, 0, 3) !== '354') {
    fwrite($socket, "QUIT\r\n"); fclose($socket);
    setFlash('error', 'DATA refusé : ' . trim($r));
    redirect(url('settings', ['tab' => 'smtp']));
}

$date    = date('r');
$subject = "[$appName] Test de configuration SMTP";
$body    = "<html><body>"
         . "<h2>Test de configuration SMTP</h2>"
         . "<p>Ce message confirme que la configuration SMTP de <strong>$appName</strong> est opérationnelle.</p>"
         . "<p><strong>Serveur :</strong> $host:$port ($encryption)<br>"
         . "<strong>Expéditeur :</strong> $from<br>"
         . "<strong>Destinataire :</strong> $to<br>"
         . "<strong>Date :</strong> $date</p>"
         . "</body></html>";

fwrite($socket, "Subject: $subject\r\n");
fwrite($socket, "From: $appName <$from>\r\n");
fwrite($socket, "To: $to\r\n");
fwrite($socket, "Date: $date\r\n");
fwrite($socket, "MIME-Version: 1.0\r\n");
fwrite($socket, "Content-Type: text/html; charset=UTF-8\r\n");
fwrite($socket, "\r\n");
fwrite($socket, $body . "\r\n");
fwrite($socket, ".\r\n");
$r = fgets($socket);

fwrite($socket, "QUIT\r\n");
fclose($socket);

if (substr($r, 0, 3) !== '250') {
    setFlash('error', 'Message rejeté par le serveur : ' . trim($r));
    redirect(url('settings', ['tab' => 'smtp']));
}

setFlash('success', "E-mail de test envoyé avec succès à $to via $host:$port.");
redirect(url('settings', ['tab' => 'smtp']));
