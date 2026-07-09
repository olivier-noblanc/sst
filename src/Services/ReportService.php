<?php
/** ReportService — Couche métier pour les signalements. */

namespace App\Services;

use App\Repository\ReportRepository;
use App\Event\EventDispatcher;
use App\DTO\CreateReportCommand;
use App\DTO\UpdateReportCommand;
use App\DTO\RespondToReportCommand;
use PDO;

class ReportService
{
    public function __construct(
        private ReportRepository $repo,
        private EventDispatcher $events
    ) {}

    public function create(CreateReportCommand $cmd): array
    {
        $this->validateForCreation($cmd);
        $cmd = $this->enforceVisibility($cmd);
        $uuid = $this->repo->create($cmd);
        $report = $this->repo->findById($uuid);

        $this->events->dispatch('report.created', [
            'report' => $report,
            'cmd' => $cmd,
            'pdo' => $this->repo->getPdo(),
        ]);

        return $report;
    }

    public function respond(string $uuid, RespondToReportCommand $cmd, int $userId): array
    {
        $report = $this->repo->findById($uuid);
        if (!$report) {
            throw new \RuntimeException('Signalement introuvable.');
        }
        if (!canRespondToReport($report, currentUserRole())) {
            throw new \RuntimeException('Accès refusé.');
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
            throw new \RuntimeException('Signalement introuvable.');
        }
        if (!canEditReport($report, $userId)) {
            throw new \RuntimeException('Accès refusé.');
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
            throw new \RuntimeException('Signalement introuvable.');
        }
        if (!canRespondToReport($report, currentUserRole())) {
            throw new \RuntimeException('Accès refusé.');
        }
        return $this->repo->abandon($uuid, $userId);
    }

    public function findById(string $uuid): ?array { return $this->repo->findById($uuid); }

    private function validateForCreation(CreateReportCommand $cmd): void
    {
        $errors = validateReportFields(
            $cmd->dateEvenement, $cmd->objet, $cmd->description,
            $cmd->lieu ?? '', $cmd->heureEvenement ?? ''
        );
        if ($cmd->type === TYPE_RAMI) {
            $errors = array_merge($errors, validatePourCompte(
                $cmd->pourCompteNom !== null, $cmd->pourCompteNom ?? '', $cmd->pourComptePrenom ?? ''
            ));
        }
        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode(', ', $errors));
        }
    }

    private function enforceVisibility(CreateReportCommand $cmd): CreateReportCommand
    {
        $mode = getReportVisibilityMode($cmd->type);
        if ($mode === 'public') {
            return new CreateReportCommand(...array_merge($cmd->toArray(), ['isConfidential' => 0]));
        }
        return $cmd;
    }
}
