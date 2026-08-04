<?php

namespace App\Tests\Unit\Services;

use App\Enum\ReportState;
use App\Enum\UserRole;
use App\DTO\ReportData;
use App\Services\ReportStateMachine;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;
use RuntimeException;

/**
 * Tests pour ReportStateMachine
 *
 * @covers \App\Services\ReportStateMachine
 */
class ReportStateMachineTest extends TestCase
{
    private ReportStateMachine $stateMachine;

    protected function setUp(): void
    {
        $this->stateMachine = new ReportStateMachine();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Tests: canTransition()
    // ─────────────────────────────────────────────────────────────────────────

    public function testCanTransitionFromNouveauToEnCoursForSuperviseur(): void
    {
        $this->assertTrue(
            $this->stateMachine->canTransition(
                ReportState::Nouveau,
                ReportState::EnCours,
                UserRole::Superviseur
            )
        );
    }

    public function testCannotTransitionFromNouveauToEnCoursForAgent(): void
    {
        $this->assertFalse(
            $this->stateMachine->canTransition(
                ReportState::Nouveau,
                ReportState::EnCours,
                UserRole::Agent
            )
        );
    }

    public function testCanTransitionFromNouveauToAbandonneForAgent(): void
    {
        $this->assertTrue(
            $this->stateMachine->canTransition(
                ReportState::Nouveau,
                ReportState::Abandonne,
                UserRole::Agent
            )
        );
    }

    public function testCannotTransitionFromNouveauToTraiteForAgent(): void
    {
        $this->assertFalse(
            $this->stateMachine->canTransition(
                ReportState::Nouveau,
                ReportState::Traite,
                UserRole::Agent
            )
        );
    }

    public function testCanTransitionFromTraiteToReouvertForChsct(): void
    {
        $this->assertTrue(
            $this->stateMachine->canTransition(
                ReportState::Traite,
                ReportState::Reouvert,
                UserRole::Chsct
            )
        );
    }

    public function testCanTransitionFromTraiteToReouvertForSuperviseur(): void
    {
        $this->assertTrue(
            $this->stateMachine->canTransition(
                ReportState::Traite,
                ReportState::Reouvert,
                UserRole::Superviseur
            )
        );
    }

    public function testCannotTransitionFromAbandonneToEnCours(): void
    {
        // Depuis Abandonné, on ne peut aller qu'à Réouvert
        $this->assertFalse(
            $this->stateMachine->canTransition(
                ReportState::Abandonne,
                ReportState::EnCours,
                UserRole::Superviseur
            )
        );
    }

    public function testInvalidTransitionReturnsFalse(): void
    {
        // Transition qui n'existe pas dans la matrice
        $this->assertFalse(
            $this->stateMachine->canTransition(
                ReportState::Nouveau,
                ReportState::Nouveau, // rester dans le même état n'est pas une transition valide
                UserRole::Superviseur
            )
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Tests: getAvailableTransitions()
    // ─────────────────────────────────────────────────────────────────────────

    public function testGetAvailableTransitionsForAgentFromNouveau(): void
    {
        $transitions = $this->stateMachine->getAvailableTransitions(
            ReportState::Nouveau,
            UserRole::Agent
        );

        $this->assertCount(1, $transitions);
        $this->assertContains(ReportState::Abandonne, $transitions);
    }

    public function testGetAvailableTransitionsForSuperviseurFromNouveau(): void
    {
        $transitions = $this->stateMachine->getAvailableTransitions(
            ReportState::Nouveau,
            UserRole::Superviseur
        );

        $this->assertCount(2, $transitions);
        $this->assertContains(ReportState::EnCours, $transitions);
        $this->assertContains(ReportState::Traite, $transitions);
    }

    public function testGetAvailableTransitionsForSuperviseurFromTraite(): void
    {
        $transitions = $this->stateMachine->getAvailableTransitions(
            ReportState::Traite,
            UserRole::Superviseur
        );

        // Superviseur peut seulement réouvrir depuis Traite (pas abandonner)
        $this->assertCount(1, $transitions);
        $this->assertContains(ReportState::Reouvert, $transitions);
    }

    public function testGetAvailableTransitionsEmptyForInvalidState(): void
    {
        // État qui n'a pas de transitions sortantes (ex: certains états finaux)
        // En l'occurrence, tous nos états ont des transitions, mais testons un cas limite
        $transitions = $this->stateMachine->getAvailableTransitions(
            ReportState::Abandonne,
            UserRole::Agent
        );

        $this->assertCount(0, $transitions);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Tests: validateTransition()
    // ─────────────────────────────────────────────────────────────────────────

    public function testValidateTransitionSuccess(): void
    {
        $report = $this->createReport(ReportState::Nouveau);

        // Ne doit pas lever d'exception
        $this->stateMachine->validateTransition(
            $report,
            ReportState::EnCours,
            UserRole::Superviseur
        );

        $this->expectNotToPerformAssertions();
    }

    public function testValidateTransitionThrowsOnInvalidTransition(): void
    {
        $report = $this->createReport(ReportState::Nouveau);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Transition invalide');

        $this->stateMachine->validateTransition(
            $report,
            ReportState::Nouveau, // rester dans le même état
            UserRole::Superviseur
        );
    }

    public function testValidateTransitionThrowsOnUnauthorizedRole(): void
    {
        $report = $this->createReport(ReportState::Nouveau);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Accès refusé');

        $this->stateMachine->validateTransition(
            $report,
            ReportState::EnCours,
            UserRole::Agent // Agent ne peut pas passer à EnCours
        );
    }

    public function testValidateTransitionWithReouvert(): void
    {
        $report = $this->createReport(ReportState::Traite);

        // CHSCT peut réouvrir
        $this->stateMachine->validateTransition(
            $report,
            ReportState::Reouvert,
            UserRole::Chsct
        );

        $this->expectNotToPerformAssertions();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Tests: getTransitionDescription()
    // ─────────────────────────────────────────────────────────────────────────

    public function testGetTransitionDescription(): void
    {
        $description = $this->stateMachine->getTransitionDescription(
            ReportState::Nouveau,
            UserRole::Superviseur
        );

        $this->assertStringContainsString('Nouveau', $description);
        $this->assertStringContainsString('En cours', $description);
        $this->assertStringContainsString('Traité', $description);
    }

    public function testGetTransitionDescriptionNoTransitions(): void
    {
        $description = $this->stateMachine->getTransitionDescription(
            ReportState::Abandonne,
            UserRole::Agent
        );

        $this->assertStringContainsString('Aucune transition', $description);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Matrice complète des transitions
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Test exhaustif de toute la matrice des transitions.
     */
    public function testFullTransitionMatrix(): void
    {
        // Matrice attendue : [from => [to => [allowedRoles]]]
        $expectedTransitions = [
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

        $allRoles = [UserRole::Agent, UserRole::Superviseur, UserRole::Chsct];

        foreach (ReportState::cases() as $fromState) {
            foreach (ReportState::cases() as $toState) {
                foreach ($allRoles as $role) {
                    $expected = false;
                    $fromValue = $fromState->value;
                    $toValue = $toState->value;

                    if (isset($expectedTransitions[$fromValue][$toValue])) {
                        $allowedRoles = $expectedTransitions[$fromValue][$toValue];
                        foreach ($allowedRoles as $allowedRole) {
                            if ($allowedRole === $role) {
                                $expected = true;
                                break;
                            }
                        }
                    }

                    $actual = $this->stateMachine->canTransition($fromState, $toState, $role);

                    $this->assertSame(
                        $expected,
                        $actual,
                        sprintf(
                            'Transition %s -> %s pour %s : attendu %s, obtenu %s',
                            $fromState->label(),
                            $toState->label(),
                            $role->defaultLabel(),
                            $expected ? 'true' : 'false',
                            $actual ? 'true' : 'false'
                        )
                    );
                }
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function createReport(ReportState $state): ReportData
    {
        return new ReportData(
            uuid: 'test-uuid',
            reference: 'TEST-001',
            etat: $state->value,
            type: 'rsst',
            objet: 'Test',
            description: 'Test description',
            dateEvenement: '2026-01-01',
            heureEvenement: '10:00',
            lieu: 'Test location',
            declarantId: 1,
            declarantNom: 'Test',
            declarantPrenom: 'User',
            pourCompteDe: '',
            pourCompteNom: '',
            pourComptePrenom: '',
            natureAuteur: '',
            typeActe: '',
            siteId: 1,
            siteText: 'Test Site',
            pole: '',
            serviceAffectation: '',
            telephoneMobile: '',
            isConfidential: 0,
            consentSyndicat: 0,
            repondantId: null,
            dateReponse: null,
            reponse: null,
            attachmentName: null,
            attachmentMime: null,
            createdAt: '2026-01-01 10:00:00',
            updatedAt: '2026-01-01 10:00:00',
            siteCode: 'TEST',
            siteNom: 'Test Site',
            repondantNom: null,
            repondantPrenom: null,
        );
    }
}