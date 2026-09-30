/**
 * Attachment field — affichage du nom du fichier choisi et bouton local
 * « Annuler la sélection ».
 *
 * Le contrôle est un `<input type="file">` HTML5 **visible** : il n'y a plus
 * aucun faux bouton label à assister, ni neutralisation d'événement, ni
 * ouverture programmée du sélecteur. Le navigateur fournit nativement le bouton
 * de sélection, et la soumission du formulaire fonctionne sans JavaScript.
 *
 * Ce script (servi par `js.php`, donc couvert par `script-src 'self'`, sans
 * gestionnaire inline) ajoute uniquement :
 *   — la mise à jour du nom affiché (`.file-upload-wrapper__filename`) ;
 *   — l'activation du bouton « Annuler la sélection », qui remet
 *     `input.value = ''` sans jamais toucher au reste du formulaire.
 *
 * L'annulation d'une NOUVELLE sélection ne supprime jamais une pièce jointe
 * déjà stockée : le script ne concentre son action que sur la nouvelle
 * sélection et ne modifie aucune case de suppression serveur.
 */
(function () {
    'use strict';

    function refresh(wrapper) {
        var input = wrapper.querySelector('input[type="file"]');
        if (!input) {
            return;
        }

        var nameEl = wrapper.querySelector('.file-upload-wrapper__filename');
        var resetButton = wrapper.querySelector('.file-upload-wrapper__reset');
        var hasFile = !!(input.files && input.files.length > 0);

        if (nameEl) {
            if (hasFile) {
                nameEl.textContent = input.files[0].name;
                nameEl.classList.add('file-upload-wrapper__filename--selected');
            } else {
                nameEl.textContent = 'Aucun fichier sélectionné';
                nameEl.classList.remove('file-upload-wrapper__filename--selected');
            }
        }

        if (resetButton) {
            resetButton.hidden = !hasFile;
        }
    }

    function init(wrapper) {
        var input = wrapper.querySelector('input[type="file"]');
        if (!input) {
            return;
        }

        input.addEventListener('change', function () {
            refresh(wrapper);
        });

        var resetButton = wrapper.querySelector('.file-upload-wrapper__reset');
        if (resetButton) {
            resetButton.addEventListener('click', function () {
                // Reset ciblé : seule la sélection en cours est annulée. Aucun
                // reset global du formulaire, et aucune modification de la case
                // de suppression serveur — une pièce jointe déjà stockée reste
                // intacte.
                input.value = '';
                refresh(wrapper);
            });
        }
    }

    var wrappers = document.querySelectorAll('.file-upload-wrapper');
    for (var i = 0; i < wrappers.length; i++) {
        init(wrappers[i]);
    }
})();