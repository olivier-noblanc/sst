<?php

/**
 * Settings Handler — Application SST DREETS BFC
 *
 * POST handler: save notification email settings, SMTP config, and app settings.
 * Access: superviseur only
 */
use App\Services\HttpService;
use App\Services\SessionService;
use App\Repository\NotificationRepository;

/**
 * POST handler: save notification email settings, SMTP config, and app settings.
 * Access: superviseur only
 *
 * Pas de try/catch ici — crash hard si erreur. Un try/finally avec
 * rollBack() sur toutes les tabs annulait silencieusement les opérations
 * DELETE/INSERT (le finally s'exécute même après exit dans un redirect).
 * AGENTS.md : « Ne JAMAIS catcher silencieusement les erreurs ».
 */

require_once __DIR__ . '/settings_handler_app.php';
require_once __DIR__ . '/settings_handler_sites.php';
require_once __DIR__ . '/settings_handler_registres.php';

/** @var array<string, string> $_POST */

$http = new HttpService();
$session = SessionService::getInstance();

$pdo = getDB();
$tab = (string) ($_POST['tab'] ?? 'sites');
$notifRepo = NotificationRepository::instance();
$configService = getConfigService();

if ($tab === 'sites') {
    $notifRepo->deleteByType('site');
    /** @var array<string, string> $siteEmails */
    $siteEmails = $_POST['site_emails'] ?? [];
    if (!empty($siteEmails)) {
        foreach ($siteEmails as $siteId => $emailText) {
            $siteId = (int) $siteId;
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
    $notifRepo->deleteByType('global');
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
    $smtpHost = trim((string) ($_POST['smtp_host'] ?? ''));
    $smtpPort = trim((string) ($_POST['smtp_port'] ?? '25'));
    $smtpUser = trim((string) ($_POST['smtp_user'] ?? ''));
    $smtpPass = trim((string) ($_POST['smtp_pass'] ?? ''));
    $smtpFrom = trim((string) ($_POST['smtp_from'] ?? ''));
    $smtpEncryption = trim((string) ($_POST['smtp_encryption'] ?? 'none'));

    if (!in_array($smtpEncryption, ['none', 'tls', 'starttls'], true)) {
        $smtpEncryption = 'none';
    }
    if (!is_numeric($smtpPort)) {
        $smtpPort = '25';
    }

    $configService->set('smtp_host', $smtpHost);
    $configService->set('smtp_port', $smtpPort);
    $configService->set('smtp_user', $smtpUser);
    if (!empty($smtpPass)) {
        $configService->set('smtp_pass', encryptConfigValue($smtpPass));
    }
    $configService->set('smtp_from', $smtpFrom);
    $configService->set('smtp_encryption', $smtpEncryption);

    if (!empty($smtpHost)) {
        require_once __DIR__ . '/../src/mail.php';
        $testTo = $smtpFrom;
        if ($testTo !== '' && filter_var($testTo, FILTER_VALIDATE_EMAIL) !== false) {
            $testSubject = 'Test de connexion SMTP';
            $testBody = '<html><body><h2>Test SMTP</h2><p>Ce message confirme que la connexion SMTP est fonctionnelle.</p></body></html>';
            $sent = sendMail($testTo, $testSubject, $testBody);
            if ($sent) {
                $session->setFlash('success', 'Configuration SMTP enregistrée. Un e-mail de test a été envoyé à ' . e($testTo) . '.');
            } else {
                $session->setFlash('warning', 'Configuration SMTP enregistrée, mais l\'envoi de l\'e-mail de test a échoué. Vérifiez les paramètres SMTP.');
            }
            auditLog($pdo, 'config', 'update', 'Paramètres SMTP modifiés (test ' . ($sent ? 'réussi' : 'échoué') . ')', null, 'config', ['tab' => 'smtp']);
            $http->redirect($http->url('settings', ['tab' => 'smtp']));
        }
    }
}

if ($tab === 'app') {
    handleSettingsAppTab($pdo, \App\DTO\UpdateAppSettingsCommand::fromPost($_POST));
}

if ($tab === 'manage_sites') {
    handleSettingsManageSitesTab($pdo, $_POST);
}

if ($tab === 'registres') {
    handleSettingsRegistresTab($pdo, $_POST);
}

if ($tab === 'wordcloud') {
    $registryCode = trim((string) ($_POST['registry_code'] ?? ''));
    /** @var list<array{word: string, weight: int}> $rawWords */
    $rawWords = $_POST['words'] ?? [];
    $cleanWords = [];
    if (!empty($rawWords)) {
        foreach ($rawWords as $entry) {
            $word = trim($entry['word']);
            $weight = $entry['weight'];
            if ($word !== '' && $weight >= 1 && $weight <= 20) {
                $cleanWords[] = ['word' => mb_strtolower($word, 'UTF-8'), 'weight' => $weight];
            }
        }
    }
    $json = json_encode($cleanWords, JSON_UNESCAPED_UNICODE);
    $jsonStr = is_string($json) ? $json : '[]';

    if ($registryCode !== '' && $registryCode !== 'global') {
        $configService->set('word_cloud_words_' . $registryCode, $jsonStr);
    } else {
        $configService->set('word_cloud_words', $jsonStr);
    }
}

$messages = [
    'sites'         => 'Paramètres de notification par site enregistrés avec succès.',
    'global'        => 'Paramètres de notification globaux enregistrés avec succès.',
    'smtp'          => 'Configuration SMTP enregistrée avec succès.',
    'app'           => 'Paramètres de l\'application enregistrés avec succès.',
    'manage_sites'  => 'Sites mis à jour avec succès.',
    'wordcloud'     => 'Nuage de mots configuré avec succès.',
    'registres'     => 'Registres mis à jour avec succès.',
];
auditLog($pdo, 'config', 'update', 'Paramètres modifiés — onglet : ' . $tab, null, 'config', ['tab' => $tab]);
$session->setFlash('success', $messages[$tab] ?? 'Paramètres enregistrés avec succès.');
$http->redirect($http->url('settings', ['tab' => $tab]));
