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

// Service instances (created once for the page)
$fmt = new \App\Services\FormattingService();
$http = new \App\Services\HttpService();
$config = \App\Services\ConfigService::getInstance();

$pdo = getContainer()->get(\PDO::class);
$noSiteMode = $config->isNoSiteMode();

// Get sites
$sites = \App\Repository\SiteRepository::instance()->findAll();

// Get current notification settings
$currentSettings = \App\Repository\NotificationRepository::instance()->findAll();

// Organize settings: by site and global
$siteEmails = [];
$globalEmails = [];

foreach ($currentSettings as $setting) {
    if ($setting['type'] === 'global') {
        $globalEmails[] = $setting;
    } else {
        $siteIdStr = $setting['site_id'] ?? '0';
        $sId = (int) $siteIdStr;
        if (!isset($siteEmails[$sId])) {
            $siteEmails[$sId] = [];
        }
        $siteEmails[$sId][] = $setting;
    }
}

// Active tab — whitelist validation to prevent LFI/path traversal
$defaultTab = $noSiteMode ? 'global' : 'sites';
$activeTab = $_GET['tab'] ?? $defaultTab;
$allowedTabs = ['sites', 'global', 'smtp', 'manage_sites', 'app', 'wordcloud', 'registres'];
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = $defaultTab;
}
// Redirect 'sites' tab to 'global' in noSiteMode
if ($noSiteMode && $activeTab === 'sites') {
    $activeTab = 'global';
}

$pageTitle = 'Paramètres';
?>

<h1 class="page-title">Paramètres</h1>


<!-- Tabs -->
<div class="tab-bar">
    <?php if (!$noSiteMode): ?>
    <a href="<?php echo $http->url('settings', ['tab' => 'sites']); ?>"
       class="settings-tab <?php echo $activeTab === 'sites' ? 'settings-tab--active' : ''; ?>">
        &#x1F4CD; Notifications par site
    </a>
    <?php endif; ?>
    <a href="<?php echo $http->url('settings', ['tab' => 'global']); ?>"
       class="settings-tab <?php echo $activeTab === 'global' ? 'settings-tab--active' : ''; ?>">
        &#x1F310; Notifications globales
    </a>
    <a href="<?php echo $http->url('settings', ['tab' => 'smtp']); ?>"
       class="settings-tab <?php echo $activeTab === 'smtp' ? 'settings-tab--active' : ''; ?>">
        &#x1F4E7; Configuration SMTP
    </a>
    <a href="<?php echo $http->url('settings', ['tab' => 'manage_sites']); ?>"
       class="settings-tab <?php echo $activeTab === 'manage_sites' ? 'settings-tab--active' : ''; ?>">
        &#x1F3E2; Gestion des <?php echo $fmt->e($config->get('app_label_unite', 'UR')); ?>s
    </a>
    <a href="<?php echo $http->url('settings', ['tab' => 'app']); ?>"
       class="settings-tab <?php echo $activeTab === 'app' ? 'settings-tab--active' : ''; ?>">
        &#x2699;&#xFE0F; Paramètres de l'application
    </a>
    <a href="<?php echo $http->url('settings', ['tab' => 'wordcloud']); ?>"
       class="settings-tab <?php echo $activeTab === 'wordcloud' ? 'settings-tab--active' : ''; ?>">
        &#x1F4CA; Nuage de mots
    </a>
    <a href="<?php echo $http->url('settings', ['tab' => 'registres']); ?>"
       class="settings-tab <?php echo $activeTab === 'registres' ? 'settings-tab--active' : ''; ?>">
        &#x1F4CB; Registres
    </a>

</div>

<?php
// Delegate to sub-template based on active tab
$tabFile = __DIR__ . '/settings/tab_' . $activeTab . '.php';
if (file_exists($tabFile)) {
    require $tabFile;
}
