<?php

/**
 * Settings Handler — Application SST DREETS BFC
 *
 * POST handler: save notification email settings, SMTP config, and app settings.
 * Access: superviseur only
 */

require_once __DIR__ . '/settings_handler_app.php';
require_once __DIR__ . '/settings_handler_sites.php';

$pdo = getDB();
$tab = $_POST['tab'] ?? 'sites';

try {
    $pdo->beginTransaction();

    if ($tab === 'sites') {
        // Delete all existing site notification settings
        $pdo->prepare("DELETE FROM notification_settings WHERE type = 'site'")->execute();

        // Insert new site emails (textarea format: one email per line)
        $siteEmails = $_POST['site_emails'] ?? [];
        if (is_array($siteEmails)) {
            foreach ($siteEmails as $siteId => $emailText) {
                $siteId = (int) $siteId;
                // Parse textarea: split by newlines, trim, filter valid emails
                $lines = preg_split('/[\r\n]+/', (string) $emailText);
                foreach ($lines as $email) {
                    $email = trim($email);
                    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        saveNotificationSetting($pdo, $siteId, 'site', 'all', $email);
                    }
                }
            }
        }
    }

    if ($tab === 'global') {
        // Delete all existing global notification settings
        $pdo->prepare("DELETE FROM notification_settings WHERE type = 'global'")->execute();

        // Insert new global emails (textarea format: one email per line)
        $globalEmailsText = trim($_POST['global_emails'] ?? '');
        if ($globalEmailsText !== '') {
            $lines = preg_split('/[\r\n]+/', $globalEmailsText);
            foreach ($lines as $email) {
                $email = trim($email);
                if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    saveNotificationSetting($pdo, null, 'global', 'all', $email);
                }
            }
        }
    }

    if ($tab === 'smtp') {
        // Update SMTP configuration
        $smtpHost = trim($_POST['smtp_host'] ?? '');
        $smtpPort = trim($_POST['smtp_port'] ?? '25');
        $smtpUser = trim($_POST['smtp_user'] ?? '');
        $smtpPass = trim($_POST['smtp_pass'] ?? '');
        $smtpFrom = trim($_POST['smtp_from'] ?? '');
        $smtpEncryption = trim($_POST['smtp_encryption'] ?? 'none');

        // Validate encryption value
        if (!in_array($smtpEncryption, ['none', 'tls', 'starttls'])) {
            $smtpEncryption = 'none';
        }

        // Validate port is numeric
        if (!is_numeric($smtpPort)) {
            $smtpPort = '25';
        }

        updateConfig($pdo, 'smtp_host', $smtpHost);
        updateConfig($pdo, 'smtp_port', $smtpPort);
        updateConfig($pdo, 'smtp_user', $smtpUser);
        // Only update password if a non-empty value is provided
        if (!empty($smtpPass)) {
            updateConfig($pdo, 'smtp_pass', encryptConfigValue($smtpPass));
        }
        updateConfig($pdo, 'smtp_from', $smtpFrom);
        updateConfig($pdo, 'smtp_encryption', $smtpEncryption);

        // Auto-test SMTP connection after saving
        if (!empty($smtpHost)) {
            require_once __DIR__ . '/../src/mail.php';
            $testTo = $smtpFrom;
            if (!empty($testTo) && filter_var($testTo, $smtpPass !== '' ? FILTER_VALIDATE_EMAIL : FILTER_VALIDATE_EMAIL)) {
                $appName = getConfig('app_nom_organisation', 'DREETS BFC');
                $testSubject = 'Test de connexion SMTP';
                $testBody = '<html><body><h2>Test SMTP</h2><p>Ce message confirme que la connexion SMTP est fonctionnelle.</p></body></html>';
                $sent = sendMail($testTo, $testSubject, $testBody);
                if ($sent) {
                    setFlash('success', 'Configuration SMTP enregistrée. Un e-mail de test a été envoyé à ' . e($testTo) . '.');
                } else {
                    setFlash('warning', 'Configuration SMTP enregistrée, mais l\'envoi de l\'e-mail de test a échoué. Vérifiez les paramètres SMTP.');
                }
                // Skip the generic success message below
                $tab = 'smtp';
                $pdo->commit();
                auditLog($pdo, 'config', 'update', 'Paramètres SMTP modifiés (test ' . ($sent ? 'réussi' : 'échoué') . ')', null, 'config', ['tab' => 'smtp']);
                redirect(url('settings', ['tab' => 'smtp']));
            }
        }
    }

    if ($tab === 'app') {
        handleSettingsAppTab($pdo, $_POST);
    }

    if ($tab === 'manage_sites') {
        handleSettingsManageSitesTab($pdo, $_POST);
    }

    $pdo->commit();

    // Success message varies by tab
    $messages = [
        'sites'         => 'Paramètres de notification par site enregistrés avec succès.',
        'global'        => 'Paramètres de notification globaux enregistrés avec succès.',
        'smtp'          => 'Configuration SMTP enregistrée avec succès.',
        'app'           => 'Paramètres de l\'application enregistrés avec succès.',
        'manage_sites'  => 'Sites mis à jour avec succès.',
    ];
    auditLog($pdo, 'config', 'update', 'Paramètres modifiés — onglet : ' . $tab, null, 'config', ['tab' => $tab]);
    setFlash('success', $messages[$tab] ?? 'Paramètres enregistrés avec succès.');
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('[SST-DB] settings failed: ' . $e->getMessage());
    setFlash('error', 'Erreur lors de l\'enregistrement des paramètres : ' . e($e->getMessage()));
}

redirect(url('settings', ['tab' => $tab]));
