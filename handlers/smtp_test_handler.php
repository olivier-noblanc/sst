<?php

/**
 * SMTP Test Handler — Application SST DREETS BFC
 *
 * POST handler: sends a real test email to a specified recipient
 * using the saved SMTP configuration.
 * Redirects back to SMTP settings with a flash message (no JavaScript needed).
 * Access: superviseur only.
 *
 * REFACTORED: Previously reimplemented the entire SMTP protocol
 * (fsockopen → EHLO → STARTTLS → AUTH → MAIL FROM → RCPT TO → DATA → QUIT).
 * Now uses sendMail() which internally calls sendViaSMTP(), eliminating
 * ~120 lines of duplicated socket code.
 */

// ── Parameters ────────────────────────────────────────────────────────────────

/** @var array<string, string> $_POST */

$to = trim((string) ($_POST['smtp_test_to'] ?? ''));
$host = trim(getConfig('smtp_host', ''));
$from = trim(getConfig('smtp_from', ''));
$port = (int) getConfig('smtp_port', '25');
$encryption = trim(getConfig('smtp_encryption', 'none'));
$appName = getConfig('app_nom_organisation', 'DREETS BFC');

if (!in_array($encryption, ['none', 'tls', 'starttls'], true)) {
    $encryption = 'none';
}

// ── Validation ────────────────────────────────────────────────────────────────

if (empty($to) || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
    setFlash('error', 'Adresse destinataire invalide.');
    redirect(url('settings', ['tab' => 'smtp']));
}

if (empty($host)) {
    setFlash('error', 'Hôte SMTP non renseigné. Enregistrez d\'abord la configuration SMTP.');
    redirect(url('settings', ['tab' => 'smtp']));
}

if (empty($from) || filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
    setFlash('error', 'Adresse d\'expédition (smtp_from) invalide. Enregistrez d\'abord la configuration SMTP.');
    redirect(url('settings', ['tab' => 'smtp']));
}

// ── Send test email via shared sendMail() ─────────────────────────────────────

require_once __DIR__ . '/../src/mail.php';

$date = date('r');
$subject = 'Test de configuration SMTP';

$body = '<html><body>'
      . '<h2>Test de configuration SMTP</h2>'
      . "<p>Ce message confirme que la configuration SMTP de <strong>$appName</strong> est opérationnelle.</p>"
      . "<p><strong>Serveur :</strong> $host:$port ($encryption)<br>"
      . "<strong>Expéditeur :</strong> $from<br>"
      . "<strong>Destinataire :</strong> $to<br>"
      . "<strong>Date :</strong> $date</p>"
      . '</body></html>';

$result = sendMail($to, $subject, $body);

if ($result) {
    setFlash('success', "E-mail de test envoyé avec succès à $to via $host:$port.");
} else {
    setFlash('error', "Échec de l'envoi de l'e-mail de test à $to via $host:$port. Vérifiez la configuration SMTP et les logs PHP.");
}

redirect(url('settings', ['tab' => 'smtp']));
