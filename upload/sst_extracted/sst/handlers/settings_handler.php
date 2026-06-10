<?php
/**
 * Settings Handler — Application SST DREETS BFC
 * 
 * POST handler: save notification email settings, SMTP config, and app settings.
 * Access: superviseur only
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('settings'));
}

// Validate CSRF token
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Erreur de sécurité. Veuillez réessayer.');
    redirect(url('settings'));
}

// Check role
if (!hasRole('superviseur')) {
    setFlash('error', 'Vous n\'avez pas les permissions nécessaires.');
    redirect(url('home'));
}

$pdo = getDB();
$tab = $_POST['tab'] ?? 'sites';

try {
    $pdo->beginTransaction();

    if ($tab === 'sites') {
        // Delete all existing site notification settings
        $pdo->prepare("DELETE FROM notification_settings WHERE type = 'site'")->execute();

        // Insert new site emails
        $siteEmails = $_POST['site_emails'] ?? [];
        if (is_array($siteEmails)) {
            foreach ($siteEmails as $siteId => $emails) {
                $siteId = (int) $siteId;
                if (!is_array($emails)) continue;
                foreach ($emails as $email) {
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

        // Insert new global emails
        $globalEmails = $_POST['global_emails'] ?? [];
        if (is_array($globalEmails)) {
            foreach ($globalEmails as $email) {
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
            updateConfig($pdo, 'smtp_pass', $smtpPass);
        }
        updateConfig($pdo, 'smtp_from', $smtpFrom);
        updateConfig($pdo, 'smtp_encryption', $smtpEncryption);
    }

    if ($tab === 'app') {
        // Update application settings
        $appNomOrganisation = trim($_POST['app_nom_organisation'] ?? '');
        $appNomComplet = trim($_POST['app_nom_complet'] ?? '');
        $appLabelUnite = trim($_POST['app_label_unite'] ?? '');
        $appAdminPrefix = trim($_POST['app_admin_prefix'] ?? '');
        $appAdminUsernames = trim($_POST['app_admin_usernames'] ?? '');

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
        updateConfig($pdo, 'app_admin_prefix', $appAdminPrefix);
        updateConfig($pdo, 'app_admin_usernames', $appAdminUsernames);

        // Agent visibility setting (radio: site / own)
        $agentVisibility = $_POST['app_agent_visibility'] ?? 'site';
        if (!in_array($agentVisibility, ['site', 'own'])) {
            $agentVisibility = 'site';
        }
        updateConfig($pdo, 'app_agent_visibility', $agentVisibility);

        // Legacy key: keep in sync for backward compatibility
        updateConfig($pdo, 'app_agent_see_only_own', $agentVisibility === 'own' ? '1' : '0');

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
    setFlash('success', $messages[$tab] ?? 'Paramètres enregistrés avec succès.');
} catch (Exception $e) {
    $pdo->rollBack();
    setFlash('error', 'Erreur lors de l\'enregistrement des paramètres.');
}

redirect(url('settings', ['tab' => $tab]));
