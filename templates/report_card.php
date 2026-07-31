<?php
use App\Enum\ReportType;

/**
 * Report Card Template — Application SST DREETS BFC
 *
 * Shared display for a single report view.
 *
 * Required variables:
 *   $report         — \App\DTO\ReportData
 *   $responses      — Array of response history entries
 *   $linkedAgents   — Array of linked agent rows (from ReportRepository::getLinkedAgents)
 *   $pendingInvites — Array of pending invite rows (from ReportRepository::getPendingInvites)
 */

$noSiteMode = getConfigService()->isNoSiteMode();
if (!isset($report) || !$report) {
    return;
}
/** @var \App\DTO\ReportData $report */

$fmt = new \App\Services\FormattingService();

$type = $report->type;
$registryForTheme = \App\Repository\RegistryRepository::instance()->findByCode($type);
$colorTheme = (string) ($registryForTheme['color_theme'] ?? $type);
$cardClass = 'card--' . $colorTheme;

$registryLabel = getRegistryShortLabel($type);
$sessionUser = new \App\Services\SessionService()->getUserSession();
$userRole = $sessionUser ? $sessionUser->role : '';
$userSiteId = $sessionUser ? $sessionUser->siteId ?? 0 : 0;
$userId = $sessionUser ? $sessionUser->id : 0;
$isDeclarant = ((int) $report->declarantId === $userId);
$canEdit = new \App\Services\AccessService()->canEditReport($report, $userId);
$canAbandon = $isDeclarant && !in_array($report->etat, [\App\Enum\ReportState::Abandonne->value, \App\Enum\ReportState::Traite->value], true);
$canRespondToReport = new \App\Services\AccessService()->canRespondToReport($report, $userRole);
$canReopen = in_array($report->etat, [\App\Enum\ReportState::Traite->value, \App\Enum\ReportState::Abandonne->value], true) && in_array($userRole, [\App\Enum\UserRole::Superviseur->value, \App\Enum\UserRole::Chsct->value], true);

// Ensure $csrfToken is available (set by index.php but may not be in scope)
if (!isset($csrfToken)) {
    $csrfToken = new \App\Services\SessionService()->generateCsrfToken();
}

/** @var list<array{prenom: string, nom: string}> $linkedAgents */
/** @var list<array{email: string}> $pendingInvites */
/** @var list<array{id: int|string, created_at: string, prenom: string|null, nom: string|null, nouvel_etat: string|null, reponse: string, attachment_name: string|null, attachment_mime: string|null}> $responses */
?>

<div class="card <?php echo e($cardClass); ?>">
    <?php if (new \App\Services\RegistryPolicy()->hasDgiWarningPanel($type)): ?>
    <div class="danger-panel">
        &#9888;&#65039; <strong>Procédure prioritaire :</strong> Ce signalement relève du registre DGI (Danger Grave et Imminent). Conformément aux articles L4131-1 et L4132-5 du Code du travail, l'agent a le droit de se retirer de la situation de danger. Le registre DGI doit être tenu à disposition de l'inspecteur du travail et du CHSCT/CSA.
    </div>
    <?php endif; ?>
    <div class="report-detail">
        <div class="report-detail__header">
            <h2>Signalement — <?php echo $fmt->e($report->reference); ?></h2>
            <div class="btn-group">
                <span class="badge <?php echo e($fmt->getRegistryBadgeClass($type)); ?>"><?php echo $fmt->e($registryLabel); ?></span>
                <span class="badge <?php echo e($fmt->getEtatBadgeClass($report->etat)); ?>"><?php echo $fmt->e(ETAT_LABELS[$report->etat] ?? $report->etat); ?></span>
                <?php if (!empty($report->isConfidential)): ?>
                <span class="badge badge--confidential">&#128274; Confidentiel</span>
                <small class="help-text">(Seuls les superviseurs peuvent voir ce signalement)</small>
                <?php endif; ?>
            </div>
        </div>

        <table class="report-detail__table" aria-label="Détails du signalement">
            <tbody>
                <tr>
                    <th>Référence</th>
                    <td><?php echo $fmt->e($report->reference); ?></td>
                </tr>
                <tr>
                    <th>Date de l'événement</th>
                    <td><?php echo $fmt->e($fmt->formatDateFR($report->dateEvenement)); ?></td>
                </tr>
                <tr>
                    <th>Heure du dépôt</th>
                    <td><?php echo $fmt->e($report->heureEvenement ?: '—'); ?></td>
                </tr>
                <tr>
                    <th><?php echo e(new \App\Services\RegistryPolicy()->getLieuLabel($type)); ?></th>
                    <td><?php echo $fmt->e($report->lieu ?: '—'); ?></td>
                </tr>
                <?php if (!empty($report->pole)): ?>
                <tr>
                    <th>Pôle</th>
                    <td><?php echo $fmt->e($report->pole); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($report->serviceAffectation)): ?>
                <tr>
                    <th>Service d'affectation</th>
                    <td><?php echo $fmt->e($report->serviceAffectation); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($report->telephoneMobile)): ?>
                <tr>
                    <th>Téléphone mobile</th>
                    <td><?php echo $fmt->e($report->telephoneMobile); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th>Objet</th>
                    <td><?php echo $fmt->e($report->objet); ?></td>
                </tr>
                <tr>
                    <th>Description</th>
                    <td><?php echo nl2br($fmt->e($report->description)); ?></td>
                </tr>
                <tr>
                    <th>Signalé par</th>
                    <td><?php echo $fmt->e($report->declarantPrenom . ' ' . $report->declarantNom); ?></td>
                </tr>
                <?php if (!$noSiteMode): ?>
                <tr>
                    <th><?php echo $fmt->e(getConfigService()->get('app_label_unite', 'UR')); ?></th>
                    <td><?php echo $fmt->e($report->siteNom ?: '—'); ?> (<?php echo $fmt->e($report->siteCode ?: '—'); ?>)</td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($report->siteText)): ?>
                <tr>
                    <th>Site</th>
                    <td><?php echo $fmt->e($report->siteText); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($type === \App\Enum\ReportType::Rami->value && !empty($report->pourCompteNom)): ?>
                <tr>
                    <th>Signalé au nom de</th>
                    <td><?php echo $fmt->e($report->pourComptePrenom . ' ' . $report->pourCompteNom); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($linkedAgents) || !empty($pendingInvites)): ?>
                <tr>
                    <th>Agents rattachés</th>
                    <td>
                        <?php if (!empty($linkedAgents)): ?>
                            <?php echo $fmt->e(implode(', ', array_map(fn($a) => $a['prenom'] . ' ' . $a['nom'], $linkedAgents))); ?>
                        <?php endif; ?>
                        <?php if (!empty($pendingInvites)): ?>
                            <?php
                            $pendingEmails = array_map(fn($i) => $i['email'] . ' (en attente)', $pendingInvites);
                            $existingText = !empty($linkedAgents) ? ', ' : '';
                            echo $existingText . '<span class="text-muted">' . $fmt->e(implode(', ', $pendingEmails)) . '</span>';
                            ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th>Transmission aux <?php echo $fmt->e(getConfigService()->getRoleLabel('chsct')); ?>s</th>
                    <td><?php echo (bool) $report->consentSyndicat ? '✅ Acceptée' : '❌ Refusée'; ?></td>
                </tr>
                <tr>
                    <th>Date de création</th>
                    <td><?php echo $fmt->e($fmt->formatDateTimeFR($report->createdAt)); ?></td>
                </tr>
                <?php if (!empty($report->attachmentName)): ?>
                <tr>
                    <th>Pièce jointe</th>
                    <td>
                        <?php
                        $isImageAttachment = !empty($report->attachmentMime) && in_array($report->attachmentMime, ['image/jpeg', 'image/png', 'image/gif'], true);
                        ?>
                        <?php if ($isImageAttachment): ?>
                            <div class="mb-2">
                                <a href="<?php echo new \App\Services\HttpService()->url('report_attachment', ['uuid' => $report->uuid]); ?>"
                                   title="<?php echo $fmt->e($report->attachmentName); ?> — Télécharger">
                                    <img src="<?php echo new \App\Services\HttpService()->url('report_attachment', ['uuid' => $report->uuid, 'inline' => 1]); ?>"
                                         alt="<?php echo $fmt->e($report->attachmentName); ?>"
                                         class="attachment-image" loading="lazy">
                                </a>
                            </div>
                            <a href="<?php echo new \App\Services\HttpService()->url('report_attachment', ['uuid' => $report->uuid]); ?>"
                               class="btn btn--outline btn--sm">&#11015; <?php echo $fmt->e($report->attachmentName); ?></a>
                        <?php else: ?>
                            <a href="<?php echo new \App\Services\HttpService()->url('report_attachment', ['uuid' => $report->uuid]); ?>"
                               class="btn btn--outline btn--sm">&#128206; <?php echo $fmt->e($report->attachmentName); ?></a>
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
                    <td><?php echo $fmt->e($fmt->formatDateTimeFR($resp['created_at'])); ?></td>
                    <td><?php echo $fmt->e((string) ($resp['prenom'] ?? '') . ' ' . (string) ($resp['nom'] ?? '')); ?></td>
                    <td>
                        <?php if (!empty($resp['nouvel_etat'])): ?>
                            <span class="badge <?php echo $fmt->getEtatBadgeClass($resp['nouvel_etat']); ?>">
                                <?php echo $fmt->e(ETAT_LABELS[$resp['nouvel_etat']] ?? $resp['nouvel_etat']); ?>
                            </span>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?php echo nl2br($fmt->e($resp['reponse'])); ?></td>
                </tr>
                <?php if (!empty($resp['attachment_name'])): ?>
                <tr>
                    <td colspan="4" class="td--flush-top">
                        <?php
                        $isImage = !empty($resp['attachment_mime']) && in_array($resp['attachment_mime'], ['image/jpeg', 'image/png', 'image/gif'], true);
                        ?>
                        <?php if ($isImage): ?>
                            <a href="<?php echo new \App\Services\HttpService()->url('response_attachment', ['id' => $resp['id']]); ?>" title="<?php echo $fmt->e($resp['attachment_name']); ?>">
                                <img src="<?php echo new \App\Services\HttpService()->url('response_attachment', ['id' => $resp['id'], 'inline' => 1]); ?>"
                                     alt="<?php echo $fmt->e($resp['attachment_name']); ?>"
                                     class="attachment-image attachment-image--thumb" loading="lazy">
                            </a>
                        <?php endif; ?>
                        <a href="<?php echo new \App\Services\HttpService()->url('response_attachment', ['id' => $resp['id']]); ?>"
                           class="btn btn--outline btn--sm"><?php echo $isImage ? '&#11015;' : '&#128206;'; ?> <?php echo $fmt->e($resp['attachment_name']); ?></a>
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
        <a href="<?php echo new \App\Services\HttpService()->url('report_edit', ['uuid' => $report->uuid]); ?>" class="btn btn--secondary">Modifier</a>
    <?php endif; ?>

    <?php if ($canRespondToReport): ?>
        <a href="<?php echo new \App\Services\HttpService()->url('report_respond', ['uuid' => $report->uuid]); ?>" class="btn btn--primary">Répondre</a>
    <?php endif; ?>

    <?php if ($canAbandon): ?>
        <a href="<?php echo new \App\Services\HttpService()->url('report_abandon', ['uuid' => $report->uuid]); ?>" class="btn btn--danger">Abandonner le signalement</a>
        <small class="help-text help-text--danger">(Le signalement est marqué comme abandonné mais reste consultable)</small>
    <?php endif; ?>

    <?php if ($canReopen): ?>
        <a href="<?php echo new \App\Services\HttpService()->url('report_reopen', ['uuid' => $report->uuid]); ?>" class="btn btn--warning">Réouvrir ce signalement</a>
    <?php endif; ?>

    <a href="<?php echo new \App\Services\HttpService()->url('report_print', ['uuid' => $report->uuid]); ?>" class="btn btn--outline" target="_blank" rel="noopener noreferrer">Imprimer ou enregistrer en PDF <span class="sr-only">(nouvelle fenêtre)</span></a>
    <a href="<?php echo new \App\Services\HttpService()->url('report_list', ['type' => $type]); ?>" class="btn btn--secondary">Retour à la liste</a>
</div>
