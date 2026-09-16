<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * OutboxMessage — message à mettre en file dans l'outbox SMTP.
 *
 * dedupKey est l'identité LOGIQUE de l'événement (ex. "response:<uuid>:<userId>") :
 * c'est elle qui rend enqueue() idempotent — deux enqueues du même événement
 * ne produisent qu'une seule ligne. Un dedupKey vide est un bug d'appelant
 * (rejeté par EmailOutboxRepository::enqueue()).
 *
 * DTO pur (phparkitect : dépend uniquement de App\DTO / App\Enum) — aucune
 * logique métier, aucun accès I/O, donc constructible sans base ni requête.
 */
final readonly class OutboxMessage
{
    /** @phpstan-ignore shipmonk.deadMethod (DTO consommé par EmailOutboxRepository + tests) */
    public function __construct(
        public string $recipient,
        public string $subject,
        public string $body,
        public string $dedupKey,
        public string $headers = '',
    ) {}
}
