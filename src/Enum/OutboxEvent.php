<?php

namespace App\Enum;

/**
 * OutboxEvent — identité logique d'un événement notifiant mis en file.
 *
 * La valeur sert de préfixe au dedup_key de l'outbox SMTP : c'est elle qui
 * distingue deux événements différents visant le même destinataire. Aucune
 * magic string métier ne doit être écrite hors de cet enum.
 *
 * Enum PUR (layer Enum → DTO uniquement) : aucune dépendance applicative,
 * aucune lecture de config — le routage vers l'outbox est décidé par
 * l'appelant, pas ici.
 */
enum OutboxEvent: string
{
    case ReportCreated   = 'report_created';
    case ReportResponded = 'report_responded';
    case ReportReopened  = 'report_reopened';
    case ReportAbandoned = 'report_abandoned';
    case RoleChanged     = 'role_changed';
    case AgentInvite     = 'agent_invite';
    case ReportTransmitted = 'report_transmitted';

    /**
     * Clé de déduplication d'un message : événement + identité logique de
     * l'action + destinataire normalisé (casse/espaces). Deux enqueues du même
     * message logique produisent la même clé → ON CONFLICT DO NOTHING.
     *
     * @param string $identity Identité de l'action (uuid de signalement, etc.)
     * @param string $recipient Destinataire brut (normalisé ici)
     */
    public function dedupKey(string $identity, string $recipient): string
    {
        return $this->value . ':' . $identity . ':' . strtolower(trim($recipient));
    }
}
