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
 * Le run est BORNÉ DANS LE TEMPS (drainBudgetSeconds). Le drain s'exécute de
 * façon synchrone pendant des requêtes utilisateur (lazy cron au login, flush
 * post-enqueue) : un transport SMTP en trou noir fait payer ~90 s de timeout
 * par message, donc un lot de 20 bloquerait la requête ~30 min. Passé le
 * budget, le run s'arrête SANS clore les messages restants — ils demeurent en
 * processing et sont récupérés par requeueStaleProcessing() au prochain run,
 * jamais marqués en échec à tort, jamais perdus.
 *
 * Le worker est appelé par le lazy cron (CronService) : pas de cron système.
 */

namespace App\Services;

use App\Repository\EmailOutboxRepository;
use Closure;

require_once __DIR__ . '/../mail.php';

final readonly class EmailOutboxWorker
{
    /** Taille de lot par défaut — petit, pour un claim court puis envoi hors transaction. */
    public const int DEFAULT_BATCH_SIZE = 20;

    /** Nombre de tentatives avant échec définitif. */
    public const int DEFAULT_MAX_ATTEMPTS = 5;

    /** Ancienneté minimale d'un processing pour être considéré orphelin (15 min). */
    public const int DEFAULT_STALE_AFTER_SECONDS = 900;

    /**
     * Budget de temps (secondes) alloué à UN run de drainage.
     *
     * Borne la durée d'un drain synchrone : un SMTP en trou noir fait payer
     * ~90 s de timeout par message, donc un lot de 20 bloquerait la requête
     * hôte ~30 min. Passé ce budget, le run s'arrête sans clore les messages
     * non traités (ils restent en processing, récupérables par
     * requeueStaleProcessing()).
     */
    public const int DEFAULT_DRAIN_BUDGET_SECONDS = 5;

    /** @var (Closure(): float)|null Horloge du run (seam de test) — null = horloge monotone réelle. */
    private ?Closure $clock;

    public function __construct(
        private EmailOutboxRepository $outbox,
        private int $batchSize = self::DEFAULT_BATCH_SIZE,
        private int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS,
        private int $staleAfterSeconds = self::DEFAULT_STALE_AFTER_SECONDS,
        private int $drainBudgetSeconds = self::DEFAULT_DRAIN_BUDGET_SECONDS,
        ?Closure $clock = null,
    ) {
        $this->clock = $clock;
    }

    /**
     * Exécute un cycle de drainage, borné dans le temps.
     *
     * @param string|null $now Horodatage UTC injectable (« Y-m-d H:i:s ») — testabilité.
     *
     * @return array{recovered:int, sent:int, retried:int, failed:int, deferred:int}
     */
    public function run(?string $now = null): array
    {
        $now ??= gmdate('Y-m-d H:i:s');
        $budget = max(0, $this->drainBudgetSeconds);
        $startedAt = $this->clockSeconds();

        $recovered = $this->outbox->requeueStaleProcessing($this->staleAfterSeconds, $now);
        $claimed = $this->outbox->claimBatch($this->batchSize, $now);

        $sent = 0;
        $retried = 0;
        $failed = 0;
        $processed = 0;
        $deferred = 0;

        foreach ($claimed as $message) {
            if ($this->clockSeconds() - $startedAt >= $budget) {
                // Budget épuisé : arrêt SANS clore les messages restants. Ils
                // restent en processing (ni sent, ni retry, ni failed) et ne
                // sont jamais perdus — requeueStaleProcessing() les repassera
                // en pending dès qu'ils seront considérés orphelins.
                $deferred = count($claimed) - $processed;
                break;
            }
            $processed++;

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
            'deferred'  => $deferred,
        ];
    }

    /**
     * Lecture de l'horloge du run, en secondes.
     *
     * Défaut : horloge monotone hrtime() — insensible aux sauts d'heure
     * système. Le seam injectable (constructeur) permet de simuler un
     * dépassement de budget en test.
     */
    private function clockSeconds(): float
    {
        if ($this->clock !== null) {
            return ($this->clock)();
        }

        return hrtime(true) / 1_000_000_000;
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
