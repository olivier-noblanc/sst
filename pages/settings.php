<?php
/**
 * Settings Page — Application SST DREETS BFC
 * 
 * Notification email configuration per site and globally.
 * Plus SMTP and Application configuration tabs.
 * Access: superviseur only
 *
 * Tab content is delegated to sub-templates under settings/.
 */
requireRole([ROLE_SUPERVISEUR]);

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

// Active tab — whitelist validation to prevent LFI/path traversal
$activeTab = $_GET['tab'] ?? 'sites';
$allowedTabs = ['sites', 'global', 'smtp', 'manage_sites', 'app'];
if (!in_array($activeTab, $allowedTabs)) {
    $activeTab = 'sites'; // default tab
}

$pageTitle = 'Paramètres';
?>

<h1 class="page-title">Paramètres</h1>


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

<?php
// Delegate to sub-template based on active tab
$tabFile = __DIR__ . '/settings/tab_' . $activeTab . '.php';
if (file_exists($tabFile)) {
    require $tabFile;
}
