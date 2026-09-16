<?php

/** ReportService — Couche métier pour les signalements. */

namespace App\Services;

use App\Enum\ReportState;
use App\Enum\RespondStatus;
use App\Enum\UserRole;
use App\Enum\VisibilityMode;
use RuntimeException;
use InvalidArgumentException;
use App\Repository\ReportRepository;
use App\Repository\RegistryFieldRepository;
use App\Repository\RegistryRepository;
use App\Repository\ReportLifecycleRepository;
use App\Repository\TransactionManager;
use App\Event\EventDispatcher;
use App\DTO\ReportEventData;
use App\DTO\CreateReportCommand;
use App\DTO\ReportData;
use App\DTO\SiteId;
use App\DTO\UpdateReportCommand;
use App\DTO\RespondToReportCommand;
use App\DTO\ReopenReportCommand;

class ReportService
{
    public function __construct(
        private readonly ReportRepository $repo,
        private readonly EventDispatcher $events,
        private readonly ReportStateMachine $stateMachine,
        private readonly ?NotificationService $notifications = null,
        private readonly ?TransactionManager $transactionManager = null,
    ) {}

    /**
     * TransactionManager de l'action : partagé si injecté (container), sinon
     * construit sur le PDO du repository. Aucun SMTP n'y transite.
     */
    private function transactionManager(): TransactionManager
    {
        return $this->transactionManager ?? new TransactionManager($this->repo->getPdo());
    }

    /**
     * Flush opportuniste APRÈS le commit (jamais dans la transaction) : le
     * listener a appelé flushOutbox() mais la garde `inTransaction()` l'a
     * neutralisé ; on draine une fois la transaction métier close.
     */
    private function flushOutboxAfterCommit(): void
    {
        $this->notifications?->flushOutbox();
    }

    /**
     * Validate linked agent emails: format + same domain as declarant.
     *
     * @param array{email?: string|null} $user
     * @return list<string> valid emails
     */
    public function validateLinkedEmails(string $linkedEmailsRaw, array $user): array
    {
        if (empty(trim($linkedEmailsRaw))) {
            return [];
        }

        $declarantEmail = (string) ($user['email'] ?? '');
        $emailDomain = '';
        if ($declarantEmail !== '' && str_contains($declarantEmail, '@')) {
            $emailDomain = substr($declarantEmail, (int) strrpos($declarantEmail, '@') + 1);
        }

        $validEmails = [];
        $linkedEmailsList = array_map(trim(...), explode(',', $linkedEmailsRaw));

        foreach ($linkedEmailsList as $em) {
            if (empty($em)) {
                continue;
            }
            if (filter_var($em, FILTER_VALIDATE_EMAIL) === false) {
                throw new InvalidArgumentException('Adresse e-mail invalide : ' . $em);
            }
            if ($emailDomain !== '') {
                $emDomain = substr($em, (int) strrpos($em, '@') + 1);
                if (strtolower($emDomain) !== strtolower($emailDomain)) {
                    throw new InvalidArgumentException('Seul le domaine @' . $emailDomain . ' est autorisé. Adresse refusée : ' . $em);
                }
            }
            $validEmails[] = $em;
        }

        return $validEmails;
    }

    /**
     * @param list<string> $linkedEmails E-mails d'agents à rattacher : traités
     *        DANS la transaction de création (invite + enqueue atomiques avec
     *        le signalement), et non après le commit.
     */
    public function create(CreateReportCommand $cmd, array $linkedEmails = []): ReportData
    {
        $this->validateForCreation($cmd);
        $this->validateCustomFields($cmd->type, $cmd->customFields);
        $cmd = $this->enforceVisibility($cmd);
        // Champs dynamiques : filtrage défensif (jamais de code à chemin
        // dédié ni de code inconnu en base) puis écriture atomique dans la
        // transaction du repository.
        $customFieldValues = $this->persistableCustomFields($cmd->type, $cmd->customFields);

        /** @var ReportData $report */
        $report = $this->transactionManager()->run(function () use ($cmd, $customFieldValues, $linkedEmails): ReportData {
            $uuid = $this->repo->create($cmd, $customFieldValues);
            $report = $this->repo->findById($uuid);
            if ($report === null) {
                throw new RuntimeException('Signalement introuvable après création.');
            }

            // Enqueue (outbox) DANS la transaction : un rollback annule signalement ET notification.
            $this->events->dispatch('report.created', ReportEventData::fromReport(
                $report,
                pdo: $this->repo->getPdo(),
            ));

            if ($linkedEmails !== []) {
                require_once __DIR__ . '/../mail.php';
                sendAgentInviteEmails($this->repo->getPdo(), $uuid, $linkedEmails);
            }

            return $report;
        }, $this->flushOutboxAfterCommit(...));

        return $report;
    }

    /**
     * @return array{status: RespondStatus, message?: string, responseId?: int}
     */
    public function respond(string $uuid, RespondToReportCommand $cmd, int $userId): array
    {
        $report = $this->repo->findById($uuid);
        if ($report === null) {
            throw new RuntimeException('Signalement introuvable.');
        }

        // AGENTS.md / NoForbiddenEnumMethodRule — tryFrom + exception contrôlée,
        // jamais de ValueError fatal sur une valeur non contrôlée.
        $respondRole = UserRole::tryFrom((string) currentUserRole());
        if ($respondRole === null) {
            throw new RuntimeException('Votre rôle de session n\'est pas reconnu.');
        }
        $userRole = $respondRole;
        $respondState = ReportState::tryFrom($report->etat);
        if ($respondState === null) {
            throw new RuntimeException('L\'état de ce signalement n\'est pas reconnu.');
        }
        $currentState = $respondState;

        // Validate transition using state machine
        $this->stateMachine->validateTransition($report, $cmd->nouvelEtat, $userRole);

        /** @var array{status: RespondStatus, message?: string, responseId?: int} $result */
        $result = $this->transactionManager()->run(function () use ($uuid, $cmd, $userId, $report): array {
            $result = ReportLifecycleRepository::instance()->respond($uuid, $cmd, $userId);

            // Audit #12 — ne pas dispatcher les events si l'opération a échoué
            // (status='concurrent' = race condition). Enqueue DANS la transaction :
            // un rollback métier annule la notification (et inversement).
            if ($result['status'] === RespondStatus::Ok) {
                $this->events->dispatch('report.responded', ReportEventData::fromReport(
                    $report,
                    userId: $userId,
                    pdo: $this->repo->getPdo(),
                    actionId: $result['responseId'] ?? null,
                ));
            }

            return $result;
        }, $this->flushOutboxAfterCommit(...));

        return $result;
    }

    /**
     * @param list<string> $inviteEmails Nouveaux e-mails d'agents à rattacher :
     *        traités DANS la transaction de modification (invite + enqueue
     *        atomiques avec l'édition).
     */
    public function update(string $uuid, UpdateReportCommand $cmd, int $userId, array $inviteEmails = []): bool
    {
        $report = $this->repo->findById($uuid);
        if ($report === null) {
            throw new RuntimeException('Signalement introuvable.');
        }
        if (!canEditReport($report, $userId)) {
            throw new RuntimeException('Accès refusé.');
        }

        // Audit #2-High — enforceVisibility was only called in create(), not update().
        // Without this, an agent could flip is_confidential=1 on an RSST public
        // (where VisibilityMode is Public) — bypassing the visibility policy.
        // Now we enforce it on update too.
        $cmd = $this->enforceVisibilityOnUpdate($cmd, $report->type);

        $this->validateCustomFields($report->type, $cmd->customFields);
        $customFieldValues = $this->persistableCustomFields($report->type, $cmd->customFields);

        return $this->transactionManager()->run(function () use ($uuid, $cmd, $userId, $report, $customFieldValues, $inviteEmails): bool {
            $result = $this->repo->update($uuid, $cmd, $userId, $report->type, $customFieldValues);

            // Audit #12 — ne pas dispatcher si l'UPDATE a échoué.
            if ($result) {
                $this->events->dispatch('report.updated', ReportEventData::fromReport(
                    $report,
                    pdo: $this->repo->getPdo(),
                ));

                if ($inviteEmails !== []) {
                    require_once __DIR__ . '/../mail.php';
                    sendAgentInviteEmails($this->repo->getPdo(), $uuid, $inviteEmails);
                }
            }

            return $result;
        }, $this->flushOutboxAfterCommit(...));
    }

    public function abandon(string $uuid, int $userId): bool
    {
        $report = $this->repo->findById($uuid);
        if ($report === null) {
            throw new RuntimeException('Signalement introuvable.');
        }

        // tryFrom — jamais ::from sur une valeur non contrôlée (AGENTS.md).
        $abandonRole = UserRole::tryFrom((string) currentUserRole());
        if ($abandonRole === null) {
            throw new RuntimeException('Votre rôle de session n\'est pas reconnu.');
        }
        $userRole = $abandonRole;
        $currentState = ReportState::tryFrom($report->etat);
        if ($currentState === null) {
            throw new RuntimeException('L\'état de ce signalement n\'est pas reconnu.');
        }

        // Validate transition using state machine
        $this->stateMachine->validateTransition($report, ReportState::Abandonne, $userRole);

        $stateHistoryId = $this->transactionManager()->run(function () use ($uuid, $userId, $report): int {
            $stateHistoryId = ReportLifecycleRepository::instance()->abandon($uuid, $userId);

            // Audit: dispatch report.abandoned so listeners can notify supervisors
            // (parallels report.reopened). Skipped on failure — no spurious email.
            // Enqueue DANS la transaction : un rollback annule la notification.
            if ($stateHistoryId > 0) {
                $this->events->dispatch('report.abandoned', ReportEventData::fromReport(
                    $report,
                    userId: $userId,
                    pdo: $this->repo->getPdo(),
                    actionId: $stateHistoryId,
                ));
            }

            return $stateHistoryId;
        }, $this->flushOutboxAfterCommit(...));

        return $stateHistoryId > 0;
    }

    public function reopen(string $uuid, ReopenReportCommand $cmd, int $userId): bool
    {
        $report = $this->repo->findById($uuid);
        if ($report === null) {
            throw new RuntimeException('Signalement introuvable.');
        }

        // tryFrom — jamais ::from sur une valeur non contrôlée (AGENTS.md).
        $reopenRole = UserRole::tryFrom((string) currentUserRole());
        if ($reopenRole === null) {
            throw new RuntimeException('Votre rôle de session n\'est pas reconnu.');
        }
        $userRole = $reopenRole;

        // Validate transition using state machine
        $this->stateMachine->validateTransition($report, ReportState::Reouvert, $userRole);

        // Audit #19 — rate limit sur les réouvertures pour éviter l'abus
        // (abandon → reopen → respond → reopen → ... en boucle). Limite
        // arbitraire de 3 réouvertures par signalement. Le nombre est
        // configurable via 'app_max_reopens_per_report' (default 3).
        $maxReopens = (int) getConfigService()->get('app_max_reopens_per_report', '3');
        if ($maxReopens > 0) {
            $reopensCount = ReportLifecycleRepository::instance()->countReopens($uuid);
            if ($reopensCount >= $maxReopens) {
                throw new RuntimeException(
                    'Ce signalement a déjà été réouvert ' . $reopensCount . ' fois. '
                    . 'Limite de ' . $maxReopens . ' réouvertures atteinte — refusez définitivement le signalement via "Abandonner" si nécessaire.'
                );
            }
        }

        $stateHistoryId = $this->transactionManager()->run(function () use ($uuid, $userId, $cmd, $report): int {
            $stateHistoryId = ReportLifecycleRepository::instance()->reopen($uuid, $userId, $cmd->motif);

            // Audit #12 — ne pas dispatcher si la réouverture a échoué.
            // Enqueue DANS la transaction : un rollback annule la notification.
            if ($stateHistoryId > 0) {
                $this->events->dispatch('report.reopened', ReportEventData::fromReport(
                    $report,
                    userId: $userId,
                    pdo: $this->repo->getPdo(),
                    motif: $cmd->motif,
                    actionId: $stateHistoryId,
                ));
            }

            return $stateHistoryId;
        }, $this->flushOutboxAfterCommit(...));

        return $stateHistoryId > 0;
    }

    private function validateForCreation(CreateReportCommand $cmd): void
    {
        $errors = validateReportFields(
            $cmd->dateEvenement,
            $cmd->objet,
            $cmd->description,
            $cmd->lieu ?? '',
            $cmd->heureEvenement ?? ''
        );
        // Modular-audit P2.1 — use RegistryPolicy instead of hardcoded type check.
        // Before: if ($cmd->type === ReportType::Rami->value) { validatePourCompte }
        // Now: any registry with requires_pour_compte=1 triggers pour_compte validation.
        $policy = new RegistryPolicy();
        if ($policy->requiresPourCompte($cmd->type)) {
            $errors = array_merge($errors, validatePourCompte(
                $cmd->pourCompteNom !== null,
                $cmd->pourCompteNom ?? '',
                $cmd->pourComptePrenom ?? ''
            ));
        }
        if (!empty($errors)) {
            throw new InvalidArgumentException(implode(', ', $errors));
        }
    }

    /**
     * Champs dynamiques — défense en profondeur (le handler valide déjà
     * depuis le POST brut). Valide les valeurs transportées par le DTO ; les
     * codes à chemin dédié (nature_auteur, etc.) n'y figurent pas et sont
     * couverts par validateForCreation / validatePourCompte.
     */
    /** @param array<string, string|null> $customFields */
    private function validateCustomFields(string $registryCode, array $customFields): void
    {
        $service = $this->customFieldsService();
        $defs = $service->getDefinitions($registryCode);
        if ($defs === []) {
            return;
        }
        $errors = $service->validateValues(
            $service->filterPersistable($customFields, $defs),
            $defs
        );
        if (!empty($errors)) {
            throw new InvalidArgumentException(implode(', ', array_values($errors)));
        }
    }

    /**
     * Valeurs dynamiques réellement persistables (codes inconnus et codes à
     * chemin dédié retirés — pas de double source de vérité).
     *
     * @param array<string, string|null> $customFields
     * @return array<string, string|null>
     */
    private function persistableCustomFields(string $registryCode, array $customFields): array
    {
        $service = $this->customFieldsService();
        $defs = $service->getDefinitions($registryCode);
        if ($defs === [] || $customFields === []) {
            return [];
        }
        return $service->filterPersistable($customFields, $defs);
    }

    private function customFieldsService(): CustomFieldsService
    {
        // Instantiation inline (pattern RegistryPolicy) — service sans état.
        return new CustomFieldsService(
            RegistryFieldRepository::instance(),
            RegistryRepository::instance(),
        );
    }

    private function enforceVisibility(CreateReportCommand $cmd): CreateReportCommand
    {
        // Modular-audit P2.3 — $cmd->type is now a string (was ReportType enum)
        $mode = getReportVisibilityMode($cmd->type);
        if ($mode === VisibilityMode::Public->value) {
            $data = array_merge($cmd->toArray(), ['type' => $cmd->type, 'isConfidential' => false]);
            $data['siteId'] = SiteId::fromInput((int) ($data['siteId'] ?? 0));
            return new CreateReportCommand(...$data);
        }
        if ($mode === VisibilityMode::Confidential->value) {
            $data = array_merge($cmd->toArray(), ['type' => $cmd->type, 'isConfidential' => true]);
            $data['siteId'] = SiteId::fromInput((int) ($data['siteId'] ?? 0));
            return new CreateReportCommand(...$data);
        }
        return $cmd;
    }

    /**
     * Audit #2-High — Apply visibility policy on update too.
     *
     * Same logic as enforceVisibility, but for UpdateReportCommand. Without this,
     * an agent could flip is_confidential on a report whose VisibilityMode is
     * 'public' or 'confidential' — bypassing the visibility policy. In AgentChoice
     * mode, the agent keeps the right to set is_confidential as they wish.
     */
    private function enforceVisibilityOnUpdate(UpdateReportCommand $cmd, string $type): UpdateReportCommand
    {
        $mode = getReportVisibilityMode($type);
        if ($mode === VisibilityMode::Public->value) {
            // Public mode: never confidential
            if ($cmd->isConfidential) {
                $data = array_merge($cmd->toArray(), ['isConfidential' => false]);
                return new UpdateReportCommand(...$data);
            }
        } elseif ($mode === VisibilityMode::Confidential->value) {
            // Confidential mode: always confidential
            if (!$cmd->isConfidential) {
                $data = array_merge($cmd->toArray(), ['isConfidential' => true]);
                return new UpdateReportCommand(...$data);
            }
        }
        // AgentChoice mode: agent decides → keep $cmd->isConfidential
        return $cmd;
    }
}
