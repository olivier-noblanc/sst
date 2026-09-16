<?php

/**
 * EmailOutboxWorker — Drain de l'outbox SMTP.
 *
 * Un run = trois temps, dans cet ordre :
 *   1. récupération des claims orphelins (processing trop ancien → pending) ;
 *   2. claim ATOMIQUE et BORNÉ d'un petit lot de lignes pending
 *      (EmailOutboxRepository::claimBatch, qui committe sa propre transaction) ;
 *   3. transport SMTP HORS transaction, puis clôture ligne par ligne :
 *        - succès              → markSent (terminal) ;
 *        - échec sous le cap   → scheduleRetry (pending + backoff exponentiel) ;
 *        - échec au cap        → markFailed (terminal, conservé).
 *
 * Rien n'est jamais supprimé : un échec temporaire reste pending (rejouable au
 * prochain run) et un échec définitif reste failed (conservé, plus réclamé).
 * Le message est donc toujours récupérable — l'outbox ne perd aucune ligne.
 *
 * Le transport passe par sendMail(), qui honore le seam injectable
 * setMailerSeam() (tests) et gère le repli PHP mail() : sendMail() ne lève
 * jamais pour une erreur de transport, il retourne un verdict booléen. Un
 * échec est donc un verdict, pas une exception — seule une erreur DB remonte
 * (crash hard, jamais d'échec silencieux).
 *
 * Le worker est appelé par le lazy cron (CronService) : pas de cron système.
 */

namespace App\Services;

use App\Repository\EmailOutboxRepository;

require_once __DIR__ . '/../mail.php';

final readonly class EmailOutboxWorker
{
    /** Taille de lot par défaut — petit, pour un claim court puis envoi hors transaction. */
    public const int DEFAULT_BATCH_SIZE = 20;

    /** Nombre de tentatives avant échec définitif. */
    public const int DEFAULT_MAX_ATTEMPTS = 5;

    /** Ancienneté minimale d'un processing pour être considéré orphelin (15 min). */
    public const int DEFAULT_STALE_AFTER_SECONDS = 900;

    public function __construct(
        private EmailOutboxRepository $outbox,
        private int $batchSize = self::DEFAULT_BATCH_SIZE,
        private int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS,
        private int $staleAfterSeconds = self::DEFAULT_STALE_AFTER_SECONDS,
    ) {}

    /**
     * Exécute un cycle de drainage.
     *
     * @param string|null $now Horodatage UTC injectable (« Y-m-d H:i:s ») — testabilité.
     *
     * @return array{recovered:int, sent:int, retried:int, failed:int}
     */
    public function run(?string $now = null): array
    {
        $now ??= gmdate('Y-m-d H:i:s');

        $recovered = $this->outbox->requeueStaleProcessing($this->staleAfterSeconds, $now);
        $claimed = $this->outbox->claimBatch($this->batchSize, $now);

        $sent = 0;
        $retried = 0;
        $failed = 0;

        foreach ($claimed as $message) {
            if ($this->deliver($message)) {
                if ($this->outbox->markSent($message['id'], $now)) {
                    $sent++;
                }
                continue;
            }

            $error = $this->failureReason($message);
            if ($message['attempts'] >= $this->maxAttempts) {
                $this->outbox->markFailed($message['id'], $error, $now);
                $failed++;
            } else {
                $this->outbox->scheduleRetry($message['id'], $error, $now);
                $retried++;
            }
        }

        return [
            'recovered' => $recovered,
            'sent'      => $sent,
            'retried'   => $retried,
            'failed'    => $failed,
        ];
    }

    /**
     * Transport d'un message — hors transaction (claimBatch a déjà committé).
     *
     * sendMail() est best-effort : il honore setMailerSeam(), gère le repli
     * PHP mail() et ne laisse jamais remonter d'exception transport. Seul un
     * échec DB (markSent/scheduleRetry/markFailed ci-dessus) peut remonter.
     *
     * @param array{id:int, dedup_key:string, recipient:string, subject:string, body:string, headers:string, attempts:int} $message
     */
    private function deliver(array $message): bool
    {
        return sendMail($message['recipient'], $message['subject'], $message['body']);
    }

    /**
     * Trace d'échec persistée dans last_error — nomme le destinataire et la
     * tentative pour que la ligne conservée soit exploitable (sans perte).
     *
     * @param array{id:int, dedup_key:string, recipient:string, subject:string, body:string, headers:string, attempts:int} $message
     */
    private function failureReason(array $message): string
    {
        return sprintf(
            'Échec d\'envoi SMTP pour %s (tentative %d/%d)',
            $message['recipient'],
            $message['attempts'],
            $this->maxAttempts
        );
    }
}
