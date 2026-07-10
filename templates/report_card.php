<?php
/**
 * Report Card Template — Application SST DREETS BFC
 *
 * Shared display for a single report view.
 *
 * Required variables:
 *   $report    — Report data array with joined site and respondent info
 *   $responses — Array of response history entries
 */

$noSiteMode = \App\Services\ConfigService::getInstance()->isNoSiteMode();
if (!isset($report) || !$report) {
    return;
}

$type = $report['type'] ?? TYPE_RSST;
$cardClass = match($type) {
    'rsst' => 'card--rsst',
    'rami' => 'card--rami',
    'dgi'  => 'card--dgi',
    default => 'card--rsst',
};

$registryLabel = REGISTRY_SHORT_LABELS[$type] ?? strtoupper($type);
$user = (new \App\Services\SessionService())->getUserSession() ?? [];
$userRole = $user['role'] ?? ROLE_AGENT;
$userSiteId = (int) ($user['site_id'] ?? 0);
$userId = (int) ($user['id'] ?? 0);
$isDeclarant = ((int) $report['declarant_id'] === $userId);
$canEdit = (new \App\Services\AccessService())->canEditReport($report, $userId);
$canAbandon = $isDeclarant && !in_array($report['etat'], [ETAT_ABANDONNE, ETAT_TRAITE]);
$canRespondToReport = (new \App\Services\AccessService())->canRespondToReport($report, $userRole);
$canReopen = in_array($report['etat'], [ETAT_TRAITE, ETAT_ABANDONNE]) && in_array($userRole, [ROLE_SUPERVISEUR, ROLE_CHSCT]);

// Ensure $csrfToken is available (set by index.php but may not be in scope)
if (!isset($csrfToken)) {
    $csrfToken = (new \App\Services\SessionService())->generateCsrfToken();
}
?>

<div class="card <?php echo $cardClass; ?>">
    <?php if ($type === TYPE_DGI): ?>
    <div class="danger-panel">
        &#9888;&#65039; <strong>Procédure prioritaire :</strong> Ce signalement relève du registre DGI (Danger Grave et Imminent). Conformément aux articles L4131-1 et L4132-5 du Code du travail, l'agent a le droit de se retirer de la situation de danger. Le registre DGI doit être tenu à disposition de l'inspecteur du travail et du CHSCT/CSA.
    </div>
    <?php endif; ?>
    <div class="report-detail">
        <div class="report-detail__header">
            <h2>Signalement — <?php echo (new \App\Services\FormattingService())->e($report['reference']); ?></h2>
            <div class="btn-group">
                <span class="badge <?php echo (new \App\Services\FormattingService())->getRegistryBadgeClass($type); ?>"><?php echo (new \App\Services\FormattingService())->e($registryLabel); ?></span>
                <span class="badge <?php echo (new \App\Services\FormattingService())->getEtatBadgeClass($report['etat']); ?>"><?php echo (new \App\Services\FormattingService())->e(ETAT_LABELS[$report['etat']] ?? $report['etat']); ?></span>
                <?php if (!empty($report['is_confidential'])): ?>
                <span class="badge badge--confidential">&#128274; Confidentiel</span>
                <small class="help-text">(Seuls les superviseurs peuvent voir ce signalement)</small>
                <?php endif; ?>
            </div>
        </div>

        <table class="report-detail__table" aria-label="Détails du signalement">
            <tbody>
                <tr>
                    <th>Référence</th>
                    <td><?php echo (new \App\Services\FormattingService())->e($report['reference']); ?></td>
                </tr>
                <tr>
                    <th>Date de l'événement</th>
                    <td><?php echo (new \App\Services\FormattingService())->e((new \App\Services\FormattingService())->formatDateFR($report['date_evenement'])); ?></td>
                </tr>
                <tr>
                    <th>Heure du dépôt</th>
                    <td><?php echo (new \App\Services\FormattingService())->e($report['heure_evenement'] ?? '—'); ?></td>
                </tr>
                <tr>
                    <th><?php echo $type === TYPE_DGI ? 'Lieu / Mesures de protection' : 'Lieu'; ?></th>
                    <td><?php echo (new \App\Services\FormattingService())->e($report['lieu'] ?? '—'); ?></td>
                </tr>
                <?php if (!empty($report['pole'])): ?>
                <tr>
                    <th>Pôle</th>
                    <td><?php echo (new \App\Services\FormattingService())->e($report['pole']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($report['service_affectation'])): ?>
                <tr>
                    <th>Service d'affectation</th>
                    <td><?php echo (new \App\Services\FormattingService())->e($report['service_affectation']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($report['telephone_mobile'])): ?>
                <tr>
                    <th>Téléphone mobile</th>
                    <td><?php echo (new \App\Services\FormattingService())->e($report['telephone_mobile']); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th>Objet</th>
                    <td><?php echo (new \App\Services\FormattingService())->e($report['objet']); ?></td>
                </tr>
                <tr>
                    <th>Description</th>
                    <td><?php echo nl2br((new \App\Services\FormattingService())->e($report['description'])); ?></td>
                </tr>
                <tr>
                    <th>Signalé par</th>
                    <td><?php echo (new \App\Services\FormattingService())->e($report['declarant_prenom'] . ' ' . $report['declarant_nom']); ?></td>
                </tr>
                <?php if (!$noSiteMode): ?>
                <tr>
                    <th><?php echo (new \App\Services\FormattingService())->e(\App\Services\ConfigService::getInstance()->get('app_label_unite', 'UR')); ?></th>
                    <td><?php echo (new \App\Services\FormattingService())->e($report['site_nom'] ?? '—'); ?> (<?php echo (new \App\Services\FormattingService())->e($report['site_code'] ?? '—'); ?>)</td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($report['site_text'])): ?>
                <tr>
                    <th>Site</th>
                    <td><?php echo (new \App\Services\FormattingService())->e($report['site_text']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($type === TYPE_RAMI && !empty($report['pour_compte_nom'])): ?>
                <tr>
                    <th>Signalé au nom de</th>
                    <td><?php echo (new \App\Services\FormattingService())->e(($report['pour_compte_prenom'] ?? '') . ' ' . $report['pour_compte_nom']); ?></td>
                </tr>
                <?php endif; ?>
                <?php
                $linkedAgents = \App\Repository\ReportRepository::instance()->getLinkedAgents($report['uuid']);
                $pendingInvites = \App\Repository\ReportRepository::instance()->getPendingInvites($report['uuid']);
                if (!empty($linkedAgents) || !empty($pendingInvites)):
                ?>
                <tr>
                    <th>Agents rattachés</th>
                    <td>
                        <?php if (!empty($linkedAgents)): ?>
                            <?php echo (new \App\Services\FormattingService())->e(implode(', ', array_map(fn($a) => $a['prenom'] . ' ' . $a['nom'], $linkedAgents))); ?>
                        <?php endif; ?>
                        <?php if (!empty($pendingInvites)): ?>
                            <?php
                            $pendingEmails = array_map(fn($i) => $i['email'] . ' (en attente)', $pendingInvites);
                            $existingText = !empty($linkedAgents) ? ', ' : '';
                            echo $existingText . '<span class="text-muted">' . (new \App\Services\FormattingService())->e(implode(', ', $pendingEmails)) . '</span>';
                            ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
                <?php if (isset($report['consent_syndicat'])): ?>
                <tr>
                    <th>Transmission aux <?php echo (new \App\Services\FormattingService())->e(\App\Services\ConfigService::getInstance()->getRoleLabel('chsct')); ?>s</th>
                    <td><?php echo $report['consent_syndicat'] ? '✅ Acceptée' : '❌ Refusée'; ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th>Date de création</th>
                    <td><?php echo (new \App\Services\FormattingService())->e((new \App\Services\FormattingService())->formatDateTimeFR($report['created_at'])); ?></td>
                </tr>
                <?php if (!empty($report['attachment_name'])): ?>
                <tr>
                    <th>Pièce jointe</th>
                    <td>
                        <?php
                        $isImageAttachment = !empty($report['attachment_mime']) && in_array($report['attachment_mime'], ['image/jpeg', 'image/png', 'image/gif']);
                        ?>
                        <?php if ($isImageAttachment): ?>
                            <div class="mb-2">
                                <a href="<?php echo (new \App\Services\HttpService())->url('report_attachment', ['uuid' => $report['uuid']]); ?>"
                                   title="<?php echo (new \App\Services\FormattingService())->e($report['attachment_name']); ?> — Télécharger">
                                    <img src="<?php echo (new \App\Services\HttpService())->url('report_attachment', ['uuid' => $report['uuid'], 'inline' => 1]); ?>"
                                         alt="<?php echo (new \App\Services\FormattingService())->e($report['attachment_name']); ?>"
                                         class="attachment-image" loading="lazy">
                                </a>
                            </div>
                            <a href="<?php echo (new \App\Services\HttpService())->url('report_attachment', ['uuid' => $report['uuid']]); ?>"
                               class="btn btn--outline btn--sm">&#11015; <?php echo (new \App\Services\FormattingService())->e($report['attachment_name']); ?></a>
                        <?php else: ?>
                            <a href="<?php echo (new \App\Services\HttpService())->url('report_attachment', ['uuid' => $report['uuid']]); ?>"
                               class="btn btn--outline btn--sm">&#128206; <?php echo (new \App\Services\FormattingService())->e($report['attachment_name']); ?></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (!empty($responses)): ?>
<div class="card">
    <h3>Réponses (<?php echo count($responses); ?>)</h3>
    <div class="table-wrapper">
        <table aria-label="Historique des réponses">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Répondant</th>
                    <th>Nouveau statut</th>
                    <th>Réponse</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($responses as $resp): ?>
                <tr>
                    <td><?php echo (new \App\Services\FormattingService())->e((new \App\Services\FormattingService())->formatDateTimeFR($resp['created_at'])); ?></td>
                    <td><?php echo (new \App\Services\FormattingService())->e(($resp['prenom'] ?? '') . ' ' . ($resp['nom'] ?? '')); ?></td>
                    <td>
                        <?php if (!empty($resp['nouvel_etat'])): ?>
                            <span class="badge <?php echo (new \App\Services\FormattingService())->getEtatBadgeClass($resp['nouvel_etat']); ?>">
                                <?php echo (new \App\Services\FormattingService())->e(ETAT_LABELS[$resp['nouvel_etat']] ?? $resp['nouvel_etat']); ?>
                            </span>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?php echo nl2br((new \App\Services\FormattingService())->e($resp['reponse'])); ?></td>
                </tr>
                <?php if (!empty($resp['attachment_name'])): ?>
                <tr>
                    <td colspan="4" style="padding-top:0;">
                        <?php
                        $isImage = !empty($resp['attachment_mime']) && in_array($resp['attachment_mime'], ['image/jpeg', 'image/png', 'image/gif']);
                        ?>
                        <?php if ($isImage): ?>
                            <a href="<?php echo (new \App\Services\HttpService())->url('response_attachment', ['id' => $resp['id']]); ?>" title="<?php echo (new \App\Services\FormattingService())->e($resp['attachment_name']); ?>">
                                <img src="<?php echo (new \App\Services\HttpService())->url('response_attachment', ['id' => $resp['id'], 'inline' => 1]); ?>"
                                     alt="<?php echo (new \App\Services\FormattingService())->e($resp['attachment_name']); ?>"
                                     class="attachment-image" loading="lazy" style="max-height:120px;">
                            </a>
                        <?php endif; ?>
                        <a href="<?php echo (new \App\Services\HttpService())->url('response_attachment', ['id' => $resp['id']]); ?>"
                           class="btn btn--outline btn--sm"><?php echo $isImage ? '&#11015;' : '&#128206;'; ?> <?php echo (new \App\Services\FormattingService())->e($resp['attachment_name']); ?></a>
                    </td>
                </tr>
                <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="form-actions">
    <?php if ($canEdit): ?>
        <a href="<?php echo (new \App\Services\HttpService())->url('report_edit', ['uuid' => $report['uuid']]); ?>" class="btn btn--secondary">Modifier</a>
    <?php endif; ?>

    <?php if ($canRespondToReport): ?>
        <a href="<?php echo (new \App\Services\HttpService())->url('report_respond', ['uuid' => $report['uuid']]); ?>" class="btn btn--primary">Répondre</a>
    <?php endif; ?>

    <?php if ($canAbandon): ?>
        <a href="<?php echo (new \App\Services\HttpService())->url('report_abandon', ['uuid' => $report['uuid']]); ?>" class="btn btn--danger">Abandonner le signalement</a>
        <small class="help-text help-text--danger">(Le signalement sera marqué comme abandonné mais restera consultable)</small>
    <?php endif; ?>

    <?php if ($canReopen): ?>
        <a href="<?php echo (new \App\Services\HttpService())->url('report_reopen', ['uuid' => $report['uuid']]); ?>" class="btn btn--warning">Réouvrir ce signalement</a>
    <?php endif; ?>

    <a href="<?php echo (new \App\Services\HttpService())->url('report_print', ['uuid' => $report['uuid']]); ?>" class="btn btn--outline" target="_blank" rel="noopener noreferrer">Imprimer ou enregistrer en PDF <span class="sr-only">(nouvelle fenêtre)</span></a>
    <a href="<?php echo (new \App\Services\HttpService())->url('report_list', ['type' => $type]); ?>" class="btn btn--secondary">Retour à la liste</a>
</div>
