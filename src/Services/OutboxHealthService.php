<?php

/**
 * OutboxHealthService — Évaluation de l'état de santé de l'outbox SMTP.
 *
 * Règle métier unique, consommée par la bannière locale
 * (templates/outbox_banner.php) : l'outbox est en incident dès qu'un message
 * est en échec définitif (failed, terminal et conservé) OU qu'un message
 * pending ÉCHU attend depuis plus de STALE_PENDING_SECONDS (le drain n'aboutit
 * plus). Un pending en attente de backoff (next_attempt_at futur) n'est PAS un
 * incident : son attente est programmée.
 *
 * La lecture SQL reste confinée dans EmailOutboxRepository
 * (NoSqlOutsideRepositoryRule) ; ce service ne fait que projeter le verdict.
 */

namespace App\Services;

use App\Repository\EmailOutboxRepository;

final readonly class OutboxHealthService
{
    /**
     * Seuil « pending trop ancien » : 1 h. Le drain outbox tourne toutes les
     * 5 min (CronService::MAIL_DRAIN_INTERVAL_SECONDS) ; un message échu encore
     * en file après une heure signale que le drain ne parvient plus à avancer.
     */
    public const int STALE_PENDING_SECONDS = 3600;

    public function __construct(
        private EmailOutboxRepository $outbox,
        private int $stalePendingSeconds = self::STALE_PENDING_SECONDS,
    ) {}

    /**
     * Photographie de l'état outbox : compteurs bruts + verdict d'incident.
     *
     * @return array{failed:int, stale_pending:int, incident:bool}
     */
    public function snapshot(?string $now = null): array
    {
        $counts = $this->outbox->healthCounts($this->stalePendingSeconds, $now);

        return [
            'failed'        => $counts['failed'],
            'stale_pending' => $counts['stale_pending'],
            'incident'      => $counts['failed'] > 0 || $counts['stale_pending'] > 0,
        ];
    }

    /** Une intervention (technicien) est-elle nécessaire ? */
    public function hasIncident(?string $now = null): bool
    {
        return $this->snapshot($now)['incident'];
    }

    public static function instance(): self
    {
        static $instance = null;
        if ($instance === null) {
            if (function_exists('getContainer') && getContainer()->has(self::class)) {
                $instance = getContainer()->get(self::class);
            } else {
                $instance = new self(EmailOutboxRepository::instance());
            }
        }
        return $instance;
    }
}
