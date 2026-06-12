<?php
/**
 * Settings Page — Application SST DREETS BFC
 * 
 * Notification email configuration per site and globally.
 * Plus SMTP and Application configuration tabs.
 * Access: superviseur only
 */
requireRole(['superviseur']);

$pdo = getDB();

// Get sites
$sites = getAllSites($pdo);

// Get current notification settings
$currentSettings = getNotificationSettings($pdo);

// Organize settings: by site and global
$siteEmails = [];
$globalEmails = [];

foreach ($currentSettings as $setting) {
    if ($setting['type'] === 'global') {
        $globalEmails[] = $setting;
    } else {
        $sId = (int) $setting['site_id'];
        if (!isset($siteEmails[$sId])) {
            $siteEmails[$sId] = [];
        }
        $siteEmails[$sId][] = $setting;
    }
}

// Active tab
$activeTab = $_GET['tab'] ?? 'sites';

$pageTitle = 'Paramètres';
?>

<h1 class="page-title">Paramètres</h1>

<?php require __DIR__ . '/../templates/alert.php'; ?>

<!-- Tabs -->
<div class="tab-bar">
    <a href="<?php echo url('settings', ['tab' => 'sites']); ?>"
       class="settings-tab <?php echo $activeTab === 'sites' ? 'settings-tab--active' : ''; ?>">
        &#x1F4CD; Notifications par site
    </a>
    <a href="<?php echo url('settings', ['tab' => 'global']); ?>"
       class="settings-tab <?php echo $activeTab === 'global' ? 'settings-tab--active' : ''; ?>">
        &#x1F310; Notifications globales
    </a>
    <a href="<?php echo url('settings', ['tab' => 'smtp']); ?>"
       class="settings-tab <?php echo $activeTab === 'smtp' ? 'settings-tab--active' : ''; ?>">
        &#x1F4E7; Configuration SMTP
    </a>
    <a href="<?php echo url('settings', ['tab' => 'manage_sites']); ?>"
       class="settings-tab <?php echo $activeTab === 'manage_sites' ? 'settings-tab--active' : ''; ?>">
        &#x1F3E2; Gestion des sites
    </a>
    <a href="<?php echo url('settings', ['tab' => 'app']); ?>"
       class="settings-tab <?php echo $activeTab === 'app' ? 'settings-tab--active' : ''; ?>">
        &#x2699;&#xFE0F; Paramètres de l'application
    </a>

</div>

<?php if ($activeTab === 'sites' || $activeTab === 'global'): ?>
<!-- Notification tabs (shared form) -->
<form method="POST" action="<?php echo url('settings'); ?>" id="settingsForm">
    <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
    <input type="hidden" name="tab" value="<?php echo e($activeTab); ?>">

    <?php if ($activeTab === 'sites'): ?>
    <!-- Notifications par site -->
    <?php foreach ($sites as $site): ?>
        <?php
            $sId = (int) $site['id'];
            $existingEmails = [];
            if (isset($siteEmails[$sId])) {
                foreach ($siteEmails[$sId] as $se) {
                    $existingEmails[] = $se['email'];
                }
            }
        ?>
        <div class="card mb-4">
            <h3 class="card__subtitle">
                <span class="badge badge--rsst badge--sm"><?php echo e($site['code']); ?></span>
                <?php echo e($site['nom']); ?>
            </h3>
            <div class="form-group">
                <label for="site_emails_<?php echo e((string)$sId); ?>">Adresses e-mail de notification</label>
                <textarea id="site_emails_<?php echo e((string)$sId); ?>" name="site_emails[<?php echo e((string)$sId); ?>]"
                          rows="3" class="form-control"
                          aria-describedby="hint_site_emails_<?php echo e((string)$sId); ?>"
                          placeholder="Une adresse par ligne&#10;ex: jean.martin@dreets.gouv.fr&#10;sophie.dupont@dreets.gouv.fr"><?php echo e(implode("\n", $existingEmails)); ?></textarea>
                <div class="form-hint" id="hint_site_emails_<?php echo e((string)$sId); ?>">Une adresse e-mail par ligne. Laissez vide pour aucune notification.</div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($activeTab === 'global'): ?>
    <!-- Notifications globales -->
    <div class="card">
        <h3 class="card__subtitle">Adresses e-mail de notification globales</h3>
        <p class="text-muted text-small mb-4">Ces adresses recevront des notifications pour tous les sites et tous les registres.</p>
        <div class="form-group">
            <label for="global_emails">Adresses e-mail</label>
            <?php $globalEmailList = []; foreach ($globalEmails as $ge) { $globalEmailList[] = $ge['email']; } ?>
            <textarea id="global_emails" name="global_emails" rows="4" class="form-control"
                      aria-describedby="hint_global_emails"
                      placeholder="Une adresse par ligne&#10;ex: direction@dreets.gouv.fr&#10;chsct@dreets.gouv.fr"><?php echo e(implode("\n", $globalEmailList)); ?></textarea>
            <div class="form-hint" id="hint_global_emails">Une adresse e-mail par ligne. Laissez vide pour aucune notification globale.</div>
        </div>
    </div>
    <?php endif; ?>

    <div class="form-actions">
        <button type="submit" class="btn btn--success">Enregistrer les modifications</button>
        <a href="<?php echo url('settings'); ?>" class="btn btn--outline">Annuler</a>
    </div>
</form>
<?php endif; ?>

<?php if ($activeTab === 'smtp'): ?>
<!-- SMTP Configuration -->
<form method="POST" action="<?php echo url('settings'); ?>">
    <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
    <input type="hidden" name="tab" value="smtp">

    <div class="card">
        <h3 class="card__title">&#x1F4E7; Configuration SMTP</h3>
        <p class="text-muted text-small mb-5">Configurez le serveur SMTP pour l'envoi des e-mails de notification.</p>

        <div class="form-row form-row--2-1">
            <div class="form-group">
                <label for="smtp_host">Serveur SMTP</label>
                <input type="text" id="smtp_host" name="smtp_host" class="form-control"
                       value="<?php echo e(getConfig('smtp_host')); ?>"
                       placeholder="smtp.example.com">
            </div>
            <div class="form-group">
                <label for="smtp_port">Port SMTP</label>
                <input type="number" id="smtp_port" name="smtp_port" class="form-control"
                       value="<?php echo e(getConfig('smtp_port', '25')); ?>"
                       placeholder="25">
            </div>
        </div>

        <div class="form-row form-row--1-1">
            <div class="form-group">
                <label for="smtp_user">Utilisateur SMTP</label>
                <input type="text" id="smtp_user" name="smtp_user" class="form-control"
                       value="<?php echo e(getConfig('smtp_user')); ?>"
                       placeholder="utilisateur@exemple.com">
            </div>
            <div class="form-group">
                <label for="smtp_pass">Mot de passe SMTP</label>
                <input type="password" id="smtp_pass" name="smtp_pass" class="form-control"
                       value=""
                       placeholder="<?php echo getConfig('smtp_pass') ? '•••••••• (laisser vide pour conserver)' : 'Non défini'; ?>">
            </div>
        </div>

        <div class="form-row form-row--1-1">
            <div class="form-group">
                <label for="smtp_from">Adresse d'expédition</label>
                <input type="email" id="smtp_from" name="smtp_from" class="form-control"
                       value="<?php echo e(getConfig('smtp_from')); ?>"
                       placeholder="noreply@dreets-bfc.gouv.fr">
            </div>
            <div class="form-group">
                <label for="smtp_encryption">Chiffrement</label>
                <select id="smtp_encryption" name="smtp_encryption" class="form-control">
                    <?php
                        $currentEncryption = getConfig('smtp_encryption', 'none');
                        $options = ['none' => 'Aucun', 'tls' => 'TLS', 'starttls' => 'STARTTLS'];
                    ?>
                    <?php foreach ($options as $val => $label): ?>
                    <option value="<?php echo e($val); ?>" <?php echo $currentEncryption === $val ? 'selected' : ''; ?>>
                        <?php echo e($label); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn--success">Enregistrer les modifications</button>
        <a href="<?php echo url('settings', ['tab' => 'smtp']); ?>" class="btn btn--outline">Annuler</a>
    </div>
</form>

<!-- SMTP Test (separate form — POST + redirect, no JavaScript) -->
<form method="POST" action="<?php echo url('smtp_test'); ?>" class="smtp-test-section">
    <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
    <div class="card smtp-test-section">
        <h4 class="card__subtitle">&#x1F9EA; Test d'envoi SMTP</h4>
        <p class="text-muted text-small mb-3">Envoyez un e-mail de test pour vérifier la configuration SMTP ci-dessus.</p>
        <div class="smtp-test-row">
            <div class="form-group smtp-test-field">
                <label for="smtp_test_to">Adresse destinataire</label>
                <input type="email" id="smtp_test_to" name="smtp_test_to" class="form-control"
                       placeholder="destinataire@exemple.com" required>
            </div>
            <button type="submit" class="btn btn--outline">Envoyer un e-mail de test</button>
        </div>
    </div>
</form>
<?php endif; ?>

<?php if ($activeTab === 'app'): ?>
<!-- Application Settings -->
<form method="POST" action="<?php echo url('settings'); ?>">
    <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
    <input type="hidden" name="tab" value="app">

    <div class="card">
        <h3 class="card__title">&#x2699;&#xFE0F; Paramètres de l'application</h3>
        <p class="text-muted text-small mb-5">Configurez les paramètres généraux de l'application.</p>

        <div class="form-group">
            <label for="app_nom_organisation">Nom de l'organisation</label>
            <input type="text" id="app_nom_organisation" name="app_nom_organisation" class="form-control"
                   value="<?php echo e(getConfig('app_nom_organisation', 'DREETS BFC')); ?>"
                   placeholder="DREETS BFC">
        </div>

        <div class="form-group">
            <label for="app_nom_complet">Nom complet</label>
            <input type="text" id="app_nom_complet" name="app_nom_complet" class="form-control"
                   value="<?php echo e(getConfig('app_nom_complet', 'DREETS Bourgogne-Franche-Comté')); ?>"
                   placeholder="DREETS Bourgogne-Franche-Comté">
        </div>

        <div class="form-group">
            <label for="app_label_unite">Libellé des unités</label>
            <input type="text" id="app_label_unite" name="app_label_unite" class="form-control"
                   value="<?php echo e(getConfig('app_label_unite', 'UR')); ?>"
                   placeholder="UR">
            <small class="text-muted block mt-1">
                Exemple : UR, UD, Direction... Ce libellé est utilisé partout dans l'application.
            </small>
        </div>

        <div class="form-group">
            <label for="app_superviseur_usernames">Logins Windows des superviseurs (liste explicite)</label>
            <input type="text" id="app_superviseur_usernames" name="app_superviseur_usernames" class="form-control"
                   value="<?php echo e(getConfig('app_superviseur_usernames', '')); ?>"
                   placeholder="jean.martin, sophie.dupont">
            <small class="text-muted block mt-1">
                Séparés par des virgules. Ces utilisateurs seront automatiquement promus <strong>Superviseur</strong>
                immédiatement (dès la prochaine page consultée). Utile pour désigner les premiers
                superviseurs sans avoir à passer par la base de données.
            </small>
        </div>

        <div class="separator">
            <h4 class="card__subtitle">&#x1F4E7; Administrateur technique</h4>
            <p class="text-muted text-small mb-3">Les erreurs critiques (Fatal, Parse, etc.) seront automatiquement envoyées par e-mail à cette adresse pour un diagnostic rapide. Laissez vide pour désactiver.</p>
            <div class="form-group">
                <label for="app_admin_email">E-mail administrateur technique</label>
                <input type="email" id="app_admin_email" name="app_admin_email" class="form-control"
                       value="<?php echo e(getConfig('app_admin_email', '')); ?>"
                       placeholder="admin.tech@dreets-bfc.gouv.fr">
                <small class="text-muted block mt-1">
                    Une même erreur ne déclenche qu'un seul e-mail toutes les 5 minutes pour éviter le spam.
                    Consultez le <a href="<?php echo url('logs'); ?>">Journal d'erreurs</a> pour voir toutes les entrées.
                </small>
            </div>
        </div>

        <div class="separator">
            <h4 class="card__subtitle">&#x1F512; Visibilité des signalements</h4>
            <p class="text-muted text-small mb-3">Détermine quels signalements les agents peuvent consulter dans les registres. Les superviseurs et membres du CHSCT voient toujours tous les signalements.</p>
            <fieldset class="form-group visibility-radios" id="visibility-radios">
                <legend class="visibility-legend">Visibilité des signalements</legend>
                <?php $currentVisibility = getConfig('app_report_visibility', 'agent_choice'); ?>
                <div class="visibility-radios">
                    <label class="visibility-radio-label">
                        <input type="radio" name="app_report_visibility" value="confidential"
                               <?php echo $currentVisibility === 'confidential' ? 'checked' : ''; ?>>
                        <div>
                            <strong>Confidentiel</strong> <span class="text-muted text-small">(le plus restrictif)</span>
                            <div class="text-muted text-small mt-2px">L'agent ne voit que ses propres signalements. Les autres agents ne voient rien, pas même le titre. Les superviseurs et membres du CHSCT voient tout.</div>
                        </div>
                    </label>
                    <label class="visibility-radio-label">
                        <input type="radio" name="app_report_visibility" value="agent_choice"
                               <?php echo $currentVisibility === 'agent_choice' ? 'checked' : ''; ?>>
                        <div>
                            <strong>Choix de l'agent</strong> <span class="text-muted text-small">(confidentiel par défaut)</span>
                            <div class="text-muted text-small mt-2px">L'agent choisit la visibilité de chaque signalement lors de la création (public ou confidentiel). Par défaut, le signalement est confidentiel. L'agent voit les signalements publics de son <?php echo e(getConfig('app_label_unite', 'UR')); ?> ainsi que ses propres signalements.</div>
                        </div>
                    </label>
                    <label class="visibility-radio-label">
                        <input type="radio" name="app_report_visibility" value="public"
                               <?php echo $currentVisibility === 'public' ? 'checked' : ''; ?>>
                        <div>
                            <strong>Visibilité publique</strong>
                            <div class="text-muted text-small mt-2px">Tous les signalements du site sont visibles par tous les agents du site.</div>
                        </div>
                    </label>
                </div>
                <div class="info-panel agent-visibility-warning">
                    &#x2139;&#xFE0F; <strong>Information :</strong> Quel que soit le mode, les superviseurs et les membres du CHSCT voient tous les signalements, y compris les confidentiels.
                </div>
            </fieldset>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn--success">Enregistrer les modifications</button>
        <a href="<?php echo url('settings', ['tab' => 'app']); ?>" class="btn btn--outline">Annuler</a>
    </div>
</form>

<!-- Agent visibility info toggle — CSS only, no JavaScript -->
<?php endif; ?>

<?php if ($activeTab === 'manage_sites'): ?>
<!-- Sites Management -->
<div class="card">
    <h3 class="card__title">&#x1F3E2; Gestion des sites (<?php echo e(getConfig('app_label_unite', 'UR')); ?>)</h3>
    <p class="text-muted text-small mb-4">Gérez les sites disponibles. Les sites désactivés n'apparaissent plus dans les listes de choix (pour les nouveaux agents) mais les signalements existants restent accessibles.</p>

    <!-- Add new site form -->
    <form method="POST" action="<?php echo url('settings'); ?>" class="add-site-form">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
        <input type="hidden" name="tab" value="manage_sites">
        <input type="hidden" name="action" value="add_site">
        <h4 class="card__subtitle">+ Ajouter un site</h4>
        <div class="add-site-grid">
            <div class="form-group mb-0">
                <label for="new_site_code">Code</label>
                <input type="text" id="new_site_code" name="new_site_code" class="form-control"
                       placeholder="UR21" required maxlength="10">
            </div>
            <div class="form-group mb-0">
                <label for="new_site_nom">Nom</label>
                <input type="text" id="new_site_nom" name="new_site_nom" class="form-control"
                       placeholder="UR Côte-d'Or" required>
            </div>
            <div class="form-group mb-0">
                <label for="new_site_departement">Département</label>
                <input type="text" id="new_site_departement" name="new_site_departement" class="form-control"
                       placeholder="Côte-d'Or">
            </div>
            <button type="submit" class="btn btn--success">Ajouter</button>
        </div>
    </form>

    <!-- Existing sites list -->
    <table class="table text-small" aria-label="Paramètres de l'application">
        <thead>
            <tr>
                <th>Code</th>
                <th>Nom</th>
                <th>Département</th>
                <th class="text-center">Agents</th>
                <th class="text-center">Signalements</th>
                <th>Statut</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sites as $site): ?>
            <?php
                $userCount = countUsersBySite($pdo, (int) $site['id']);
                $reportCount = countReportsBySite($pdo, (int) $site['id']);
                $isActive = !isset($site['is_active']) || $site['is_active'] == 1;
            ?>
            <tr class="<?php echo !$isActive ? 'row--inactive' : ''; ?>">
                <td><strong><?php echo e($site['code']); ?></strong></td>
                <td><?php echo e($site['nom']); ?></td>
                <td><?php echo e($site['departement'] ?? '—'); ?></td>
                <td class="text-center"><?php echo $userCount; ?></td>
                <td class="text-center"><?php echo $reportCount; ?></td>
                <td>
                    <?php if ($isActive): ?>
                        <span class="badge badge--traite badge--sm">Actif</span>
                    <?php else: ?>
                        <span class="badge badge--abandonne badge--sm">Inactif</span>
                    <?php endif; ?>
                </td>
                <td class="whitespace-nowrap">
                    <?php if ($isActive): ?>
                    <form method="POST" action="<?php echo url('settings'); ?>" class="form--inline">
                        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                        <input type="hidden" name="tab" value="manage_sites">
                        <input type="hidden" name="action" value="toggle_site">
                        <input type="hidden" name="site_id" value="<?php echo e($site['id']); ?>">
                        <input type="hidden" name="is_active" value="0">
                        <button type="submit" class="btn btn--sm btn--outline">Désactiver</button>
                    </form>
                    <?php else: ?>
                    <form method="POST" action="<?php echo url('settings'); ?>" class="form--inline">
                        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                        <input type="hidden" name="tab" value="manage_sites">
                        <input type="hidden" name="action" value="toggle_site">
                        <input type="hidden" name="site_id" value="<?php echo e($site['id']); ?>">
                        <input type="hidden" name="is_active" value="1">
                        <button type="submit" class="btn btn--sm btn--success">Réactiver</button>
                    </form>
                    <?php endif; ?>

                    <?php if ($userCount === 0 && $reportCount === 0): ?>
                    <?php if (isset($_GET['confirm_delete_site']) && (int) $_GET['confirm_delete_site'] === (int) $site['id']): ?>
                    <!-- Confirmation inline — pas de JavaScript -->
                    <span class="section-header--danger confirm-delete-label">&#x26A0;&#xFE0F; Supprimer <strong><?php echo e($site['code']); ?></strong> ?</span>
                    <form method="POST" action="<?php echo url('settings'); ?>" class="form--inline">
                        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                        <input type="hidden" name="tab" value="manage_sites">
                        <input type="hidden" name="action" value="delete_site">
                        <input type="hidden" name="site_id" value="<?php echo e($site['id']); ?>">
                        <button type="submit" class="btn btn--sm btn--danger">Oui, supprimer</button>
                    </form>
                    <a href="<?php echo url('settings', ['tab' => 'manage_sites']); ?>" class="btn btn--sm btn--secondary">Annuler</a>
                    <?php else: ?>
                    <a href="<?php echo url('site_edit', ['id' => $site['id']]); ?>" class="btn btn--sm btn--outline">Éditer</a>
                    <a href="<?php echo url('settings', ['tab' => 'manage_sites', 'confirm_delete_site' => $site['id']]); ?>" class="btn btn--sm btn--danger">Supprimer</a>
                    <?php endif; ?>
                    <?php else: ?>
                    <a href="<?php echo url('site_edit', ['id' => $site['id']]); ?>" class="btn btn--sm btn--outline">Éditer</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
