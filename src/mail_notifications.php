<?php

use App\DTO\OutboxMessage;
use App\Enum\OutboxEvent;
use App\Repository\AnonymizationPolicy;
use App\Repository\EmailOutboxRepository;
use App\Repository\ReportAgentRepository;
use App\Repository\ReportRepository;
use App\Repository\UserRepository;
use App\Enum\UserRole;
use App\Repository\NotificationRepository;

/**
 * Mail Notification Functions — Application SST DREETS BFC
 *
 * Notification dispatch functions for email.
 * Split from mail.php for readability.
 * Email body builders are in mail_templates.php + mail/email_renderer.php.
 */

require_once __DIR__ . '/mail_templates.php';
require_once __DIR__ . '/mail/email_renderer.php';

/**
 * Met un message de notification en file dans l'outbox SMTP (durable).
 *
 * Enqueue transactionnel — l'insertion partage la transaction métier de
 * l'appelant (EmailOutboxRepository::enqueue() la rejoint si elle est active,
 * sans committer) : un rollback métier annule aussi la mise en file, et un
 * échec d'enqueue fait échouer l'action. Aucun transport SMTP n'a lieu ici ;
 * une fois committée, la ligne est durable (worker + retry/backoff), drainée
 * hors transaction, et dédupliquée par (événement, identité, destinataire).
 * Hors transaction (best-effort), l'action étant déjà committée, un échec
 * d'enqueue ne peut plus la faire échouer.
 *
 * La sentinelle d'anonymisation n'est jamais mise en file (le repository la
 * refuse — on court-circuite ici pour ne pas construire de message inutile).
 *
 * @param string $identity Identité logique de l'action (uuid, uuid:userId…)
 * @return bool true si une ligne a été insérée ; false si doublon/sentinelle
 */
function enqueueNotification(
    PDO $pdo,
    OutboxEvent $event,
    string $identity,
    string $recipient,
    string $subject,
    string $body,
): bool {
    if (AnonymizationPolicy::isAnonymizedEmail($recipient)) {
        // @silent-ok: invariant produit — la sentinelle n'est jamais destinataire,
        // l'absence d'envoi est le comportement attendu (pas un échec).
        return false;
    }

    return new EmailOutboxRepository($pdo)->enqueue(new OutboxMessage(
        recipient: $recipient,
        subject: $subject,
        body: $body,
        dedupKey: $event->dedupKey($identity, $recipient),
    ));
}

/**
 * Annule une transaction uniquement si CET appel l'a ouverte et qu'elle est
 * encore active.
 *
 * Isolé dans cette fonction : PHPStan considère PDO::inTransaction() comme
 * pure et replierait le test inline en « toujours faux » (même contournement
 * que EmailOutboxRepository::rollBackIfOwned()).
 */
function rollbackOwnedTransaction(PDO $pdo, bool $ownsTransaction): void
{
    if ($ownsTransaction && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

/**
 * Notify relevant people about a new report.
 *
 * @param PDO    $pdo        Database connection
 * @param string $reportUuid The new report UUID
 * @param string $type       Report type (rsst/rami/dgi)
 * @param int    $siteId     Site ID where report was filed
 */
function notifyNewReport(PDO $pdo, string $reportUuid, string $type, int $siteId): void
{
    $report = ReportRepository::instance()->findById($reportUuid);
    if ($report === null) {
        return;
    }
    $registryLabel = getRegistryShortLabel($type);
    $subject = "Nouveau signalement $registryLabel — {$report->reference}";
    $reportUrl = absoluteUrl('report_view', ['uuid' => $reportUuid]);
    $body = '<html><body>';
    $body .= '<h2>Nouveau signalement enregistré</h2>';
    $body .= renderEmailField('Référence', $report->reference);
    $body .= renderEmailField('Registre', $registryLabel);
    $body .= renderEmailField('Objet', $report->objet);
    $body .= renderEmailField('Déclarant', $report->declarantPrenom . ' ' . $report->declarantNom);
    $body .= renderEmailField('Date de l\'événement', formatDateFR($report->dateEvenement));
    $body .= renderEmailLink($reportUrl, 'Consulter le signalement');
    $body .= '</body></html>';
    // Collect recipients: per-site + global
    $recipients = getNotificationRecipients($pdo, $siteId);
    foreach ($recipients as $email) {
        // Option A — enqueue figé (destinataire/sujet/corps déjà résolus) :
        // aucun envoi direct, le worker outbox transporte.
        enqueueNotification($pdo, OutboxEvent::ReportCreated, $reportUuid, $email, $subject, $body);
    }
    // Décision métier (Oracle) — AUCUNE notification CSA/CHSCT automatique à la
    // création : `consent_syndicat` est une consigne pour le superviseur, qui
    // déclenche lui-même la transmission via notifyReportTransmitted()
    // (action manuelle, auditée, dédupliquée par signalement × destinataire).
}

/**
 * Transmet MANUELLEMENT un signalement aux membres CSA/CHSCT (action
 * superviseur). Chaque membre actif portant le rôle Chsct et un email valide
 * est mis en file dans l'outbox, dédupliqué par (signalement × destinataire) :
 * rejouer la transmission ne crée jamais de doublon, tandis qu'un nouveau
 * membre reçoit bien sa propre ligne.
 *
 * @return int Nombre de lignes réellement mises en file
 */
function notifyReportTransmitted(PDO $pdo, string $reportUuid): int
{
    $report = ReportRepository::instance()->findById($reportUuid);
    if ($report === null) {
        return 0;
    }

    $registryLabel = getRegistryShortLabel($report->type);
    $reportUrl = absoluteUrl('report_view', ['uuid' => $reportUuid]);

    $enqueued = 0;
    $csaUsers = UserRepository::instance()->findByRole(UserRole::Chsct->value);
    foreach ($csaUsers as $csaUser) {
        if (empty($csaUser->email) || AnonymizationPolicy::isAnonymizedEmail($csaUser->email)) {
            continue;
        }
        $subject = 'Signalement ' . $registryLabel . ' — Notification ' . getRoleLabelShort(UserRole::Chsct->value) . " — {$report->reference}";
        $body = '<html><body>';
        $body .= '<h2>Notification ' . $registryLabel . ' — Article L4131-2 du Code du travail</h2>';
        $body .= '<p>Conformément à l\'article L4131-2 du Code du travail, vous êtes informé(e) de la transmission d\'un signalement relatif à un danger grave et imminent.</p>';
        $body .= renderEmailField('Référence', $report->reference);
        $body .= renderEmailField('Objet', $report->objet);
        $body .= renderEmailField('Déclarant', $report->declarantPrenom . ' ' . $report->declarantNom);
        $body .= renderEmailLink($reportUrl, 'Consulter le signalement');
        $body .= '</body></html>';
        if (enqueueNotification(
            $pdo,
            OutboxEvent::ReportTransmitted,
            $reportUuid,
            (string) $csaUser->email,
            $subject,
            $body,
        )) {
            $enqueued++;
        }
    }

    return $enqueued;
}
/**
 * Sélectionne les destinataires de la notification de réponse (oracle).
 *
 * INVARIANT (décision produit) : users.email est NOT NULL avec sentinelle
 * RGPD d'anonymisation AnonymizationPolicy::ANONYMIZED_EMAIL
 * ('anonyme@anonyme.invalid', domaine .invalid RFC 2606). Un compte réel
 * porte toujours un email valide (validation obligatoire à la création et à
 * l'édition) ; la sentinelle n'est écrite QUE par le chemin d'anonymisation
 * et n'est jamais destinataire — comparaison insensible à la casse via
 * AnonymizationPolicy::isAnonymizedEmail().
 *
 * Les agents rattachés sont notifiés indépendamment de l'email du déclarant
 * (dédupliquée contre l'email du déclarant).
 *
 * @param array{email: string|null}|null $declarant Utilisateur déclarant (ou null si introuvable)
 * @param array<int, array{id?: int, nom?: string, prenom?: string|null, email?: string|null}> $linkedAgents
 * @return list<array{email: string, role: string}> role ∈ {'declarant','linked'}
 */
function buildResponseNotificationTargets(?array $declarant, array $linkedAgents): array
{
    $targets = [];
    $declarantEmail = ($declarant !== null && !empty($declarant['email'])) ? (string) $declarant['email'] : null;

    // Invariant sentinelle (décision produit) — la sentinelle d'anonymisation
    // n'est jamais un destinataire réel (compte anonymisé) ; comparaison
    // insensible à la casse via AnonymizationPolicy::isAnonymizedEmail().
    if ($declarantEmail !== null && !AnonymizationPolicy::isAnonymizedEmail($declarantEmail)) {
        $targets[] = ['email' => $declarantEmail, 'role' => 'declarant'];
    }

    $seen = $declarantEmail !== null ? [strtolower($declarantEmail)] : [];
    foreach ($linkedAgents as $linkedAgent) {
        $email = (string) ($linkedAgent['email'] ?? '');
        if ($email === '' || AnonymizationPolicy::isAnonymizedEmail($email)) {
            continue;
        }
        $lower = strtolower($email);
        if (in_array($lower, $seen, true)) {
            continue;
        }
        $seen[] = $lower;
        $targets[] = ['email' => $email, 'role' => 'linked'];
    }

    return $targets;
}

/**
 * Notify the declarant that their report has received a response.
 *
 * @param PDO    $pdo          Database connection
 * @param string $reportUuid   Report UUID
 * @param int    $respondentId The responding user's ID
 * @param int    $responseId   Identifiant de la réponse (report_responses.id) —
 *                             identité d'OCCURRENCE du dedup_key : deux
 *                             réponses successives ont des id distincts, donc
 *                             aucune notification n'est perdue.
 */
function notifyReportResponse(PDO $pdo, string $reportUuid, int $respondentId, int $responseId): void
{
    $report = ReportRepository::instance()->findById($reportUuid);
    if ($report === null) {
        return;
    }
    /** @var int */
    $declarantId = $report->declarantId;
    $declarant = UserRepository::instance()->findById($declarantId);
    /** @var string */
    $reportType = $report->type;
    $registryLabel = getRegistryShortLabel($reportType);
    $respondent = UserRepository::instance()->findById($respondentId);
    if ($respondent === null) {
        return;
    }
    $reportUrl = absoluteUrl('report_view', ['uuid' => $reportUuid]);

    // Oracle — destinataires via le helper pur : un déclarant sans email
    // (légal) ne prive plus les agents rattachés de leur notification.
    /** @var array{email: string|null}|null $declarantArray */
    $declarantArray = $declarant !== null ? ['email' => $declarant->email] : null;
    $linkedAgents = ReportAgentRepository::instance()->getLinkedAgents($reportUuid);
    $targets = buildResponseNotificationTargets($declarantArray, $linkedAgents);

    foreach ($targets as $target) {
        $subject = $target['role'] === 'declarant'
            ? "Réponse à votre signalement $registryLabel — {$report->reference}"
            : "Réponse au signalement $registryLabel — {$report->reference}";
        $body = '<html><body>';
        if ($target['role'] === 'declarant') {
            $body .= '<h2>Votre signalement a reçu une réponse</h2>';
            $body .= renderEmailField('Référence', $report->reference);
            $body .= renderEmailField('Répondant', $respondent->prenom . ' ' . $respondent->nom);
            $body .= renderEmailField('Nouvel état', ETAT_LABELS[$report->etat] ?? $report->etat);
            $body .= renderEmailLink($reportUrl, 'Consulter la réponse');
        } else {
            $body .= '<h2>Réponse au signalement</h2>';
            $body .= '<p>Le signalement <strong>' . e($report->reference) . '</strong> auquel vous êtes rattaché(e) a reçu une réponse.</p>';
            $body .= renderEmailField('Répondant', $respondent->prenom . ' ' . $respondent->nom);
            $body .= renderEmailField('Nouvel état', ETAT_LABELS[$report->etat] ?? $report->etat);
            $body .= renderEmailLink($reportUrl, 'Consulter la réponse');
        }
        $body .= '</body></html>';
        // Identité logique de la réponse : signalement + id de la réponse.
        // Deux réponses successives du même répondant ont des responseId
        // distincts → deux clés distinctes (aucune perte). Le rejeu de la même
        // réponse (même id) reste idempotent.
        enqueueNotification(
            $pdo,
            OutboxEvent::ReportResponded,
            $reportUuid . ':' . $responseId,
            $target['email'],
            $subject,
            $body,
        );
    }
}

/**
 * Get all notification recipients for a given site.
 *
 * Mode sans site (AGENTS.md §"Mode sans site") : si $siteId est null, on saute
 * la requête per-site et on retourne uniquement les destinataires globaux.
 * Ne jamais coercer null → 0 ici — c'est le bug classique documenté.
 *
 * @param PDO     $pdo     Database connection
 * @param int|null $siteId Site ID, ou null pour le mode sans site
 * @return array<int, string>  Array of email strings
 */
function getNotificationRecipients(PDO $pdo, ?int $siteId): array
{
    $emails = [];
    $seen = [];
    // Per-site — seulement si un vrai site est rattaché.
    if ($siteId !== null && $siteId > 0) {
        $siteEmails = NotificationRepository::instance()->findSiteEmails($siteId);
        foreach ($siteEmails as $email) {
            $lower = strtolower($email);
            $seen[] = $lower;
            $emails[] = $email;
        }
    }
    // Global — toujours, même en mode sans site.
    $globalEmails = NotificationRepository::instance()->findGlobalEmails();
    foreach ($globalEmails as $email) {
        $lower = strtolower($email);
        if (!in_array($lower, $seen, true)) {
            $seen[] = $lower;
            $emails[] = $email;
        }
    }
    return $emails;
}

/**
 * Notify a user that their role has been changed.
 *
 * Option A outbox — la notification est MISE EN FILE (durable) au lieu d'être
 * envoyée en direct. Le bool retourné signale la mise en file (true) ou son
 * impossibilité (utilisateur introuvable ou destinataire = sentinelle), plus
 * un verdict SMTP : le transport appartient au worker outbox.
 *
 * $eventKey est généré UNE fois par le handler pour cette transition : deux
 * changements de rôle distincts (même cyclique A→B→A→B) portent des clés
 * distinctes → aucune notification perdue ; rejouer la même transition
 * (même eventKey) reste idempotent.
 *
 * @param PDO    $pdo      Database connection
 * @param int    $userId   The user whose role changed
 * @param string $oldRole  Previous role
 * @param string $newRole  New role
 * @param string $eventKey Identité d'occurrence générée par le handler
 * @return bool True si le message a été mis en file, false sinon
 */
function notifyRoleChange(PDO $pdo, int $userId, string $oldRole, string $newRole, string $eventKey): bool
{
    $user = UserRepository::instance()->findById($userId);
    if ($user === null || empty($user->email)) {
        return false;
    }
    $appName = getConfigService()->get('app_nom_organisation', 'DREETS BFC');
    $oldLabel = ROLE_LABELS[$oldRole] ?? $oldRole;
    $newLabel = ROLE_LABELS[$newRole] ?? $newRole;
    $subject = "Changement de votre rôle dans $appName";
    $body = '<html><body>';
    $body .= '<h2>Changement de rôle</h2>';
    $body .= '<p>Bonjour ' . htmlspecialchars($user->prenom . ' ' . $user->nom) . ',</p>';
    $body .= "<p>Votre rôle dans l'application <strong>" . htmlspecialchars($appName) . '</strong> a été modifié par un administrateur :</p>';
    $body .= '<table style="border-collapse:collapse; font-family:sans-serif; font-size:14px; margin:16px 0;">';
    $body .= '<tr><td style="padding:6px 16px; color:#888;">Ancien rôle</td><td style="padding:6px 16px;">' . htmlspecialchars($oldLabel) . '</td></tr>';
    $body .= '<tr><td style="padding:6px 16px; color:#888;">Nouveau rôle</td><td style="padding:6px 16px;"><strong>' . htmlspecialchars($newLabel) . '</strong></td></tr>';
    $body .= '</table>';
    if ($newRole === UserRole::Superviseur->value) {
        $body .= "<p>En tant que <strong>Superviseur</strong>, vous pouvez désormais : répondre aux signalements, gérer les utilisateurs, consulter la synthèse et les statistiques, exporter les données, et configurer les paramètres de l'application.</p>";
    } elseif ($newRole === UserRole::Chsct->value) {
        $body .= '<p>En tant que <strong>' . e(getRoleLabel(UserRole::Chsct->value)) . '</strong>, vous pouvez consulter tous les signalements (y compris confidentiels), la synthèse, les statistiques et les exports.</p>';
    } else {
        $body .= "<p>En tant qu'<strong>Agent</strong>, vous pouvez créer des signalements et suivre leurs réponses.</p>";
    }
    $body .= '<p>Si vous pensez que cette modification est une erreur, veuillez contacter votre administrateur.</p>';
    $body .= '<hr style="margin:16px 0; border:none; border-top:1px solid #ddd;">';
    $body .= "<p style=\"font-size:12px; color:#888;\">Cet e-mail a été envoyé automatiquement par l'application $appName. Ne pas répondre directement à ce message.</p>";
    $body .= '</body></html>';
    return enqueueNotification(
        $pdo,
        OutboxEvent::RoleChanged,
        $userId . ':' . $eventKey,
        $user->email,
        $subject,
        $body,
    );
}

/**
 * Queue confirmation emails to agents invited to be linked to a report.
 * Each agent receives a unique token link they must click to confirm.
 *
 * Option A outbox (remplace le send-then-persist du bug #10) — invariant
 * « enqueue durable » : l'invite (avec son token) ET le message sont écrits
 * dans la MÊME transaction, AVANT tout transport. Un SMTP indisponible ne
 * perd plus l'invitation : le worker outbox rejouera l'envoi. La
 * déduplication porte sur l'invite VIVANTE (non confirmée) : un rejeu
 * immédiat (double POST) n'insère ni invite ni message supplémentaire, mais
 * une ré-invitation après confirmation/purge produit une nouvelle invite
 * (nouveau token → nouveau dedup_key) — aucune ré-invitation n'est perdue.
 *
 * La sentinelle d'anonymisation n'est jamais invitée ni mise en file.
 *
 * @param array<string> $emails  List of email addresses
 * @return list<string> Emails dont la mise en file a échoué (invite NON persistée)
 */
function sendAgentInviteEmails(PDO $pdo, string $reportUuid, array $emails): array
{
    $report = ReportRepository::instance()->findById($reportUuid);
    if ($report === null) {
        return [];
    }
    $outbox = new EmailOutboxRepository($pdo);
    $agents = new ReportAgentRepository($pdo);
    $failed = [];
    foreach ($emails as $email) {
        $email = trim($email);
        if ($email === '') {
            continue;
        }
        // Invariant produit — la sentinelle n'est jamais destinataire : ni
        // invite persistée, ni message en file (l'absence d'envoi est voulue).
        if (AnonymizationPolicy::isAnonymizedEmail($email)) {
            continue;
        }
        // Transaction locale (jamais ouverte par enqueue()) : invite + message
        // sont atomiques. Si l'appelant est déjà en transaction, on la rejoint.
        $ownsTransaction = !$pdo->inTransaction();
        try {
            if ($ownsTransaction) {
                $pdo->beginTransaction();
            }

            // Une invitation NON CONFIRMÉE déjà vivante pour (signalement, e-mail)
            // prouve que le message a déjà été mis en file : ne pas la doubler
            // (idempotence d'un double POST). Dès que l'invite est confirmée ou
            // purgée par le lazy cron (>30j non confirmée, cron_cleanup.php), une
            // RÉ-INVITATION devient possible : le nouveau token produit un nouveau
            // dedup_key, donc aucune ré-invitation n'est perdue.
            if ($agents->hasUnconfirmedInvite($reportUuid, $email)) {
                if ($ownsTransaction) {
                    $pdo->commit();
                }
                continue;
            }

            $token = bin2hex(random_bytes(32));
            $confirmUrl = absoluteUrl('agent_confirm', ['token' => $token]);
            $subject = 'Vous avez été rattaché(e) au signalement ' . $report->reference;
            $body = renderEmailBody(
                'Confirmation de rattachement',
                '<p>Bonjour,</p>'
                . '<p>Vous avez été rattaché(e) au signalement <strong>' . e($report->reference) . '</strong> par le déclarant.</p>'
                . renderEmailField('Objet', $report->objet)
                . '<p>Pour confirmer votre rattachement, cliquez sur le bouton ci-dessous :</p>'
                . renderEmailButton($confirmUrl, 'Confirmer mon rattachement')
                . '<p style="font-size:13px; color:#888;">Si vous ne souhaitez pas être rattaché(e), ignorez cet e-mail. Aucune action ne sera effectuée.</p>'
            );

            // Identité = signalement + token de CETTE invite : chaque occurrence
            // d'invitation est unique et rejouable sans collision.
            $outbox->enqueue(new OutboxMessage(
                recipient: $email,
                subject: $subject,
                body: $body,
                dedupKey: OutboxEvent::AgentInvite->dedupKey($reportUuid . ':' . $token, $email),
            ));

            $agents->createAgentInviteWithToken($reportUuid, $email, $token);

            if ($ownsTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            rollbackOwnedTransaction($pdo, $ownsTransaction);
            if (!$ownsTransaction) {
                // On participe à la transaction métier (création atomique) : un
                // échec d'invite/enqueue doit annuler l'action englobante, jamais
                // être avalé (pas de signalement sans ses invitations).
                throw $e;
            }
            // @silent-ok: best-effort per-invite in a loop, appel hors transaction —
            // one failed invite must not stop the others from being queued.
            $failed[] = $email;
            error_log('[SST-MAIL] sendAgentInviteEmails failed for ' . $email . ': ' . $e->getMessage());
        }
    }
    return $failed;
}

/**
 * Get base URL for links in emails.
 *
 * @return string
 */
function getBaseUrl(): string
{
    // Explicit config takes priority — $_SERVER['HTTP_HOST'] isn't reliable
    // in every context email gets sent from (missing entirely outside a
    // real HTTP request, or reflecting an internal hostname behind a
    // reverse proxy) and falls back to 'localhost', producing a link that
    // only resolves on the server itself — broken for every recipient.
    $configured = trim(getConfigService()->get('app_base_url', ''));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // The app doesn't have to be deployed at the domain root — IIS commonly
    // mounts it as an "application" under a site, e.g.
    // https://server/sst/index.php. url() always returns a path relative
    // to index.php ("index.php?page=..."), which works fine for in-app
    // navigation (the browser resolves it against whatever URL it's
    // already on) but not for an email, which needs the full path
    // including that subfolder. SCRIPT_NAME carries it (e.g.
    // "/sst/index.php"); PHP's dirname() returns "/" for a root
    // deployment ("/index.php") — normalize that to '' so this doesn't
    // produce a trailing-slash-only segment.
    $scriptDir = \dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $subfolder = ($scriptDir === '/' || $scriptDir === '\\' || $scriptDir === '.') ? '' : $scriptDir;
    return "$protocol://$host$subfolder";
}

/**
 * Build an absolute URL (with scheme + host) for use in an email — url()
 * alone returns a path relative to the current page ("index.php?..."),
 * which has no meaning in an email client (there's no "current page" to
 * resolve it against). Prefer this over combining getBaseUrl() and url()
 * by hand at each call site — that pattern already caused a real bug once
 * (sendAgentInviteEmails() built a plain url() with no host at all).
 *
 * @param array<string, string|int|null> $params
 */
function absoluteUrl(string $page, array $params = []): string
{
    return getBaseUrl() . '/' . url($page, $params);
}
