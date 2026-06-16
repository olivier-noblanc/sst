<?php
/**
 * Settings Handler — Application SST DREETS BFC
 * 
 * POST handler: save notification email settings, SMTP config, and app settings.
 * Access: superviseur only
 */

validatePostRequest(url('settings'), [ROLE_SUPERVISEUR]);

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
                $lines = preg_split('/[\r\n]+/', $emailText);
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
                $testBody = "<html><body><h2>Test SMTP</h2><p>Ce message confirme que la connexion SMTP est fonctionnelle.</p></body></html>";
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
        // Update application settings
        // NOTE: app_version is NOT editable here — it is read from CHANGELOG.md by getAppVersion()
        $appNomOrganisation = trim($_POST['app_nom_organisation'] ?? '');
        $appNomComplet = trim($_POST['app_nom_complet'] ?? '');
        $appLabelUnite = trim($_POST['app_label_unite'] ?? '');
        $appSuperviseurUsernames = trim($_POST['app_superviseur_usernames'] ?? '');

        // Validate: none should be empty (admin usernames can be empty)
        $errors = [];
        if (empty($appNomOrganisation)) {
            $errors[] = 'Le nom de l\'organisation est requis.';
        }
        if (empty($appNomComplet)) {
            $errors[] = 'Le nom complet est requis.';
        }
        if (empty($appLabelUnite)) {
            $errors[] = 'Le libellé des unités est requis.';
        }

        if (!empty($errors)) {
            $pdo->rollBack();
            setFlash('error', implode(' ', $errors));
            redirect(url('settings', ['tab' => 'app']));
        }

        updateConfig($pdo, 'app_nom_organisation', $appNomOrganisation);
        updateConfig($pdo, 'app_nom_complet', $appNomComplet);
        updateConfig($pdo, 'app_label_unite', $appLabelUnite);
        updateConfig($pdo, 'app_superviseur_usernames', $appSuperviseurUsernames);

        // DPO contact (displayed in RGPD preamble)
        $appDpoContact = trim($_POST['app_dpo_contact'] ?? '');
        updateConfig($pdo, 'app_dpo_contact', $appDpoContact);

        // Admin email for error notifications
        $appAdminEmail = trim($_POST['app_admin_email'] ?? '');
        if (!empty($appAdminEmail) && !filter_var($appAdminEmail, FILTER_VALIDATE_EMAIL)) {
            $pdo->rollBack();
            setFlash('error', 'L\'adresse e-mail de l\'administrateur technique n\'est pas valide.');
            redirect(url('settings', ['tab' => 'app']));
        }
        updateConfig($pdo, 'app_admin_email', $appAdminEmail);

        // Display PHP errors toggle (admin debug option)
        $displayErrors = !empty($_POST['app_display_errors']) ? '1' : '0';
        updateConfig($pdo, 'app_display_errors', $displayErrors);

        // Report visibility setting (radio: confidential / agent_choice / public)
        $reportVisibility = $_POST['app_report_visibility'] ?? 'agent_choice';
        if (!in_array($reportVisibility, ['confidential', 'agent_choice', 'public'])) {
            $reportVisibility = 'agent_choice';
        }
        updateConfig($pdo, 'app_report_visibility', $reportVisibility);

        // Per-registry visibility settings
        $registryTypes = [TYPE_RSST, TYPE_RAMI, TYPE_DGI];
        foreach ($registryTypes as $type) {
            $key = 'app_report_visibility_' . $type;
            $value = $_POST[$key] ?? '';
            if ($value !== '' && !in_array($value, ['confidential', 'agent_choice', 'public'])) {
                $value = '';
            }
            updateConfig($pdo, $key, $value);
        }

        // Legacy keys: keep in sync for backward compatibility
        updateConfig($pdo, 'app_agent_visibility', $reportVisibility);
        updateConfig($pdo, 'app_agent_see_only_own', $reportVisibility === 'confidential' ? '1' : '0');

        // Clear the getConfig() static cache so new values are picked up immediately
        clearConfigCache();
    }

    if ($tab === 'manage_sites') {
        $action = $_POST['action'] ?? '';

        if ($action === 'add_site') {
            $code = trim($_POST['new_site_code'] ?? '');
            $nom = trim($_POST['new_site_nom'] ?? '');
            $departement = trim($_POST['new_site_departement'] ?? '');

            if (empty($code) || empty($nom)) {
                $pdo->rollBack();
                setFlash('error', 'Le code et le nom du site sont requis.');
                redirect(url('settings', ['tab' => 'manage_sites']));
            }

            // Check for duplicate code
            $existing = getSiteByCode($pdo, $code);
            if ($existing) {
                $pdo->rollBack();
                setFlash('error', 'Un site avec ce code existe déjà.');
                redirect(url('settings', ['tab' => 'manage_sites']));
            }

            createSite($pdo, $code, $nom, $departement);
        }

        if ($action === 'toggle_site') {
            $siteId = (int) ($_POST['site_id'] ?? 0);
            $isActive = (bool) ($_POST['is_active'] ?? 0);

            if ($siteId > 0) {
                toggleSiteActive($pdo, $siteId, $isActive);
            }
        }

        if ($action === 'delete_site') {
            $siteId = (int) ($_POST['site_id'] ?? 0);

            if ($siteId > 0) {
                // Verify this site can be deleted (no users, no reports)
                $userCount = countUsersBySite($pdo, $siteId);
                $reportCount = countReportsBySite($pdo, $siteId);

                if ($userCount > 0 || $reportCount > 0) {
                    $pdo->rollBack();
                    setFlash('error', 'Impossible de supprimer ce site : il contient ' . $userCount . ' agent(s) et ' . $reportCount . ' signalement(s). Désactivez-le plutôt.');
                    redirect(url('settings', ['tab' => 'manage_sites']));
                }

                $deleted = deleteSite($pdo, $siteId);
                if (!$deleted) {
                    $pdo->rollBack();
                    setFlash('error', 'Erreur lors de la suppression du site.');
                    redirect(url('settings', ['tab' => 'manage_sites']));
                }
            }
        }
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
    auditLog($pdo, 'config', 'update', 'Paramètres modifiés — onglet : ' . ($tab ?? 'inconnu'), null, 'config', ['tab' => $tab ?? 'inconnu']);
    setFlash('success', $messages[$tab] ?? 'Paramètres enregistrés avec succès.');
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('[SST-DB] settings failed: ' . $e->getMessage());
    setFlash('error', 'Erreur lors de l\'enregistrement des paramètres : ' . e($e->getMessage()));
}

redirect(url('settings', ['tab' => $tab]));
