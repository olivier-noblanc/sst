<?php
/**
 * User View Page — Application SST DREETS BFC
 * 
 * View user profile (read-only).
 * Access: superviseur only
 */
requireRole(['superviseur']);

$pdo = getDB();
$userId = (int) ($_GET['id'] ?? 0);

if ($userId <= 0) {
    setFlash('error', 'Utilisateur introuvable.');
    redirect(url('users'));
}

$user = getUserById($pdo, $userId);

if (!$user) {
    setFlash('error', 'Utilisateur introuvable.');
    redirect(url('users'));
}

// Get user's report count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE declarant_id = :uid");
$stmt->execute([':uid' => $userId]);
$reportCount = (int) $stmt->fetchColumn();

$pageTitle = 'Utilisateur — ' . e($user['prenom'] . ' ' . $user['nom']);
?>

<h1 class="page-title">Profil utilisateur</h1>

<?php require __DIR__ . '/../templates/alert.php'; ?>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;">
        <div>
            <h2 style="margin-bottom:4px;"><?php echo e($user['prenom'] . ' ' . $user['nom']); ?></h2>
            <div style="display:flex;gap:8px;align-items:center;">
                <span class="badge <?php echo getRoleBadgeClass($user['role']); ?>"><?php echo e(ROLE_LABELS[$user['role']] ?? $user['role']); ?></span>
                <?php if ($user['is_active']): ?>
                    <span style="color:var(--state-traite);font-size:12px;">● Actif</span>
                <?php else: ?>
                    <span style="color:var(--state-abandonne);font-size:12px;">● Inactif</span>
                <?php endif; ?>
            </div>
        </div>
        <a href="<?php echo url('user_edit', ['id' => (int) $user['id']]); ?>" class="btn btn--primary">Éditer</a>
    </div>

    <table class="report-detail__table">
        <tr>
            <th>Nom</th>
            <td><?php echo e($user['nom']); ?></td>
        </tr>
        <tr>
            <th>Prénom</th>
            <td><?php echo e($user['prenom']); ?></td>
        </tr>
        <tr>
            <th>Email</th>
            <td><?php echo e($user['email'] ?? '—'); ?></td>
        </tr>
        <tr>
            <th>Identifiant</th>
            <td><code style="background:var(--grey-100);padding:2px 6px;border-radius:3px;"><?php echo e($user['username']); ?></code></td>
        </tr>
        <tr>
            <th>Rôle</th>
            <td><span class="badge <?php echo getRoleBadgeClass($user['role']); ?>"><?php echo e(ROLE_LABELS[$user['role']] ?? $user['role']); ?></span></td>
        </tr>
        <tr>
            <th>Site</th>
            <td><?php echo e($user['site_nom'] ?? '—'); ?></td>
        </tr>
        <tr>
            <th>Signalements créés</th>
            <td><?php echo $reportCount; ?></td>
        </tr>
        <tr>
            <th>Date de création</th>
            <td><?php echo e(formatDateTimeFR($user['created_at'])); ?></td>
        </tr>
        <tr>
            <th>Dernière modification</th>
            <td><?php echo e(formatDateTimeFR($user['updated_at'])); ?></td>
        </tr>
    </table>
</div>

<div class="form-actions">
    <a href="<?php echo url('user_edit', ['id' => (int) $user['id']]); ?>" class="btn btn--primary">Éditer</a>
    <a href="<?php echo url('users'); ?>" class="btn btn--secondary">Retour à la liste</a>
</div>

<?php if ($user['is_active'] || $user['nom'] !== 'Anonymisé'): ?>
<div class="card" style="margin-top:20px;">
    <h3 style="margin-bottom:12px;color:var(--grey-600);">RGPD — Données personnelles</h3>
    <p style="color:var(--grey-500);font-size:13px;margin-bottom:16px;">
        Conformément au RGPD, l'utilisateur peut exercer son droit d'accès (export) ou d'effacement (anonymisation).
        L'anonymisation remplace les données personnelles par des placeholders et désactive le compte. Les signalements sont conservés.
    </p>
    <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <form method="POST" action="<?php echo url('user_edit', ['id' => (int) $user['id']]); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
            <input type="hidden" name="action" value="export_data">
            <button type="submit" class="btn btn--outline" style="font-size:13px;">📥 Exporter les données (droit d'accès)</button>
        </form>
        <?php if ($user['nom'] !== 'Anonymisé'): ?>
        <?php if (isset($_GET['confirm_anonymize'])): ?>
        <span style="font-weight:600;color:var(--dgi-color);">⚠️ Anonymiser définitivement ?</span>
        <form method="POST" action="<?php echo url('user_edit', ['id' => (int) $user['id']]); ?>" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
            <input type="hidden" name="action" value="anonymize">
            <button type="submit" class="btn btn--danger" style="font-size:13px;">Oui, anonymiser</button>
        </form>
        <a href="<?php echo url('user_view', ['id' => (int) $user['id']]); ?>" class="btn btn--secondary" style="font-size:13px;">Annuler</a>
        <?php else: ?>
        <a href="<?php echo url('user_view', ['id' => (int) $user['id'], 'confirm_anonymize' => 1]); ?>" class="btn btn--danger" style="font-size:13px;">🔒 Anonymiser (droit d'effacement)</a>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
