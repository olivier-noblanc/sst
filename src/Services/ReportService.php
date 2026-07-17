<?php

/** ReportService — Couche métier pour les signalements. */

namespace App\Services;

use RuntimeException;
use InvalidArgumentException;
use App\Repository\ReportRepository;
use App\Event\EventDispatcher;
use App\DTO\CreateReportCommand;
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
     * @return array<string, mixed>
     */
    public function create(CreateReportCommand $cmd): array
    {
        $this->validateForCreation($cmd);
        $cmd = $this->enforceVisibility($cmd);
        $uuid = $this->repo->create($cmd);
        $report = $this->repo->findById($uuid);
        /** @var array<string, mixed> $report */

        $this->events->dispatch('report.created', [
            'report' => $report,
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
        if (!$report) {
            throw new RuntimeException('Signalement introuvable.');
        }
        /** @var array<string, mixed> $report */
        if (!canRespondToReport($report, currentUserRole())) {
            throw new RuntimeException('Accès refusé.');
        }

        $result = $this->repo->respond($uuid, $cmd, $userId);

        $this->events->dispatch('report.responded', [
            'report' => $report,
            'cmd' => $cmd,
            'userId' => $userId,
            'pdo' => $this->repo->getPdo(),
        ]);

        return $result;
    }

    public function update(string $uuid, UpdateReportCommand $cmd, int $userId): bool
    {
        $report = $this->repo->findById($uuid);
        if (!$report) {
            throw new RuntimeException('Signalement introuvable.');
        }
        /** @var array<string, mixed> $report */
        if (!canEditReport($report, $userId)) {
            throw new RuntimeException('Accès refusé.');
        }

        $result = $this->repo->update($uuid, $cmd, $userId);

        $this->events->dispatch('report.updated', [
            'report' => $report,
            'cmd' => $cmd,
            'pdo' => $this->repo->getPdo(),
        ]);

        return $result;
    }

    public function abandon(string $uuid, int $userId): bool
    {
        $report = $this->repo->findById($uuid);
        if (!$report) {
            throw new RuntimeException('Signalement introuvable.');
        }
        /** @var array<string, mixed> $report */
        if (!canEditReport($report, $userId)) {
            throw new RuntimeException('Accès refusé.');
        }
        return $this->repo->abandon($uuid, $userId);
    }

    public function reopen(string $uuid, ReopenReportCommand $cmd, int $userId): bool
    {
        $report = $this->repo->findById($uuid);
        if (!$report) {
            throw new RuntimeException('Signalement introuvable.');
        }
        /** @var array<string, mixed> $report */
        if (!in_array($report['etat'], [ETAT_TRAITE, ETAT_ABANDONNE])) {
            throw new RuntimeException('Ce signalement ne peut pas être réouvert.');
        }
        if (!in_array(currentUserRole(), [ROLE_SUPERVISEUR, ROLE_CHSCT])) {
            throw new RuntimeException('Accès refusé — seuls les superviseurs et le CHSCT peuvent réouvrir.');
        }

        $result = $this->repo->reopen($uuid, $userId, $cmd->motif);

        $this->events->dispatch('report.reopened', [
            'report' => $report,
            'cmd'    => $cmd,
            'pdo'    => $this->repo->getPdo(),
        ]);

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(string $uuid): ?array
    {
        $result = $this->repo->findById($uuid);
        /** @var array<string, mixed>|null $result */
        return $result;
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
        if ($cmd->type === TYPE_RAMI) {
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
        if ($mode === 'public') {
            $data = array_merge($cmd->toArray(), ['isConfidential' => 0]);
            /** @phpstan-var array{type: string, objet: string, description: string, dateEvenement: string, heureEvenement: string|null, lieu: string|null, declarantId: int, declarantNom: string, declarantPrenom: string, siteId: int, siteText: string|null, pole: string|null, serviceAffectation: string|null, telephoneMobile: string|null, isConfidential: int, consentSyndicat: int, natureAuteur: string|null, typeActe: string|null, pourCompteNom: string|null, pourComptePrenom: string|null, attachmentBlob: string|null, attachmentName: string|null, attachmentMime: string|null} $data */
            return new CreateReportCommand(...$data);
        }
        if ($mode === 'confidential') {
            $data = array_merge($cmd->toArray(), ['isConfidential' => 1]);
            /** @phpstan-var array{type: string, objet: string, description: string, dateEvenement: string, heureEvenement: string|null, lieu: string|null, declarantId: int, declarantNom: string, declarantPrenom: string, siteId: int, siteText: string|null, pole: string|null, serviceAffectation: string|null, telephoneMobile: string|null, isConfidential: int, consentSyndicat: int, natureAuteur: string|null, typeActe: string|null, pourCompteNom: string|null, pourComptePrenom: string|null, attachmentBlob: string|null, attachmentName: string|null, attachmentMime: string|null} $data */
            return new CreateReportCommand(...$data);
        }
        return $cmd;
    }
}
