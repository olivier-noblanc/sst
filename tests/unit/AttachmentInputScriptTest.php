<?php

declare(strict_types=1);

/**
 * AttachmentInputScriptTest — robustesse du bouton « Pièce jointe » (Edge).
 *
 * Diagnostic production : sur Edge, le clic physique sur
 * `.file-upload-wrapper__label` n'ouvre pas le sélecteur de fichiers, alors
 * que l'input et le label sont correctement associés (`id`/`for`), l'input non
 * `disabled` et le label en `pointer-events: auto`. L'activation native
 * `<label for>` échoue avec le nouveau shell ; un listener JS délégué
 * non-inline (`public/js/attachment-input.js`, servi par `js.php`) prend le
 * relais.
 *
 * Ce contrat verrouille : présence du listener délégué, forward
 * `document.getElementById(...).click()`, chargement via `js.php` dans le
 * footer, et présence de la classe cible dans les deux formulaires
 * (`attachment` et `response_attachment`).
 */

use PHPUnit\Framework\TestCase;

final class AttachmentInputScriptTest extends TestCase
{
    private function script(): string
    {
        $script = file_get_contents(__DIR__ . '/../../public/js/attachment-input.js');
        $this->assertIsString($script, 'public/js/attachment-input.js introuvable.');

        return $script;
    }

    public function testScriptExposesDelegatedLabelClickForwarding(): void
    {
        $script = $this->script();

        $this->assertStringContainsString(
            'label.file-upload-wrapper__label',
            $script,
            'Le script doit cibler le label du bouton de pièce jointe.'
        );
        $this->assertMatchesRegularExpression(
            '/addEventListener\s*\(\s*[\'"]click[\'"]/',
            $script,
            'Le script doit poser un listener click délégué.'
        );
        $this->assertMatchesRegularExpression(
            '/document\.getElementById\s*\(/',
            $script,
            'Le script doit retrouver l\'input associé via document.getElementById().'
        );
        $this->assertMatchesRegularExpression(
            '/\.click\s*\(\s*\)/',
            $script,
            'Le script doit déclencher l\'input associé via .click().'
        );
    }

    public function testFooterLoadsScriptThroughJsPhpWithVersion(): void
    {
        $footer = file_get_contents(__DIR__ . '/../../templates/footer.php');
        $this->assertIsString($footer, 'templates/footer.php introuvable.');

        $this->assertStringContainsString(
            'js.php?f=js/attachment-input.js&amp;v=',
            $footer,
            'Le footer doit charger le script via js.php (couvert par script-src \'self\').'
        );
        $this->assertStringContainsString(
            'getAppVersion()',
            $footer,
            'L\'URL js.php doit porter le `v=` versionné pour l\'invalidation de cache.'
        );
    }

    public function testBothAttachmentFormsCarryTheTargetLabelClass(): void
    {
        foreach (['templates/report_form.php', 'pages/report_respond.php'] as $file) {
            $markup = file_get_contents(__DIR__ . '/../../' . $file);
            $this->assertIsString($markup, sprintf('%s introuvable.', $file));
            $this->assertStringContainsString(
                'class="file-upload-wrapper__label',
                $markup,
                sprintf('%s : la classe ciblée par le listener doit être présente.', $file)
            );
        }
    }
}