<?php

/** Contrat partagé des codes de champs custom réservés par le modèle de données. */

namespace App\Repository;

final class CustomFieldContract
{
    /** @var list<string> */
    public const array COMMAND_MAPPED_CODES = [
        'uuid', 'reference', 'type', 'objet', 'description', 'date_evenement',
        'heure_evenement', 'lieu', 'declarant_id', 'declarant_nom',
        'declarant_prenom', 'site_id', 'site_text', 'pole', 'service_affectation',
        'telephone_mobile', 'is_confidential', 'consent_syndicat', 'etat',
        'repondant_id', 'date_reponse', 'reponse', 'attachment_blob',
        'attachment_name', 'attachment_mime', 'created_at', 'updated_at',
        'pour_compte_de', 'pour_compte_nom', 'pour_compte_prenom',
        'nature_auteur', 'type_acte',
        'report_uuid', 'site_code', 'site_nom', 'repondant_nom', 'repondant_prenom',
        'linked_emails', 'remove_attachment', 'attachment', 'attachment*',
        'action', 'csrf_token', 'registry_id', 'field_id', 'new_field_code',
        'new_field_label', 'new_field_type', 'new_field_options',
        'new_field_required', 'new_field_order', 'pour_compte',
    ];

    /** @var list<string> */
    public const array LEGACY_DEFINITION_CODES = [
        'pour_compte', 'pour_compte_nom', 'pour_compte_prenom',
        'nature_auteur', 'type_acte',
    ];
}
