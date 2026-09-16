<?php

namespace App\Enum;

/**
 * OutboxStatus — états du cycle de vie d'un message dans l'outbox SMTP.
 *
 * pending    : en attente de traitement (éligible si next_attempt_at est NULL
 *              ou échu).
 * processing : réclamé par un worker (claim atomique), en cours d'envoi.
 * sent       : envoyé avec succès (état terminal).
 * failed     : échec définitif (état terminal) ; un retry passe par
 *              scheduleRetry() qui repasse la ligne en pending avec backoff.
 */
enum OutboxStatus: string
{
    case Pending    = 'pending';
    case Processing = 'processing';
    case Sent       = 'sent';
    case Failed     = 'failed';
}
