<?php
/**
 * SMTP Test Handler — Application SST DREETS BFC
 *
 * POST handler: sends a real test email to a specified recipient
 * using the form values (before they are saved).
 * Returns JSON {ok: bool, message: string}.
 * Access: superviseur only.
 */

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Méthode non autorisée.']);
    exit;
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Jeton CSRF invalide. Actualisez la page.']);
    exit;
}

if (!hasRole('superviseur')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Accès refusé.']);
    exit;
}

// ── Parameters ────────────────────────────────────────────────────────────────

$to         = trim($_POST['smtp_test_to']   ?? '');
$host       = trim($_POST['smtp_host']       ?? getConfig('smtp_host', ''));
$port       = (int) ($_POST['smtp_port']     ?? getConfig('smtp_port', 25));
$user       = trim($_POST['smtp_user']       ?? getConfig('smtp_user', ''));
$pass       = trim($_POST['smtp_pass']       ?? '');
$encryption = trim($_POST['smtp_encryption'] ?? getConfig('smtp_encryption', 'none'));
$from       = trim($_POST['smtp_from']       ?? getConfig('smtp_from', ''));
$appName    = getConfig('app_nom_organisation', 'DREETS BFC');

// Empty password field = keep saved password
if ($pass === '') {
    $pass = getConfig('smtp_pass', '');
}

if (!in_array($encryption, ['none', 'tls', 'starttls'], true)) {
    $encryption = 'none';
}

// ── Validation ────────────────────────────────────────────────────────────────

if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'message' => 'Adresse destinataire invalide.']);
    exit;
}

if (empty($host)) {
    echo json_encode(['ok' => false, 'message' => 'Hôte SMTP non renseigné.']);
    exit;
}

if ($port <= 0 || $port > 65535) {
    $port = 25;
}

if (empty($from) || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'message' => 'Adresse d\'expédition (smtp_from) invalide.']);
    exit;
}

// ── Socket connection ─────────────────────────────────────────────────────────

$prefix = ($encryption === 'tls') ? 'tls://' : '';
$socket = @fsockopen($prefix . $host, $port, $errno, $errstr, 10);
if (!$socket) {
    echo json_encode(['ok' => false, 'message' => "Impossible de joindre $host:$port — [$errno] $errstr"]);
    exit;
}

stream_set_timeout($socket, 10);

$greeting = fgets($socket);
if (substr($greeting, 0, 3) !== '220') {
    fclose($socket);
    echo json_encode(['ok' => false, 'message' => 'Réponse inattendue du serveur : ' . trim($greeting)]);
    exit;
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
        echo json_encode(['ok' => false, 'message' => 'STARTTLS refusé : ' . trim($r)]);
        exit;
    }
    if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        fclose($socket);
        echo json_encode(['ok' => false, 'message' => 'Échec de la négociation TLS.']);
        exit;
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
        echo json_encode(['ok' => false, 'message' => 'AUTH LOGIN refusé : ' . trim($r)]);
        exit;
    }
    fwrite($socket, base64_encode($user) . "\r\n");
    fgets($socket);
    fwrite($socket, base64_encode($pass) . "\r\n");
    $r = fgets($socket);
    if (substr($r, 0, 3) !== '235') {
        fwrite($socket, "QUIT\r\n"); fclose($socket);
        echo json_encode(['ok' => false, 'message' => 'Authentification échouée : ' . trim($r)]);
        exit;
    }
}

// MAIL FROM
fwrite($socket, "MAIL FROM:<$from>\r\n");
$r = fgets($socket);
if (substr($r, 0, 3) !== '250') {
    fwrite($socket, "QUIT\r\n"); fclose($socket);
    echo json_encode(['ok' => false, 'message' => 'MAIL FROM refusé : ' . trim($r)]);
    exit;
}

// RCPT TO
fwrite($socket, "RCPT TO:<$to>\r\n");
$r = fgets($socket);
if (substr($r, 0, 3) !== '250') {
    fwrite($socket, "QUIT\r\n"); fclose($socket);
    echo json_encode(['ok' => false, 'message' => "RCPT TO <$to> refusé : " . trim($r)]);
    exit;
}

// DATA
fwrite($socket, "DATA\r\n");
$r = fgets($socket);
if (substr($r, 0, 3) !== '354') {
    fwrite($socket, "QUIT\r\n"); fclose($socket);
    echo json_encode(['ok' => false, 'message' => 'DATA refusé : ' . trim($r)]);
    exit;
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
    echo json_encode(['ok' => false, 'message' => 'Message rejeté par le serveur : ' . trim($r)]);
    exit;
}

echo json_encode(['ok' => true, 'message' => "E-mail de test envoyé à $to via $host:$port."]);
exit;
