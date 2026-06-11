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
    <a href="<?php echo url('users'); ?>" class="btn btn--secondary">Retour à la liste</a>
</div>
