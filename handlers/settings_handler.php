<?php

/**
 * Settings Handler — Application SST DREETS BFC
 *
 * POST handler: save notification email settings, SMTP config, and app settings.
 * Access: superviseur only
 */

use App\Repository\NotificationRepository;
use App\Services\ConfigService;

require_once __DIR__ . '/settings_handler_app.php';
require_once __DIR__ . '/settings_handler_sites.php';

/** @var array<string, string> $_POST */

$pdo = getDB();
$tab = (string) ($_POST['tab'] ?? 'sites');
$notifRepo = NotificationRepository::instance();
$configService = ConfigService::getInstance();

try {
    $pdo->beginTransaction();

    if ($tab === 'sites') {
        // Delete all existing site notification settings
        $notifRepo->deleteByType('site');

        // Insert new site emails (textarea format: one email per line)
        /** @var array<string, string> $siteEmails */
        $siteEmails = is_array($_POST['site_emails'] ?? null) ? $_POST['site_emails'] : [];
        if (!empty($siteEmails)) {
            foreach ($siteEmails as $siteId => $emailText) {
                $siteId = (int) $siteId;
                // Parse textarea: split by newlines, trim, filter valid emails
                $lines = preg_split('/[\r\n]+/', (string) $emailText) !== false ? preg_split('/[\r\n]+/', (string) $emailText) : [];
                foreach ($lines as $email) {
                    $email = trim($email);
                    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
                        $notifRepo->save($siteId, 'site', 'all', $email);
                    }
                }
            }
        }
    }

    if ($tab === 'global') {
        // Delete all existing global notification settings
        $notifRepo->deleteByType('global');

        // Insert new global emails (textarea format: one email per line)
        $globalEmailsText = trim((string) ($_POST['global_emails'] ?? ''));
        if ($globalEmailsText !== '') {
            $lines = preg_split('/[\r\n]+/', $globalEmailsText) !== false ? preg_split('/[\r\n]+/', $globalEmailsText) : [];
            foreach ($lines as $email) {
                $email = trim($email);
                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
                    $notifRepo->save(null, 'global', 'all', $email);
                }
            }
        }
    }

    if ($tab === 'smtp') {
        // Update SMTP configuration
        $smtpHost = trim((string) ($_POST['smtp_host'] ?? ''));
        $smtpPort = trim((string) ($_POST['smtp_port'] ?? '25'));
        $smtpUser = trim((string) ($_POST['smtp_user'] ?? ''));
        $smtpPass = trim((string) ($_POST['smtp_pass'] ?? ''));
        $smtpFrom = trim((string) ($_POST['smtp_from'] ?? ''));
        $smtpEncryption = trim((string) ($_POST['smtp_encryption'] ?? 'none'));

        // Validate encryption value
        if (!in_array($smtpEncryption, ['none', 'tls', 'starttls'], true)) {
            $smtpEncryption = 'none';
        }

        // Validate port is numeric
        if (!is_numeric($smtpPort)) {
            $smtpPort = '25';
        }

        $configService->set('smtp_host', $smtpHost);
        $configService->set('smtp_port', $smtpPort);
        $configService->set('smtp_user', $smtpUser);
        // Only update password if a non-empty value is provided
        if (!empty($smtpPass)) {
            $configService->set('smtp_pass', encryptConfigValue($smtpPass));
        }
        $configService->set('smtp_from', $smtpFrom);
        $configService->set('smtp_encryption', $smtpEncryption);

        // Auto-test SMTP connection after saving
        if (!empty($smtpHost)) {
            require_once __DIR__ . '/../src/mail.php';
            $testTo = $smtpFrom;
            if ($testTo !== '' && filter_var($testTo, FILTER_VALIDATE_EMAIL) !== false) {
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

    if ($tab === 'wordcloud') {
        /** @var array<int, mixed> $rawWords */
        $rawWords = is_array($_POST['words'] ?? null) ? $_POST['words'] : [];
        $cleanWords = [];
        if (!empty($rawWords)) {
            foreach ($rawWords as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                /** @var string $wordVal */
                $wordVal = $entry['word'] ?? '';
                /** @var int $weightVal */
                $weightVal = $entry['weight'] ?? 10;
                $word = trim((string) $wordVal);
                $weight = (int) $weightVal;
                if ($word !== '' && $weight >= 1 && $weight <= 20) {
                    $cleanWords[] = ['word' => mb_strtolower($word, 'UTF-8'), 'weight' => $weight];
                }
            }
        }
        $json = json_encode($cleanWords, JSON_UNESCAPED_UNICODE);
        $configService->set('word_cloud_words', is_string($json) ? $json : '[]');
    }

    $pdo->commit();

    // Success message varies by tab
    $messages = [
        'sites'         => 'Paramètres de notification par site enregistrés avec succès.',
        'global'        => 'Paramètres de notification globaux enregistrés avec succès.',
        'smtp'          => 'Configuration SMTP enregistrée avec succès.',
        'app'           => 'Paramètres de l\'application enregistrés avec succès.',
        'manage_sites'  => 'Sites mis à jour avec succès.',
        'wordcloud'     => 'Nuage de mots configuré avec succès.',
    ];
    auditLog($pdo, 'config', 'update', 'Paramètres modifiés — onglet : ' . $tab, null, 'config', ['tab' => $tab]);
    setFlash('success', $messages[$tab] ?? 'Paramètres enregistrés avec succès.');
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('[SST-DB] settings failed: ' . $e->getMessage());
    setFlash('error', 'Erreur lors de l\'enregistrement des paramètres : ' . e($e->getMessage()));
}

redirect(url('settings', ['tab' => $tab]));
