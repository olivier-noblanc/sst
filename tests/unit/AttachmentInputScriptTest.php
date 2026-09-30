<?php

declare(strict_types=1);

/**
 * AttachmentInputScriptTest — contrat du champ « Pièce jointe » (input fichier
 * HTML5 natif visible).
 *
 * Le faux bouton label/JS a été retiré : `public/js/attachment-input.js` ne
 * sert plus qu'à afficher le nom du fichier choisi et à câbler le bouton local
 * « Annuler la sélection » (reset ciblé `input.value = ''`). Aucun
 * `preventDefault()`, aucun `input.click()` forcé, aucune interception du clic
 * sur un label.
 *
 * Ce contrat verrouille : le contenu du script, son chargement via `js.php`
 * dans le footer, le rendu du composant partagé (input visible, `accept`,
 * association label/input) et l'absence de `<script>` inline dans les deux
 * formulaires.
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

    public function testScriptWiresFilenameDisplayAndTargetedReset(): void
    {
        $script = $this->script();

        $this->assertMatchesRegularExpression(
            '/addEventListener\s*\(\s*[\'"]change[\'"]/',
            $script,
            'Le script doit mettre à jour le nom affiché au changement de fichier.'
        );
        $this->assertStringContainsString(
            '.file-upload-wrapper__filename',
            $script,
            'Le script doit cibler l\'affichage du nom du fichier.'
        );
        $this->assertStringContainsString(
            "input.value = ''",
            $script,
            'Le bouton de reset doit vider uniquement la valeur de l\'input file.'
        );
        $this->assertStringContainsString(
            '.file-upload-wrapper__reset',
            $script,
            'Le script doit câbler le bouton local « Annuler la sélection ».'
        );
    }

    public function testScriptNeverHijacksTheNativeControl(): void
    {
        $script = $this->script();

        $this->assertDoesNotMatchRegularExpression(
            '/\.preventDefault\s*\(/',
            $script,
            'Aucune neutralisation d\'événement ne doit subsister.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.click\s*\(\s*\)/',
            $script,
            'Le contrôle natif ne doit plus être ouvert par script (aucun .click() forcé).'
        );
        $this->assertStringNotContainsString(
            'file-upload-wrapper__label',
            $script,
            'Le faux bouton label ne doit plus être ciblé.'
        );
    }

    public function testScriptDoesNotTouchTheStoredAttachmentRemovalCheckbox(): void
    {
        $script = $this->script();

        $this->assertStringNotContainsString(
            'remove_attachment',
            $script,
            'L\'annulation d\'une nouvelle sélection ne doit jamais toucher à la pièce jointe stockée.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/form\.reset\s*\(/',
            $script,
            'Le reset doit rester ciblé sur l\'input file (aucun form.reset()).'
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

    public function testSharedComponentRendersAVisibleNativeFileInput(): void
    {
        $component = file_get_contents(__DIR__ . '/../../templates/attachment_field.php');
        $this->assertIsString($component, 'templates/attachment_field.php introuvable.');

        $this->assertStringContainsString(
            'type="file"',
            $component,
            'Le composant doit rendre un <input type="file">.'
        );
        $this->assertStringContainsString(
            'class="file-upload-wrapper__input"',
            $component,
            'Le champ fichier doit porter la classe du composant partagé.'
        );
        $this->assertStringContainsString(
            'accept=".jpg,.jpeg,.png,.gif,.pdf"',
            $component,
            'Le composant doit déclarer les extensions acceptées.'
        );
        $this->assertStringContainsString(
            'class="file-upload-wrapper__filename"',
            $component,
            'Le composant doit afficher le nom du fichier choisi.'
        );
        $this->assertStringContainsString(
            'file-upload-wrapper__reset',
            $component,
            'Le composant doit contenir le bouton « Annuler la sélection ».'
        );
        $this->assertStringContainsString(
            'Annuler la sélection',
            $component,
            'Le bouton de reset doit porter le libellé « Annuler la sélection ».'
        );
        $this->assertStringContainsString(
            '<label for="<?php echo e($attachmentInputId); ?>">',
            $component,
            'Le libellé du champ doit rester associé à l\'input par son `for`.'
        );
        $this->assertStringContainsString(
            'id="<?php echo e($attachmentInputId); ?>"',
            $component,
            'L\'input doit porter un `id` issu de la même variable que le `for` du label.'
        );
    }

    public function testBothFormsUseTheSharedComponentWithoutInlineScript(): void
    {
        foreach (['templates/report_form.php', 'pages/report_respond.php'] as $file) {
            $markup = file_get_contents(__DIR__ . '/../../' . $file);
            $this->assertIsString($markup, sprintf('%s introuvable.', $file));
            $this->assertStringContainsString(
                'attachment_field.php',
                $markup,
                sprintf('%s : le composant partagé doit être requis.', $file)
            );
            $this->assertDoesNotMatchRegularExpression(
                '/<script[\s>]/i',
                $markup,
                sprintf('%s : plus aucun script inline ne doit subsister.', $file)
            );
        }
    }
}
