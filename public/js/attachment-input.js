/**
 * Attachment input — robust label activation for Edge.
 *
 * Diagnostic production (Edge) : l'input file et son label sont présents,
 * `id`/`for` correctement associés, l'input n'est ni `disabled` ni en
 * `pointer-events: none` (le label est en `pointer-events: auto`), mais aucun
 * événement click n'est détecté lors du clic physique sur le label : le clic
 * n'atteint jamais l'input et l'activation native `<label for>` échoue avec le
 * nouveau shell (empilement/positionnement CSS). Le sélecteur de fichiers ne
 * s'ouvre donc jamais.
 *
 * Ce script est un filet de sécurité explicitement non-inline (servi par
 * js.php, donc couvert par `script-src 'self'` ; aucun `onclick` inline) : un
 * listener délégué intercepte le clic sur `.file-upload-wrapper__label`,
 * neutralise l'activation native (potentiellement cassée) et déclenche
 * lui-même l'input associé via son `for` →
 * `document.getElementById(label.htmlFor).click()`.
 *
 * Le fallback natif sans JavaScript reste inchangé : le markup
 * `<input>` + `<label for>` continue de fonctionner sur les navigateurs où
 * l'activation native marche.
 */
(function () {
    'use strict';

    function forwardLabelClick(event) {
        var target = event.target;
        if (!target || typeof target.closest !== 'function') {
            return;
        }

        var label = target.closest('label.file-upload-wrapper__label');
        if (!label) {
            return;
        }

        // Un clic déjà pris en charge par un autre script garde son
        // comportement : on ne double pas l'activation.
        if (event.defaultPrevented) {
            return;
        }

        var inputId = label.htmlFor || label.getAttribute('for');
        if (!inputId) {
            return;
        }

        var input = document.getElementById(inputId);
        if (!input || input.disabled) {
            return;
        }

        // Neutralise l'activation native `<label for>` (qui échoue sous Edge
        // avec le shell actuel) puis déclenche une activation unique. Le clic
        // synthétique de l'input ne remonte pas à ce label (l'input est un
        // frère adjacent, non un descendant), donc pas de récursion.
        event.preventDefault();
        input.click();
    }

    document.addEventListener('click', forwardLabelClick, false);
})();