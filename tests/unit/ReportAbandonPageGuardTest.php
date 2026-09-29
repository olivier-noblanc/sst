<?php
/**
 * Report Abandon Page Guard — cohérence page GET ↔ matrice ReportStateMachine.
 *
 * Bug (audit lifecycle) : pages/report_abandon.php utilisait
 * requireReportEditable ([Nouveau, EnCours]) alors que le handler
 * (report_abandon_handler.php) ET la matrice ReportStateMachine autorisent
 * Nouveau/EnCours/Traite/Reouvert → Abandonne pour le rôle Agent. Un agent
 * déclarant ne pouvait donc pas abandonner un signalement Traité ou Réouvert
 * depuis la page de confirmation, alors que le bouton et le handler
 * l'autorisaient.
 *
 * La matrice (ReportStateMachine) est l'autorité : la page GET doit s'y
 * aligner, pas dupliquer une liste d'états en dur.
 */

use PHPUnit\Framework\TestCase;
use App\Enum\ReportState;
use App\Enum\UserRole;
use App\Services\ReportStateMachine;

class ReportAbandonPageGuardTest extends TestCase
{
    private function source(string $rel): string
    {
        $content = file_get_contents(__DIR__ . '/../../' . $rel);
        $this->assertNotFalse($content, 'Fichier source introuvable : ' . $rel);
        return (string) $content;
    }

    public function testPageUsesStateMachineAndOwnershipInsteadOfRequireReportEditable(): void
    {
        $src = $this->source('pages/report_abandon.php');
        $this->assertStringContainsString('ReportStateMachine', $src, 'La page doit s\'appuyer sur la matrice');
        $this->assertStringContainsString('canTransition', $src, 'La page doit interroger canTransition()');
        $this->assertStringContainsString('requireReportOwnership', $src, 'L\'ownership du déclarant reste requis');
        $this->assertDoesNotMatchRegularExpression(
            '/^\s*requireReportEditable\s*\(/m',
            $src,
            'report_abandon.php ne doit plus utiliser requireReportEditable ([Nouveau, EnCours]) : '
            . 'la matrice autorise aussi Traite/Reouvert → Abandonne pour le rôle Agent.'
        );
    }

    public function testMatrixAllowsAbandonFromEveryNonFinalStateForAgent(): void
    {
        $sm = new ReportStateMachine();
        foreach ([ReportState::Nouveau, ReportState::EnCours, ReportState::Traite, ReportState::Reouvert] as $from) {
            $this->assertTrue(
                $sm->canTransition($from, ReportState::Abandonne, UserRole::Agent),
                sprintf('La matrice doit autoriser %s → Abandonne pour le rôle Agent', $from->value)
            );
        }
        $this->assertFalse(
            $sm->canTransition(ReportState::Abandonne, ReportState::Abandonne, UserRole::Agent),
            'Abandonne → Abandonne n\'existe pas dans la matrice : doit rester refusé.'
        );
    }
}