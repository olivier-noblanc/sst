<?php
/**
 * Settings Tab: Gestion des sites
 *
 * Variables attendues: $sites, $pdo, $csrfToken
 */
/** @var PDO $pdo */
/** @var list<array{id: int, code: string, nom: string, departement: string, is_active: int}> $sites */
/** @var string $csrfToken */
?>
<div class="card">
    <h3 class="card__title">&#x1F3E2; Gestion des sites (<?php echo new \App\Services\FormattingService()->e(\App\Services\ConfigService::getInstance()->get('app_label_unite', 'UR')); ?>)</h3>
    <p class="text-muted text-small mb-4">Gérez les sites disponibles. Les sites désactivés n'apparaissent plus dans les listes de choix (pour les nouveaux agents) mais les signalements existants restent accessibles.</p>

    <!-- Add new site form -->
    <form method="POST" action="<?php echo new \App\Services\HttpService()->url('settings'); ?>" class="add-site-form">
        <input type="hidden" name="csrf_token" value="<?php echo new \App\Services\FormattingService()->e($csrfToken); ?>">
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
                $isActive = !isset($site['is_active']) || $site['is_active'] === 1 || $site['is_active'] === '1';
                ?>
            <tr class="<?php echo !$isActive ? 'row--inactive' : ''; ?>">
                <td><strong><?php echo new \App\Services\FormattingService()->e($site['code']); ?></strong></td>
                <td><?php echo new \App\Services\FormattingService()->e($site['nom']); ?></td>
                <td><?php echo new \App\Services\FormattingService()->e($site['departement'] ?? '—'); ?></td>
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
                    <form method="POST" action="<?php echo new \App\Services\HttpService()->url('settings'); ?>" class="form--inline">
                        <input type="hidden" name="csrf_token" value="<?php echo new \App\Services\FormattingService()->e($csrfToken); ?>">
                        <input type="hidden" name="tab" value="manage_sites">
                        <input type="hidden" name="action" value="toggle_site">
                        <input type="hidden" name="site_id" value="<?php echo new \App\Services\FormattingService()->e($site['id']); ?>">
                        <input type="hidden" name="is_active" value="0">
                        <button type="submit" class="btn btn--sm btn--outline">Désactiver</button>
                    </form>
                    <?php else: ?>
                    <form method="POST" action="<?php echo new \App\Services\HttpService()->url('settings'); ?>" class="form--inline">
                        <input type="hidden" name="csrf_token" value="<?php echo new \App\Services\FormattingService()->e($csrfToken); ?>">
                        <input type="hidden" name="tab" value="manage_sites">
                        <input type="hidden" name="action" value="toggle_site">
                        <input type="hidden" name="site_id" value="<?php echo new \App\Services\FormattingService()->e($site['id']); ?>">
                        <input type="hidden" name="is_active" value="1">
                        <button type="submit" class="btn btn--sm btn--success">Réactiver</button>
                    </form>
                    <?php endif; ?>

                    <?php if ($userCount === 0 && $reportCount === 0): ?>
                    <?php if (isset($_GET['confirm_delete_site']) && (int) $_GET['confirm_delete_site'] === (int) $site['id']): ?>
                    <!-- Confirmation inline — pas de JavaScript -->
                    <span class="section-header--danger confirm-delete-label">&#x26A0;&#xFE0F; Supprimer <strong><?php echo new \App\Services\FormattingService()->e($site['code']); ?></strong> ?</span>
                    <form method="POST" action="<?php echo new \App\Services\HttpService()->url('settings'); ?>" class="form--inline">
                        <input type="hidden" name="csrf_token" value="<?php echo new \App\Services\FormattingService()->e($csrfToken); ?>">
                        <input type="hidden" name="tab" value="manage_sites">
                        <input type="hidden" name="action" value="delete_site">
                        <input type="hidden" name="site_id" value="<?php echo new \App\Services\FormattingService()->e($site['id']); ?>">
                        <button type="submit" class="btn btn--sm btn--danger">Oui, supprimer</button>
                    </form>
                    <a href="<?php echo new \App\Services\HttpService()->url('settings', ['tab' => 'manage_sites']); ?>" class="btn btn--sm btn--secondary">Annuler</a>
                    <?php else: ?>
                    <a href="<?php echo new \App\Services\HttpService()->url('site_edit', ['id' => $site['id']]); ?>" class="btn btn--sm btn--outline">Éditer</a>
                    <a href="<?php echo new \App\Services\HttpService()->url('settings', ['tab' => 'manage_sites', 'confirm_delete_site' => $site['id']]); ?>" class="btn btn--sm btn--danger">Supprimer</a>
                    <?php endif; ?>
                    <?php else: ?>
                    <a href="<?php echo new \App\Services\HttpService()->url('site_edit', ['id' => $site['id']]); ?>" class="btn btn--sm btn--outline">Éditer</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
