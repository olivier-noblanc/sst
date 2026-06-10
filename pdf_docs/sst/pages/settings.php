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
<div style="display:flex;gap:0;margin-bottom:20px;border-bottom:2px solid var(--grey-200);flex-wrap:wrap;">
    <a href="<?php echo url('settings', ['tab' => 'sites']); ?>"
       class="settings-tab <?php echo $activeTab === 'sites' ? 'settings-tab--active' : ''; ?>">
        📍 Notifications par site
    </a>
    <a href="<?php echo url('settings', ['tab' => 'global']); ?>"
       class="settings-tab <?php echo $activeTab === 'global' ? 'settings-tab--active' : ''; ?>">
        🌐 Notifications globales
    </a>
    <a href="<?php echo url('settings', ['tab' => 'smtp']); ?>"
       class="settings-tab <?php echo $activeTab === 'smtp' ? 'settings-tab--active' : ''; ?>">
        📧 Configuration SMTP
    </a>
    <a href="<?php echo url('settings', ['tab' => 'manage_sites']); ?>"
       class="settings-tab <?php echo $activeTab === 'manage_sites' ? 'settings-tab--active' : ''; ?>">
        🏢 Gestion des sites
    </a>
    <a href="<?php echo url('settings', ['tab' => 'app']); ?>"
       class="settings-tab <?php echo $activeTab === 'app' ? 'settings-tab--active' : ''; ?>">
        ⚙️ Paramètres de l'application
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
        <div class="card" style="margin-bottom:16px;">
            <h3 style="margin-bottom:12px;font-size:15px;">
                <span class="badge badge--rsst" style="font-size:11px;margin-right:6px;"><?php echo e($site['code']); ?></span>
                <?php echo e($site['nom']); ?>
            </h3>
            <div class="form-group">
                <label>Adresses e-mail de notification</label>
                <div class="tag-input-container" data-field="site_emails_<?php echo $sId; ?>">
                    <div class="tag-input-tags">
                        <?php foreach ($existingEmails as $email): ?>
                        <span class="tag-input-tag" data-email="<?php echo e($email); ?>">
                            <?php echo e($email); ?>
                            <button type="button" class="tag-input-remove" onclick="this.parentElement.remove()">&times;</button>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <div style="display:flex;gap:6px;">
                        <input type="email" placeholder="Ajouter un e-mail..." class="tag-input-field"
                               onkeydown="if(event.key==='Enter'){event.preventDefault();addTag(this);}">
                        <button type="button" class="btn btn--sm btn--outline" onclick="addTag(this.previousElementSibling)">Ajouter</button>
                    </div>
                </div>
                <!-- Hidden inputs to store the emails -->
                <div class="tag-input-hidden" data-field="site_emails_<?php echo $sId; ?>">
                    <?php foreach ($existingEmails as $i => $email): ?>
                    <input type="hidden" name="site_emails[<?php echo $sId; ?>][]" value="<?php echo e($email); ?>">
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($activeTab === 'global'): ?>
    <!-- Notifications globales -->
    <div class="card">
        <h3 style="margin-bottom:12px;">Adresses e-mail de notification globales</h3>
        <p class="text-muted text-small mb-4">Ces adresses recevront des notifications pour tous les sites et tous les registres.</p>
        <div class="form-group">
            <div class="tag-input-container" data-field="global_emails">
                <div class="tag-input-tags">
                    <?php foreach ($globalEmails as $ge): ?>
                    <span class="tag-input-tag" data-email="<?php echo e($ge['email']); ?>">
                        <?php echo e($ge['email']); ?>
                        <button type="button" class="tag-input-remove" onclick="this.parentElement.remove()">&times;</button>
                    </span>
                    <?php endforeach; ?>
                </div>
                <div style="display:flex;gap:6px;">
                    <input type="email" placeholder="Ajouter un e-mail..." class="tag-input-field"
                           onkeydown="if(event.key==='Enter'){event.preventDefault();addTag(this);}">
                    <button type="button" class="btn btn--sm btn--outline" onclick="addTag(this.previousElementSibling)">Ajouter</button>
                </div>
            </div>
            <div class="tag-input-hidden" data-field="global_emails">
                <?php foreach ($globalEmails as $i => $ge): ?>
                <input type="hidden" name="global_emails[]" value="<?php echo e($ge['email']); ?>">
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="form-actions">
        <button type="submit" class="btn btn--success">Enregistrer les modifications</button>
        <a href="<?php echo url('settings'); ?>" class="btn btn--outline">Annuler</a>
    </div>
</form>

<script>
function addTag(input) {
    var email = input.value.trim();
    if (!email || !email.includes('@')) return;

    var container = input.closest('.tag-input-container');
    var fieldName = container.getAttribute('data-field');
    var tagsDiv = container.querySelector('.tag-input-tags');
    var hiddenDiv = document.querySelector('.tag-input-hidden[data-field="' + fieldName + '"]');

    // Create visible tag (use textContent for email to prevent DOM XSS)
    var tag = document.createElement('span');
    tag.className = 'tag-input-tag';
    tag.setAttribute('data-email', email);
    var emailText = document.createTextNode(email + ' ');
    var removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'tag-input-remove';
    removeBtn.innerHTML = '&times;';
    removeBtn.onclick = function() { this.parentElement.remove(); syncHidden(fieldName); };
    tag.appendChild(emailText);
    tag.appendChild(removeBtn);
    tagsDiv.appendChild(tag);

    // Create hidden input
    var hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = fieldName.startsWith('site_emails_') ? 'site_emails[' + fieldName.replace('site_emails_', '') + '][]' : 'global_emails[]';
    hidden.value = email;
    hiddenDiv.appendChild(hidden);

    input.value = '';
    input.focus();
}

// Override remove to also sync hidden inputs
document.querySelectorAll('.tag-input-remove').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var container = this.closest('.tag-input-container');
        var fieldName = container.getAttribute('data-field');
        setTimeout(function() { syncHidden(fieldName); }, 10);
    });
});

function syncHidden(fieldName) {
    var container = document.querySelector('.tag-input-container[data-field="' + fieldName + '"]');
    var hiddenDiv = document.querySelector('.tag-input-hidden[data-field="' + fieldName + '"]');
    if (!container || !hiddenDiv) return;

    // Clear hidden inputs
    hiddenDiv.innerHTML = '';

    // Recreate from tags
    var tags = container.querySelectorAll('.tag-input-tag');
    tags.forEach(function(tag) {
        var email = tag.getAttribute('data-email');
        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = fieldName.startsWith('site_emails_') ? 'site_emails[' + fieldName.replace('site_emails_', '') + '][]' : 'global_emails[]';
        hidden.value = email;
        hiddenDiv.appendChild(hidden);
    });
}
</script>
<?php endif; ?>

<?php if ($activeTab === 'smtp'): ?>
<!-- SMTP Configuration -->
<form method="POST" action="<?php echo url('settings'); ?>">
    <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
    <input type="hidden" name="tab" value="smtp">

    <div class="card">
        <h3 style="margin-bottom:16px;">📧 Configuration SMTP</h3>
        <p class="text-muted text-small" style="margin-bottom:20px;">Configurez le serveur SMTP pour l'envoi des e-mails de notification.</p>

        <div class="form-row" style="display:grid;grid-template-columns:2fr 1fr;gap:16px;">
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

        <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
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

        <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
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

    <div class="form-actions" style="flex-wrap:wrap;gap:8px;">
        <button type="submit" class="btn btn--success">Enregistrer les modifications</button>
        <div style="display:flex;align-items:center;gap:6px;flex-shrink:0;">
            <input type="email" id="smtp_test_to" placeholder="destinataire@exemple.com"
                   style="height:36px;padding:0 10px;border:1px solid var(--border,#ccc);border-radius:4px;font-size:.9em;min-width:220px;">
            <button type="button" class="btn btn--outline" onclick="testSmtp()">Envoyer un e-mail de test</button>
        </div>
        <a href="<?php echo url('settings', ['tab' => 'smtp']); ?>" class="btn btn--outline">Annuler</a>
    </div>
</form>

<script>
function testSmtp() {
    var csrf  = document.querySelector('input[name="csrf_token"]');
    var btn   = document.querySelector('[onclick="testSmtp()"]');
    var toEl  = document.getElementById('smtp_test_to');
    if (!csrf) { alert('Erreur : jeton CSRF introuvable.'); return; }

    var to = toEl ? toEl.value.trim() : '';
    if (!to) { alert('Saisissez une adresse e-mail destinataire avant de tester.'); if (toEl) toEl.focus(); return; }

    var origText = btn ? btn.textContent : '';
    if (btn) { btn.disabled = true; btn.textContent = 'Envoi en cours\u2026'; }

    var data = new FormData();
    data.append('csrf_token',      csrf.value);
    data.append('smtp_test_to',    to);
    data.append('smtp_host',       document.getElementById('smtp_host').value);
    data.append('smtp_port',       document.getElementById('smtp_port').value);
    data.append('smtp_user',       document.getElementById('smtp_user').value);
    data.append('smtp_pass',       document.getElementById('smtp_pass').value);
    data.append('smtp_from',       document.getElementById('smtp_from').value);
    data.append('smtp_encryption', document.getElementById('smtp_encryption').value);

    <?php $testUrl = url('smtp_test'); ?>
    fetch('<?php echo e($testUrl); ?>', { method: 'POST', body: data })
        .then(function(r) {
            if (!r.ok) { throw new Error('HTTP ' + r.status); }
            return r.json();
        })
        .then(function(json) {
            alert((json.ok ? '\u2705 ' : '\u274c ') + json.message);
        })
        .catch(function(err) {
            alert('\u274c Erreur r\u00e9seau lors du test SMTP : ' + err.message);
        })
        .finally(function() {
            if (btn) { btn.disabled = false; btn.textContent = origText; }
        });
}
</script>
<?php endif; ?>

<?php if ($activeTab === 'app'): ?>
<!-- Application Settings -->
<form method="POST" action="<?php echo url('settings'); ?>">
    <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
    <input type="hidden" name="tab" value="app">

    <div class="card">
        <h3 style="margin-bottom:16px;">⚙️ Paramètres de l'application</h3>
        <p class="text-muted text-small" style="margin-bottom:20px;">Configurez les paramètres généraux de l'application.</p>

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
            <small class="text-muted" style="display:block;margin-top:4px;">
                Exemple : UR, UD, Direction... Ce libellé est utilisé partout dans l'application.
            </small>
        </div>

        <div class="form-group">
            <label for="app_admin_prefix">Préfixe de login administrateur</label>
            <input type="text" id="app_admin_prefix" name="app_admin_prefix" class="form-control"
                   value="<?php echo e(getConfig('app_admin_prefix', 'adm.')); ?>"
                   placeholder="adm." maxlength="20">
            <small class="text-muted" style="display:block;margin-top:4px;">
                Tout utilisateur dont le login Windows commence par ce préfixe sera automatiquement promu <strong>Superviseur</strong>.
                Par exemple, avec le préfixe "<code>adm.</code>", le login "<code>adm.olivier.noblanc</code>" sera Superviseur.
                <strong>Laisser vide pour désactiver</strong> cette règle de promotion automatique.
                La promotion s'applique aussi aux utilisateurs existants à leur prochaine connexion.
            </small>
        </div>

        <div class="form-group">
            <label for="app_admin_usernames">Logins Windows des administrateurs (liste explicite)</label>
            <input type="text" id="app_admin_usernames" name="app_admin_usernames" class="form-control"
                   value="<?php echo e(getConfig('app_admin_usernames', '')); ?>"
                   placeholder="jean.martin, sophie.dupont">
            <small class="text-muted" style="display:block;margin-top:4px;">
                Séparés par des virgules. Ces utilisateurs seront également promus <strong>Superviseur</strong>
                lors de leur connexion via IIS. Complémentaire du préfixe ci-dessus — permet d'ajouter
                des administrateurs dont le login ne suit pas la convention de préfixe.
            </small>
        </div>

        <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--grey-200);">
            <h4 style="margin-bottom:12px;">🔒 Visibilité des agents</h4>
            <p class="text-muted text-small" style="margin-bottom:12px;">Détermine quels signalements les agents peuvent consulter dans les registres.</p>
            <div class="form-group" style="margin-bottom:0;">
                <div style="display:flex;flex-direction:column;gap:10px;">
                    <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;font-weight:normal;">
                        <input type="radio" name="app_agent_visibility" value="all"
                               <?php echo getAgentVisibility() === 'all' ? 'checked' : ''; ?>
                               style="margin-top:3px;width:16px;height:16px;">
                        <div>
                            <strong>Tous les signalements</strong> <span style="color:var(--grey-500);font-size:12px;">(par défaut)</span>
                            <div style="color:var(--grey-600);font-size:12px;margin-top:2px;">L'agent voit tous les signalements de tous les sites. Conforme au principe de transparence des registres SST.</div>
                        </div>
                    </label>
                    <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;font-weight:normal;">
                        <input type="radio" name="app_agent_visibility" value="site"
                               <?php echo getAgentVisibility() === 'site' ? 'checked' : ''; ?>
                               style="margin-top:3px;width:16px;height:16px;">
                        <div>
                            <strong>Uniquement son site</strong>
                            <div style="color:var(--grey-600);font-size:12px;margin-top:2px;">L'agent voit uniquement les signalements de son <?php echo e(getConfig('app_label_unite', 'UR')); ?>.</div>
                        </div>
                    </label>
                    <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;font-weight:normal;">
                        <input type="radio" name="app_agent_visibility" value="own"
                               <?php echo getAgentVisibility() === 'own' ? 'checked' : ''; ?>
                               style="margin-top:3px;width:16px;height:16px;">
                        <div>
                            <strong>Uniquement ses propres signalements</strong>
                            <div style="color:var(--grey-600);font-size:12px;margin-top:2px;">L'agent ne voit que les signalements qu'il a lui-même déposés.</div>
                        </div>
                    </label>
                </div>
                <div id="agentVisibilityWarning" style="display:<?php echo in_array(getAgentVisibility(), ['own', 'site']) ? 'block' : 'none'; ?>;margin-top:14px;padding:10px 14px;background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;color:#991b1b;font-size:12px;">
                    ⚠️ <strong>Avertissement réglementaire :</strong> Par défaut, les registres SST sont consultables par tous les agents (principe de transparence en matière de santé et sécurité au travail). Restreindre l'accès peut ne pas être conforme aux dispositions du Code du travail relatives aux registres SST. Activez ces restrictions uniquement si vous avez vérifié leur conformité avec votre politique interne.
                </div>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn--success">Enregistrer les modifications</button>
        <a href="<?php echo url('settings', ['tab' => 'app']); ?>" class="btn btn--outline">Annuler</a>
    </div>
</form>
<script>
(function() {
    var radios = document.querySelectorAll('input[name="app_agent_visibility"]');
    var warning = document.getElementById('agentVisibilityWarning');
    if (radios.length > 0 && warning) {
        function updateWarning() {
            var selected = document.querySelector('input[name="app_agent_visibility"]:checked');
            if (selected && (selected.value === 'own' || selected.value === 'site')) {
                warning.style.display = 'block';
            } else {
                warning.style.display = 'none';
            }
        }
        radios.forEach(function(radio) {
            radio.addEventListener('change', updateWarning);
        });
    }
})();
</script>
<?php endif; ?>

<?php if ($activeTab === 'manage_sites'): ?>
<!-- Sites Management -->
<div class="card">
    <h3 style="margin-bottom:16px;">🏢 Gestion des sites (<?php echo e(getConfig('app_label_unite', 'UR')); ?>)</h3>
    <p class="text-muted text-small" style="margin-bottom:16px;">Gérez les sites disponibles. Les sites désactivés n'apparaissent plus dans les listes de choix (pour les nouveaux agents) mais les signalements existants restent accessibles.</p>

    <!-- Add new site form -->
    <form method="POST" action="<?php echo url('settings'); ?>" style="margin-bottom:20px;padding:16px;background:var(--grey-50,#f9fafb);border-radius:8px;border:1px dashed var(--grey-300);">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
        <input type="hidden" name="tab" value="manage_sites">
        <input type="hidden" name="action" value="add_site">
        <h4 style="margin-bottom:12px;">+ Ajouter un site</h4>
        <div style="display:grid;grid-template-columns:100px 1fr 1fr auto;gap:10px;align-items:end;">
            <div class="form-group" style="margin-bottom:0;">
                <label for="new_site_code">Code</label>
                <input type="text" id="new_site_code" name="new_site_code" class="form-control"
                       placeholder="UR21" required maxlength="10">
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label for="new_site_nom">Nom</label>
                <input type="text" id="new_site_nom" name="new_site_nom" class="form-control"
                       placeholder="UR Côte-d'Or" required>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label for="new_site_departement">Département</label>
                <input type="text" id="new_site_departement" name="new_site_departement" class="form-control"
                       placeholder="Côte-d'Or">
            </div>
            <button type="submit" class="btn btn--success">Ajouter</button>
        </div>
    </form>

    <!-- Existing sites list -->
    <table class="table" style="font-size:13px;">
        <thead>
            <tr>
                <th>Code</th>
                <th>Nom</th>
                <th>Département</th>
                <th style="text-align:center;">Agents</th>
                <th style="text-align:center;">Signalements</th>
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
            <tr style="<?php echo !$isActive ? 'opacity:0.5;' : ''; ?>">
                <td><strong><?php echo e($site['code']); ?></strong></td>
                <td><?php echo e($site['nom']); ?></td>
                <td><?php echo e($site['departement'] ?? '—'); ?></td>
                <td style="text-align:center;"><?php echo $userCount; ?></td>
                <td style="text-align:center;"><?php echo $reportCount; ?></td>
                <td>
                    <?php if ($isActive): ?>
                        <span class="badge badge--traite" style="font-size:11px;">Actif</span>
                    <?php else: ?>
                        <span class="badge badge--abandonne" style="font-size:11px;">Inactif</span>
                    <?php endif; ?>
                </td>
                <td style="white-space:nowrap;">
                    <?php if ($isActive): ?>
                    <form method="POST" action="<?php echo url('settings'); ?>" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                        <input type="hidden" name="tab" value="manage_sites">
                        <input type="hidden" name="action" value="toggle_site">
                        <input type="hidden" name="site_id" value="<?php echo e($site['id']); ?>">
                        <input type="hidden" name="is_active" value="0">
                        <button type="submit" class="btn btn--sm btn--outline" onclick="return confirm('Désactiver ce site ? Il n\\'apparaîtra plus dans les listes de choix pour les nouveaux agents.')">Désactiver</button>
                    </form>
                    <?php else: ?>
                    <form method="POST" action="<?php echo url('settings'); ?>" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                        <input type="hidden" name="tab" value="manage_sites">
                        <input type="hidden" name="action" value="toggle_site">
                        <input type="hidden" name="site_id" value="<?php echo e($site['id']); ?>">
                        <input type="hidden" name="is_active" value="1">
                        <button type="submit" class="btn btn--sm btn--success">Réactiver</button>
                    </form>
                    <?php endif; ?>

                    <?php if ($userCount === 0 && $reportCount === 0): ?>
                    <form method="POST" action="<?php echo url('settings'); ?>" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                        <input type="hidden" name="tab" value="manage_sites">
                        <input type="hidden" name="action" value="delete_site">
                        <input type="hidden" name="site_id" value="<?php echo e($site['id']); ?>">
                        <button type="submit" class="btn btn--sm btn--danger" onclick="return confirm('⚠️ Supprimer définitivement ce site ? Cette action est irréversible.')">Supprimer</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>