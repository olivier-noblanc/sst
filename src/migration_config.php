<?php

/**
 * Migration — Config Keys & SMTP Password Encryption
 *
 * Adds missing config_app keys for existing databases,
 * and encrypts plaintext smtp_pass values.
 *
 * Extracted from database.php for readability.
 */

/**
 * Auto-migrate: add missing config_app keys for existing databases.
 * This ensures that databases created before a key was added
 * will automatically receive it on next request.
 *
 * @param PDO $pdo
 */
function migrateConfigKeys(PDO $pdo): void
{
    // NOTE: app_version is no longer stored in the database.
    // The version is now read directly from CHANGELOG.md by getAppVersion().

    $newKeys = [
        'app_superviseur_usernames' => ['', 'text', 'app', 'Logins Windows des superviseurs (séparés par virgule, ex: jean.martin, sophie.dupont). Ces utilisateurs seront automatiquement promus Superviseur lors de leur première connexion via IIS. Utile pour une première installation.', 1],
        'app_agent_see_only_own' => ['0', 'text', 'app', 'Obsolète : utilisez app_report_visibility', 1],
        'app_agent_visibility' => ['agent_choice', 'text', 'app', 'Obsolète : utilisez app_report_visibility', 1],
        'app_report_visibility' => ['agent_choice', 'text', 'app', 'Visibilité des signalements : "confidential" (l\'agent ne voit que ses propres signalements), "agent_choice" (l\'agent choisit au cas par cas, confidentiel par défaut), "public" (tous les signalements du site sont visibles par tous les agents).', 1],
        'app_admin_email' => ['', 'email', 'app', 'Adresse e-mail de l\'administrateur technique. Les erreurs critiques (Fatal, E_ERROR, E_PARSE, etc.) seront automatiquement envoyées à cette adresse pour un diagnostic rapide. Laissez vide pour désactiver les notifications par e-mail.', 1],
        'app_report_visibility_rsst' => ['public', 'text', 'app', 'Visibilité des signalements RSST : "confidential", "agent_choice" ou "public". Par défaut "public" conformément au décret 82-453 art. 3-2 (registre consultable par tout agent).', 1],
        'app_report_visibility_rami' => ['', 'text', 'app', 'Visibilité des signalements RAMI. Laisser vide pour utiliser la visibilité globale. Valeurs : "confidential", "agent_choice" ou "public".', 1],
        'app_report_visibility_dgi' => ['', 'text', 'app', 'Visibilité des signalements DGI. Laisser vide pour utiliser la visibilité globale. Valeurs : "confidential", "agent_choice" ou "public".', 1],
        'app_retention_years' => ['0', 'number', 'app', 'Durée de conservation des signalements traités/abandonnés (en années). 0 = désactivé (conservation illimitée). Doit être fixé après validation du DPO.', 1],
        'app_dpo_contact' => ['', 'text', 'app', 'Coordonnées du Délégué à la Protection des Données (DPO) — affichées dans la mention RGPD du préambule. Ex : dpo@dreets-bfc.gouv.fr', 1],
        'app_alert_delay_days' => ['0', 'number', 'app', 'Délai d\'alerte en jours pour les signalements restés à l\'état « Nouveau ». 0 = désactivé. Si > 0, un e-mail est envoyé aux superviseurs du site lorsqu\'un signalement dépasse ce délai (via lazy cron au login).', 1],
        'last_lazy_cron_check_delays' => ['', 'text', 'system', 'Timestamp de la dernière exécution du lazy cron check_delays. Ne pas modifier manuellement.', 0],
        'last_lazy_cron_anonymize' => ['', 'text', 'system', 'Timestamp de la dernière exécution du lazy cron anonymize. Ne pas modifier manuellement.', 0],
        'app_report_preamble' => ['Pour toute inscription d\'un fait, vous devez être objectif et factuel. Ne pas mentionner de noms de personnes. Vous pouvez joindre un document ou une photo.', 'text', 'app', 'Texte d\'information affiché en haut du formulaire de signalement (zone readonly). Modifiable via l\'administration.', 1],
        'app_rsst_description' => ['Risques liés aux locaux, équipements, ergonomie, conditions environnementales', 'text', 'app', 'Description du registre RSST affichée sur la page d\'accueil. Modifiable via l\'administration.', 1],
        'app_report_create_label' => ['Signaler un événement', 'text', 'app', 'Libellé du bouton et du titre de la page de création de signalement (page d\'accueil, liste des signalements, formulaire, titre d\'onglet). Modifiable via l\'administration.', 1],
    ];

    foreach ($newKeys as $cle => $data) {
        // Check if key already exists
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM config_app WHERE cle = :cle');
        $stmt->execute([':cle' => $cle]);
        $exists = (int) $stmt->fetchColumn();

        if ($exists === 0) {
            // For app_agent_visibility: migrate from old values
            $value = $data[0];
            if ($cle === 'app_agent_visibility') {
                // Check if key already exists with old value
                $stmt2 = $pdo->prepare('SELECT valeur FROM config_app WHERE cle = :cle');
                $stmt2->execute([':cle' => 'app_agent_visibility']);
                $existingValue = $stmt2->fetchColumn();
                if ($existingValue !== false) {
                    // Key exists but with old value — migrate it
                    if ($existingValue === 'site') {
                        $value = 'public'; // old "site" → new "public"
                    } elseif ($existingValue === 'own') {
                        $value = 'confidential'; // old "own" → new "confidential"
                    }
                    // Update the existing row instead of inserting
                    $stmt3 = $pdo->prepare('UPDATE config_app SET valeur = :valeur, libelle = :libelle, updated_at = datetime("now") WHERE cle = :cle');
                    $stmt3->execute([':valeur' => $value, ':libelle' => $data[3], ':cle' => $cle]);
                    continue; // Skip the INSERT below
                }
                // Key doesn't exist at all — also check old app_agent_see_only_own
                $stmt2 = $pdo->prepare('SELECT valeur FROM config_app WHERE cle = :cle');
                $stmt2->execute([':cle' => 'app_agent_see_only_own']);
                $oldValue = $stmt2->fetchColumn();
                if ($oldValue === '1') {
                    $value = 'confidential'; // Migrate: old "see only own" → new "confidential"
                }
            }

            // For app_report_visibility: migrate from app_agent_visibility value
            if ($cle === 'app_report_visibility') {
                $stmt2 = $pdo->prepare('SELECT valeur FROM config_app WHERE cle = :cle');
                $stmt2->execute([':cle' => 'app_agent_visibility']);
                $oldVisValue = $stmt2->fetchColumn();
                if ($oldVisValue !== false) {
                    // Map old 2-mode value to new 3-mode value
                    if ($oldVisValue === 'confidential') {
                        $value = 'agent_choice'; // old "confidential" was actually agent_choice mode
                    } elseif ($oldVisValue === 'public') {
                        $value = 'public';
                    } elseif ($oldVisValue === 'site') {
                        $value = 'public';
                    } elseif ($oldVisValue === 'own') {
                        $value = 'confidential'; // old "own" = truly confidential
                    }
                    // else: keep default 'agent_choice'
                }
            }

            $stmt = $pdo->prepare('INSERT INTO config_app (cle, valeur, type, categorie, libelle, modifiable) VALUES (:cle, :valeur, :type, :categorie, :libelle, :modifiable)');
            $stmt->execute([
                ':cle'        => $cle,
                ':valeur'     => $value,
                ':type'       => $data[1],
                ':categorie'  => $data[2],
                ':libelle'    => $data[3],
                ':modifiable' => $data[4],
            ]);
        }
    }
}

/**
 * Auto-migrate: encrypt plaintext smtp_pass values in config_app.
 * If the value does not start with "enc:" and is not empty,
 * encrypt it with encryptConfigValue() and update the row.
 * This silently migrates existing installations on first boot.
 *
 * Idempotent: once the value starts with "enc:", this is a no-op.
 *
 * @param PDO $pdo
 */
function migrateEncryptSmtpPass(PDO $pdo): void
{
    $stmt = $pdo->prepare("SELECT valeur FROM config_app WHERE cle = 'smtp_pass'");
    $stmt->execute();
    $value = $stmt->fetchColumn();

    // Only encrypt if there's a non-empty value that is not already encrypted
    if ($value !== false && $value !== '' && !str_starts_with((string) $value, 'enc:')) {
        $encrypted = encryptConfigValue((string) $value);
        if (str_starts_with($encrypted, 'enc:')) {
            $upd = $pdo->prepare("UPDATE config_app SET valeur = :valeur, updated_at = datetime('now') WHERE cle = 'smtp_pass'");
            $upd->execute([':valeur' => $encrypted]);
            error_log('[SST-MIGRATION] smtp_pass automatically encrypted (plaintext → enc: prefix).');
        } else {
            // Not a bug — this is the expected outcome when SST_SECRET_KEY isn't
            // configured yet. Leaving the password in plaintext (rather than
            // throwing) keeps SMTP working until an admin sets the key; the
            // clear log line makes the degraded state visible either way.
            error_log('[SST-MIGRATION] smtp_pass encryption failed — SST_SECRET_KEY may be missing. Password remains in plaintext until the key is configured.');
        }
    }
}
