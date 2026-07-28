<?php

/** ReportService — Couche métier pour les signalements. */

namespace App\Services;

use App\Enum\ReportState;
use App\Enum\UserRole;
use App\Enum\VisibilityMode;
use RuntimeException;
use InvalidArgumentException;
use App\Repository\ReportRepository;
use App\Event\EventDispatcher;
use App\DTO\CreateReportCommand;
use App\DTO\ReportData;
use App\DTO\UpdateReportCommand;
use App\DTO\RespondToReportCommand;
use App\DTO\ReopenReportCommand;

class ReportService
{
    public function __construct(
        private readonly ReportRepository $repo,
        private readonly EventDispatcher $events
    ) {}

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

    public function create(CreateReportCommand $cmd): ReportData
    {
        $this->validateForCreation($cmd);
        $cmd = $this->enforceVisibility($cmd);
        $uuid = $this->repo->create($cmd);
        $report = $this->repo->findById($uuid);
        if ($report === null) {
            throw new RuntimeException('Signalement introuvable après création.');
        }

        $this->events->dispatch('report.created', [
            'report' => $report->toArray(),
            'cmd' => $cmd,
            'pdo' => $this->repo->getPdo(),
        ]);

        return $report;
    }

    /**
     * @return array{status: string, message?: string}
     */
    public function respond(string $uuid, RespondToReportCommand $cmd, int $userId): array
    {
        $report = $this->repo->findById($uuid);
        if ($report === null) {
            throw new RuntimeException('Signalement introuvable.');
        }
        if (!canRespondToReport($report->toArray(), currentUserRole())) {
            throw new RuntimeException('Accès refusé.');
        }

        // Audit #6-Medium — validate that nouvelEtat is a valid response state.
        // Before this fix, a supervisor could pass ReportState::Abandonne (via
        // crafted POST) → bypassing the rule that abandon is declarant-only
        // (cf. abandon() method). Only EnCours and Traite are valid response
        // states — Nouveau is the initial state (no response yet), Reouvert and
        // Abandonne are managed by their own methods.
        $validResponseStates = [ReportState::EnCours, ReportState::Traite];
        if (!in_array($cmd->nouvelEtat, $validResponseStates, true)) {
            throw new InvalidArgumentException(
                'État cible invalide pour une réponse. Seuls "en_cours" et "traite" sont autorisés. '
                . 'Pour abandonner, utilisez le bouton "Abandonner" (réservé au déclarant).'
            );
        }

        $result = $this->repo->respond($uuid, $cmd, $userId);

        // Audit #12 — ne pas dispatcher les events si l'opération a échoué
        // (status='concurrent' = race condition : un autre superviseur a déjà
        // répondu). Avant ce fix, l'event 'report.responded' était dispatché
        // même en cas d'échec → ghost events → notifications email mentant
        // sur l'état réel du signalement.
        if (($result['status'] ?? '') === 'success') {
            $this->events->dispatch('report.responded', [
                'report' => $report->toArray(),
                'cmd' => $cmd,
                'userId' => $userId,
                'pdo' => $this->repo->getPdo(),
            ]);
        }

        return $result;
    }

    public function update(string $uuid, UpdateReportCommand $cmd, int $userId): bool
    {
        $report = $this->repo->findById($uuid);
        if ($report === null) {
            throw new RuntimeException('Signalement introuvable.');
        }
        if (!canEditReport($report->toArray(), $userId)) {
            throw new RuntimeException('Accès refusé.');
        }

        // Audit #2-High — enforceVisibility was only called in create(), not update().
        // Without this, an agent could flip is_confidential=1 on an RSST public
        // (where VisibilityMode is Public) — bypassing the visibility policy.
        // Now we enforce it on update too.
        $cmd = $this->enforceVisibilityOnUpdate($cmd, $report->type);

        $result = $this->repo->update($uuid, $cmd, $userId);

        // Audit #12 — ne pas dispatcher si l'UPDATE a échoué.
        if ($result) {
            $this->events->dispatch('report.updated', [
                'report' => $report->toArray(),
                'cmd' => $cmd,
                'pdo' => $this->repo->getPdo(),
            ]);
        }

        return $result;
    }

    public function abandon(string $uuid, int $userId): bool
    {
        $report = $this->repo->findById($uuid);
        if ($report === null) {
            throw new RuntimeException('Signalement introuvable.');
        }
        if (!canEditReport($report->toArray(), $userId)) {
            throw new RuntimeException('Accès refusé.');
        }
        $result = $this->repo->abandon($uuid, $userId);

        // Audit: dispatch report.abandoned so listeners can notify supervisors
        // (parallels report.reopened). Skipped on failure — no spurious email.
        if ($result) {
            $this->events->dispatch('report.abandoned', [
                'report' => $report->toArray(),
                'userId' => $userId,
                'pdo'    => $this->repo->getPdo(),
            ]);
        }

        return $result;
    }

    public function reopen(string $uuid, ReopenReportCommand $cmd, int $userId): bool
    {
        $report = $this->repo->findById($uuid);
        if ($report === null) {
            throw new RuntimeException('Signalement introuvable.');
        }
        if (!in_array($report->etat, [ReportState::Traite->value, ReportState::Abandonne->value], true)) {
            throw new RuntimeException('Ce signalement ne peut pas être réouvert.');
        }
        if (!in_array(currentUserRole(), [UserRole::Superviseur->value, UserRole::Chsct->value], true)) {
            throw new RuntimeException('Accès refusé — seuls les superviseurs et le CHSCT peuvent réouvrir.');
        }

        // Audit #19 — rate limit sur les réouvertures pour éviter l'abus
        // (abandon → reopen → respond → reopen → ... en boucle). Limite
        // arbitraire de 3 réouvertures par signalement. Le nombre est
        // configurable via 'app_max_reopens_per_report' (default 3).
        $maxReopens = (int) getConfigService()->get('app_max_reopens_per_report', '3');
        if ($maxReopens > 0) {
            $reopensCount = $this->repo->countReopens($uuid);
            if ($reopensCount >= $maxReopens) {
                throw new RuntimeException(
                    'Ce signalement a déjà été réouvert ' . $reopensCount . ' fois. '
                    . 'Limite de ' . $maxReopens . ' réouvertures atteinte — refusez définitivement le signalement via "Abandonner" si nécessaire.'
                );
            }
        }

        $result = $this->repo->reopen($uuid, $userId, $cmd->motif);

        // Audit #12 — ne pas dispatcher si la réouverture a échoué.
        if ($result) {
            $this->events->dispatch('report.reopened', [
                'report' => $report->toArray(),
                'cmd'    => $cmd,
                'pdo'    => $this->repo->getPdo(),
            ]);
        }

        return $result;
    }

    public function findById(string $uuid): ?ReportData
    {
        return $this->repo->findById($uuid);
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

    private function enforceVisibility(CreateReportCommand $cmd): CreateReportCommand
    {
        // Modular-audit P2.3 — $cmd->type is now a string (was ReportType enum)
        $mode = getReportVisibilityMode($cmd->type);
        if ($mode === VisibilityMode::Public->value) {
            $data = array_merge($cmd->toArray(), ['type' => $cmd->type, 'isConfidential' => false]);
            return new CreateReportCommand(...$data);
        }
        if ($mode === VisibilityMode::Confidential->value) {
            $data = array_merge($cmd->toArray(), ['type' => $cmd->type, 'isConfidential' => true]);
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
