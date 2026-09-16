# Outbox SMTP — pertes déterministes (corrigées) et atomicité transactionnelle (implémentée)

Date : 2026-09-16 (màj atomicité : même jour, 2ᵉ passe)
Périmètre : atomicité/dédup outbox + tests associés.
Statut : **pertes déterministes corrigées** ET **enqueue outbox atomique avec les
transactions métier** pour tous les chemins notifiants. Aucun SMTP dans une transaction.

> Aucun commit/push effectué. Fichiers en working tree non commité.

---

## 1. Pertes déterministes (corrigées — 1ʳᵉ passe)

Le `dedup_key` encodait le type d'événement, pas l'occurrence de l'action : `ON CONFLICT
DO NOTHING` avalait la 2 notification d'une action distincte. Identités désormais
uniques par occurrence :

| Événement | Identité (après) | Source |
|---|---|---|
| `report_responded` | `uuid:<responseId>` | `report_responses.id` |
| `report_reopened` | `uuid:<stateHistoryId>` | `report_state_history.id` |
| `report_abandoned` | `uuid:<stateHistoryId>` | `report_state_history.id` (historique désormais écrit aussi pour l'abandon) |
| `role_changed` | `<userId>:<eventKey>` | UUID par handler |
| `agent_invite` | `uuid:<inviteToken>:<email>` | token de l'invite |

Rejouer la même action reste idempotent. Preuves : `tests/unit/OutboxDeterministicLossTest.php`
(9 tests). Détail complet : voir l'historique de ce document (inchangé).

---

## 2. Atomicité transactionnelle (implémentée — 2 passe)

### Principe
L'action métier et l'insertion `email_outbox` partagent une seule transaction SQLite.
Rollback métier ⇒ aucune ligne outbox. Échec d'enqueue ⇒ rollback de l'action.
Le transport SMTP n'a jamais lieu dans la transaction : il est déclenché par un
callback **post-commit**.

### Mécanique
- **`src/Repository/TransactionManager.php`** (nouveau) : `run(callable $fn, ?callable
  $afterCommit)`. Ouvre si l'appelant n'a pas de transaction ; commit ; `$afterCommit`
  seulement si CET appel possède la transaction ; rollback + rethrow sur `Throwable`.
- **Repositories « join-if-active »** : `ReportWriteRepository::create/update`,
  `ReportLifecycleRepository::respondToReport/reopen/abandon` rejoignent une transaction
  ouverte sans committer (`$owns = !$pdo->inTransaction()`), et ne rollback que s'ils la
  possèdent. `respondToReport` rethrow au lieu de « status=Error » quand il rejoint une
  transaction (sinon l'appelant committerait une action partielle).
- **`ReportService`** englobe action + dispatch dans `TransactionManager::run()` :
  `create` (dont invitations via le param `$linkedEmails`), `respond`, `update` (dont
  invitations via `$inviteEmails`), `reopen`, `abandon`. Flush opportuniste en
  `$afterCommit` (jamais dans la transaction).
- **`handlers/user_edit_handler.php`** (role_changed) : `UserService::update` +
  `notifyRoleChange` dans une seule transaction ; `TransactionManager::run`.
- **Listeners de production** (`src/Event/event_listeners.php`) : un échec d'enqueue est
  **repropagé** (`throw $e`) quand la transaction métier est ouverte (`$data->pdo` en
  transaction), ce qui déclenche le rollback. Hors transaction, best-effort conservé
  (l'action est déjà committée) — plus de « zéro perte » feint.
- **`sendAgentInviteEmails`** : invite + message déjà atomiques ; rethrow (au lieu
  d'avaler) quand il rejoint une transaction métier.
- **Boundaries** : `report_create_handler` et `report_edit_handler` surfacent l'échec
  (flash + saisie conservée), la transaction ayant tout annulé.
- **DI** : `bootstrap_services.php` injecte `NotificationService` dans `ReportService`
  (flush post-commit).

### Preuves (RED → GREEN)
`tests/unit/OutboxAtomicityTest.php` — **13 tests** :
- rollback métier ⇒ 0 signalement **et** 0 ligne outbox ; commit ⇒ les deux présents ;
- `report.created/responded/reopened/abandoned` dispatchés **dans** la transaction ;
- échec de listener ⇒ création annulée (0 signalement) ;
- create + invitations atomiques (succès ⇒ invite + outbox ; échec ⇒ rollback complet) ;
- listener de production : propage dans une transaction, best-effort hors transaction ;
- role_changed : update + notification rollback/commit ensemble.

---

## 3. Fichiers modifiés (2ᵉ passe)

Production :
- `src/Repository/TransactionManager.php` (nouveau)
- `src/Repository/ReportWriteRepository.php` (create/update join-if-active)
- `src/Repository/ReportLifecycleRepository.php` (respond/reopen/abandon join-if-active)
- `src/Services/ReportService.php` (transaction + invitations atomiques + flush post-commit)
- `src/Services/NotificationService.php` (déjà : flush ; inchangé ici)
- `src/Event/event_listeners.php` (propagation conditionnelle)
- `src/mail_notifications.php` (sendAgentInviteEmails rethrow en transaction)
- `handlers/report_create_handler.php` (invitations via create, boundary d'erreur)
- `handlers/report_edit_handler.php` (invitations via update, boundary d'erreur)
- `handlers/user_edit_handler.php` (update + notification atomiques)
- `src/bootstrap_services.php` (wiring NotificationService → ReportService)

Tests :
- `tests/unit/OutboxAtomicityTest.php` (nouveau, 13 tests)

Perturbation annulée : `.deptrac.cache` restauré après exécution de deptrac.

---

## 4. Points restant NO-GO (précis)

1. **`src/error_notify.php`** (alerte erreur admin) : hors périmètre (best-effort, throttle
   fichier, pas de table outbox) — décision du plan. Reste non atomique par construction.
2. **At-least-once assumé** : un crash entre le verdict SMTP et `markSent` côté worker
   peut produire un **doublon** (préféré à une perte, décision produit). Inchangé.
3. **`sendAgentInviteEmails` best-effort** : sa branche « standalone » n'est plus empruntée
   en production (tous les appels sont désormais transactionnels) ; conservée par sécurité.
4. **Dette Rector — obsolète pour le lot** : après nettoyage, `rector --dry-run` est
   **clean pour les fichiers du lot** — plus aucun `ReadOnlyClassRector`
   (`EmailOutboxRepository`/`EmailOutboxWorker`/`OutboxHealthService`) ni
   `NewMethodCallWithoutParenthesesRector` (`NotificationService`/`mail_notifications`).
   Seul subsiste, **pré-existant et hors lot**, `NullCoalescingOperatorRector` sur
   `src/mail.php` (fichier non touché par cette passe).

---

## 5. Preuves d'exécution (2ᵉ passe)

- `phpunit --no-coverage tests/unit/OutboxAtomicityTest.php` → **OK (13 tests, 26 assertions)**.
- Suite complète → **OK (2040 tests, 5254 assertions)**.
- `phpstan analyse --memory-limit=1G` (level 8) → **No errors**.
- `deptrac analyse` → **0 violation** ; `phparkitect check` → **No violations**.
- `php-cs-fixer --dry-run` (fichiers modifiés) → **0 fix** ; `php -l` (39 fichiers) → **OK**.
- `rector --dry-run` → **clean pour le lot** ; seul subsiste `src/mail.php`
  (`NullCoalescingOperatorRector`, pré-existant, hors lot).