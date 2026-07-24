<?php
use App\Repository\RegistryRepository;
use App\Repository\RegistryFieldRepository;

// Seed default registries (RSST, RAMI, DGI) if not present
$registryRepo = RegistryRepository::instance();
$registryRepo->seedDefaults();

// Seed RAMI-specific fields
$rami = $registryRepo->findByCode('rami');
if ($rami !== null) {
    $fieldRepo = RegistryFieldRepository::instance();
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
