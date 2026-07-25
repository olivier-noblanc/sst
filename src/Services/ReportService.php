<?php

/** ReportService — Couche métier pour les signalements. */

namespace App\Services;

use App\Enum\ReportState;
use App\Enum\ReportType;
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
     * @param array<string, mixed> $user
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

        $result = $this->repo->respond($uuid, $cmd, $userId);

        $this->events->dispatch('report.responded', [
            'report' => $report->toArray(),
            'cmd' => $cmd,
            'userId' => $userId,
            'pdo' => $this->repo->getPdo(),
        ]);

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

        $result = $this->repo->update($uuid, $cmd, $userId);

        $this->events->dispatch('report.updated', [
            'report' => $report->toArray(),
            'cmd' => $cmd,
            'pdo' => $this->repo->getPdo(),
        ]);

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
        return $this->repo->abandon($uuid, $userId);
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

        $result = $this->repo->reopen($uuid, $userId, $cmd->motif);

        $this->events->dispatch('report.reopened', [
            'report' => $report->toArray(),
            'cmd'    => $cmd,
            'pdo'    => $this->repo->getPdo(),
        ]);

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
        if ($cmd->type === ReportType::Rami->value) {
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
        $mode = getReportVisibilityMode($cmd->type);
        if ($mode === VisibilityMode::Public->value) {
            $data = array_merge($cmd->toArray(), ['isConfidential' => 0]);
            /** @phpstan-var array{type: string, objet: string, description: string, dateEvenement: string, heureEvenement: string|null, lieu: string|null, declarantId: int, declarantNom: string, declarantPrenom: string, siteId: int, siteText: string|null, pole: string|null, serviceAffectation: string|null, telephoneMobile: string|null, isConfidential: int, consentSyndicat: int, natureAuteur: string|null, typeActe: string|null, pourCompteNom: string|null, pourComptePrenom: string|null, attachmentBlob: string|null, attachmentName: string|null, attachmentMime: string|null} $data */
            return new CreateReportCommand(...$data);
        }
        if ($mode === VisibilityMode::Confidential->value) {
            $data = array_merge($cmd->toArray(), ['isConfidential' => 1]);
            /** @phpstan-var array{type: string, objet: string, description: string, dateEvenement: string, heureEvenement: string|null, lieu: string|null, declarantId: int, declarantNom: string, declarantPrenom: string, siteId: int, siteText: string|null, pole: string|null, serviceAffectation: string|null, telephoneMobile: string|null, isConfidential: int, consentSyndicat: int, natureAuteur: string|null, typeActe: string|null, pourCompteNom: string|null, pourComptePrenom: string|null, attachmentBlob: string|null, attachmentName: string|null, attachmentMime: string|null} $data */
            return new CreateReportCommand(...$data);
        }
        return $cmd;
    }
}
