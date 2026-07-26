<?php
use App\Repository\RegistryRepository;
use App\Repository\RegistryFieldRepository;

// Seed default registries (RSST, RAMI, DGI) if not present
$registryRepo = RegistryRepository::instance();
$registryRepo->seedDefaults();

// Modular-audit P1.5 — Seed RAMI-specific fields via registry_fields DB.
// Before this fix, only nature_auteur and type_acte were seeded — pour_compte
// (the checkbox that reveals the "pour le compte de" sub-fields) was missing.
// E2E tests e2e/forms.spec.js:117 and reports.spec.js:128 expected #pour_compte
// to exist in the DOM, but it was never rendered (no registry_field for it).
$rami = $registryRepo->findByCode('rami');
if ($rami !== null) {
    $fieldRepo = RegistryFieldRepository::instance();
    if ($fieldRepo->findByCode((int) $rami['id'], 'pour_compte') === null) {
        $fieldRepo->create((int) $rami['id'], [
            'field_code' => 'pour_compte',
            'label' => 'Signaler pour le compte d\'un autre agent',
            'field_type' => 'checkbox',
            'options' => null,
            'is_required' => 0,
            'sort_order' => 0,
        ]);
    }
    // Modular-audit P1.5 — pour_compte_nom/prenom aussi via registry_fields.
    // Before this fix, these fields were rendered by the dead code in
    // templates/report_form_rami.php (which was deleted in audit #78).
    // They were never re-implemented via the dynamic registry_fields system.
    // E2E test e2e/forms.spec.js:129 expected #pour_compte_nom to be visible
    // after clicking #pour_compte — never happened.
    if ($fieldRepo->findByCode((int) $rami['id'], 'pour_compte_nom') === null) {
        $fieldRepo->create((int) $rami['id'], [
            'field_code' => 'pour_compte_nom',
            'label' => 'Nom de l\'agent pour le compte de qui vous signalez',
            'field_type' => 'text',
            'options' => null,
            'is_required' => 0,
            'sort_order' => 3,
        ]);
    }
    if ($fieldRepo->findByCode((int) $rami['id'], 'pour_compte_prenom') === null) {
        $fieldRepo->create((int) $rami['id'], [
            'field_code' => 'pour_compte_prenom',
            'label' => 'Prénom de l\'agent pour le compte de qui vous signalez',
            'field_type' => 'text',
            'options' => null,
            'is_required' => 0,
            'sort_order' => 4,
        ]);
    }
    if ($fieldRepo->findByCode((int) $rami['id'], 'nature_auteur') === null) {
        $fieldRepo->create((int) $rami['id'], [
            'field_code' => 'nature_auteur',
            'label' => 'Nature de l\'auteur',
            'field_type' => 'select',
            'options' => json_encode([
                'usager' => 'Usager',
                'collegue' => 'Collègue',
                'hierarchie' => 'Hiérarchie',
                'tiers' => 'Tiers',
            ]),
            'is_required' => 0,
            'sort_order' => 1,
        ]);
    }
    if ($fieldRepo->findByCode((int) $rami['id'], 'type_acte') === null) {
        $fieldRepo->create((int) $rami['id'], [
            'field_code' => 'type_acte',
            'label' => 'Type d\'acte',
            'field_type' => 'select',
            'options' => json_encode([
                'verbal' => 'Verbal',
                'physique' => 'Physique',
                'moral' => 'Moral',
                'sexiste' => 'Sexiste',
                'autre' => 'Autre',
            ]),
            'is_required' => 0,
            'sort_order' => 2,
        ]);
    }
}

echo "Registries seeded: " . count($registryRepo->findAll()) . " registries, fields OK.\n";
