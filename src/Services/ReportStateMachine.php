<?php

/**
 * ReportStateMachine — Centralise les transitions d'état des signalements.
 *
 * Un seul point de vérité pour :
 * - Quelles transitions sont valides (depuis/vers)
 * - Qui peut effectuer chaque transition (rôles)
 * - Validation des transitions avec messages d'erreur clairs
 */

namespace App\Services;

use App\Enum\ReportState;
use App\Enum\UserRole;
use App\DTO\ReportData;
use InvalidArgumentException;
use RuntimeException;

final class ReportStateMachine
{
    /**
     * Matrice des transitions valides.
     *
     * Structure : [état_source_value => [état_cible_value => [rôles autorisés]]]
     *
     * Exemple : 'nouveau' => ['en_cours' => [UserRole::Superviseur]]
     * signifie : "Depuis Nouveau, on peut aller vers EnCours, réservé aux Superviseurs"
     */
    private const array TRANSITIONS = [
        ReportState::Nouveau->value => [
            ReportState::EnCours->value => [UserRole::Superviseur],
            ReportState::Traite->value => [UserRole::Superviseur],
            ReportState::Abandonne->value => [UserRole::Agent],
        ],
        ReportState::EnCours->value => [
            ReportState::Traite->value => [UserRole::Superviseur],
            ReportState::Abandonne->value => [UserRole::Agent],
        ],
        ReportState::Traite->value => [
            ReportState::Reouvert->value => [UserRole::Superviseur, UserRole::Chsct],
            ReportState::Abandonne->value => [UserRole::Agent],
        ],
        ReportState::Reouvert->value => [
            ReportState::EnCours->value => [UserRole::Superviseur],
            ReportState::Traite->value => [UserRole::Superviseur],
            ReportState::Abandonne->value => [UserRole::Agent],
        ],
        ReportState::Abandonne->value => [
            ReportState::Reouvert->value => [UserRole::Superviseur, UserRole::Chsct],
        ],
    ];

    /**
     * Vérifie si une transition est valide pour un rôle donné.
     */
    public function canTransition(ReportState $from, ReportState $to, UserRole $role): bool
    {
        $transitions = self::TRANSITIONS[$from->value];
        $allowedRoles = $transitions[$to->value] ?? [];
        return in_array($role, $allowedRoles, true);
    }

    /**
     * Retourne les transitions disponibles depuis un état donné pour un rôle.
     *
     * @return list<ReportState>
     */
    public function getAvailableTransitions(ReportState $from, UserRole $role): array
    {
        $transitions = self::TRANSITIONS[$from->value];

        $available = [];
        foreach ($transitions as $toValue => $allowedRoles) {
            if (in_array($role, $allowedRoles, true)) {
                // Valeur interne de la matrice (toujours un ReportState connu) —
                // PHPStan infère le non-null depuis la constante typée.
                $available[] = ReportState::tryFrom($toValue);
            }
        }

        return $available;
    }

    /**
     * Valide une transition et lance une exception si invalide.
     *
     * @throws InvalidArgumentException si la transition n'existe pas
     * @throws RuntimeException si le rôle n'est pas autorisé
     */
    public function validateTransition(ReportData $report, ReportState $newState, UserRole $userRole): void
    {
        // tryFrom — un état DB inconnu (hors CHECK constraint) doit produire
        // une exception métier claire, jamais une ValueError fatale (AGENTS.md).
        $currentState = ReportState::tryFrom($report->etat);
        if ($currentState === null) {
            throw new InvalidArgumentException(
                sprintf('L\'état du signalement "%s" n\'est pas reconnu.', $report->etat)
            );
        }

        // Transition invalide (n'existe pas dans la matrice)
        if (!$this->transitionExists($currentState, $newState)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Transition invalide : impossible de passer de "%s" à "%s".',
                    $currentState->label(),
                    $newState->label()
                )
            );
        }

        // Rôle non autorisé
        if (!$this->canTransition($currentState, $newState, $userRole)) {
            throw new RuntimeException(
                sprintf(
                    'Accès refusé : la transition de "%s" à "%s" n\'est pas autorisée pour le rôle "%s".',
                    $currentState->label(),
                    $newState->label(),
                    $userRole->defaultLabel()
                )
            );
        }
    }

    /**
     * Vérifie si une transition existe dans la matrice (indépendamment du rôle).
     */
    private function transitionExists(ReportState $from, ReportState $to): bool
    {
        return isset(self::TRANSITIONS[$from->value][$to->value]);
    }

    /**
     * Retourne une description lisible des transitions autorisées pour un état/rôle.
     * Utile pour les messages d'erreur ou l'UI.
     *
     * @return string Ex: "Depuis Nouveau, vous pouvez passer à : En cours, Traité"
     */
    public function getTransitionDescription(ReportState $from, UserRole $role): string
    {
        $available = $this->getAvailableTransitions($from, $role);

        if (empty($available)) {
            return sprintf(
                'Aucune transition disponible depuis "%s" pour votre rôle.',
                $from->label()
            );
        }

        $labels = array_map(fn(ReportState $s) => $s->label(), $available);
        $list = implode(', ', $labels);

        return sprintf(
            'Depuis "%s", vous pouvez passer à : %s',
            $from->label(),
            $list
        );
    }
}
