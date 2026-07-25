<?php

/**
 * Settings Handler: Registres — Application SST DREETS BFC
 *
 * POST handler: full CRUD for registry settings.
 * Actions: save (update all), add (create new), delete_{id} (remove).
 * Access: superviseur only (enforced by Router middleware)
 */

use App\Repository\RegistryRepository;
use App\Enum\VisibilityMode;

/**
 * Handle the 'registres' settings tab.
 *
 * @param PDO    $pdo      Database connection
 * @param array<string, mixed> $postData POST data
 */
function handleSettingsRegistresTab(PDO $pdo, array $postData): void
{
    $repo = RegistryRepository::instance();
    $fieldRepo = \App\Repository\RegistryFieldRepository::instance();
    $action = trim((string) ($postData['action'] ?? 'save'));

    // ── Delete a registry field ──────────────────────────────────────────
    if ($action === 'delete_field') {
        $fieldId = (int) ($postData['field_id'] ?? 0);
        if ($fieldId > 0) {
            $fieldRepo->delete($fieldId);
            setFlash('success', 'Champ supprimé.');
        }
        redirect(url('settings', ['tab' => 'registres']));
    }

    // ── Add a new registry field ─────────────────────────────────────────
    if ($action === 'add_field') {
        $registryId = (int) ($postData['registry_id'] ?? 0);
        $fieldCode = trim(strtolower((string) ($postData['new_field_code'] ?? '')));
        $fieldLabel = trim((string) ($postData['new_field_label'] ?? ''));
        $fieldType = trim((string) ($postData['new_field_type'] ?? 'text'));
        $fieldOptions = trim((string) ($postData['new_field_options'] ?? ''));
        $isRequired = !empty($postData['new_field_required']) ? 1 : 0;
        $sortOrder = (int) ($postData['new_field_order'] ?? 0);

        if ($registryId <= 0 || $fieldCode === '' || $fieldLabel === '') {
            setFlash('error', 'Registre, code et libellé sont requis.');
            redirect(url('settings', ['tab' => 'registres']));
        }

        if (!preg_match('/^[a-z_]+$/', $fieldCode)) {
            setFlash('error', 'Le code ne doit contenir que des lettres minuscules et des underscores.');
            redirect(url('settings', ['tab' => 'registres']));
        }

        if ($fieldOptions !== '') {
            $decoded = json_decode($fieldOptions, true);
            if (!is_array($decoded)) {
                setFlash('error', 'Les options doivent être un JSON valide.');
                redirect(url('settings', ['tab' => 'registres']));
            }
        }

        $fieldRepo->create($registryId, [
            'field_code'  => $fieldCode,
            'label'       => $fieldLabel,
            'field_type'  => $fieldType,
            'options'     => $fieldOptions !== '' ? $fieldOptions : null,
            'is_required' => $isRequired,
            'sort_order'  => $sortOrder,
        ]);

        setFlash('success', 'Champ « ' . $fieldLabel . ' » ajouté.');
        redirect(url('settings', ['tab' => 'registres']));
    }

    // ── Delete a registry ─────────────────────────────────────────────────
    if (str_starts_with($action, 'delete_')) {
        $deleteId = (int) substr($action, 7);
        if ($deleteId > 0) {
            $reg = $repo->findById($deleteId);
            if ($reg !== null && (int) $reg['is_system'] === 0) {
                $repo->delete($deleteId);
                setFlash('success', 'Registre « ' . $reg['label'] . ' » supprimé.');
            } else {
                setFlash('error', 'Impossible de supprimer ce registre.');
            }
        }
        redirect(url('settings', ['tab' => 'registres']));
    }

    // ── Add a new registry ────────────────────────────────────────────────
    if ($action === 'add') {
        $code = trim(strtolower((string) ($postData['new_code'] ?? '')));
        $label = trim((string) ($postData['new_label'] ?? ''));
        $shortLabel = trim((string) ($postData['new_short_label'] ?? ''));

        if ($code === '' || $label === '' || $shortLabel === '') {
            setFlash('error', 'Code, libellé et sigle sont requis.');
            redirect(url('settings', ['tab' => 'registres']));
        }

        if (!preg_match('/^[a-z_]+$/', $code)) {
            setFlash('error', 'Le code ne doit contenir que des lettres minuscules et des underscores.');
            redirect(url('settings', ['tab' => 'registres']));
        }

        if ($repo->countByCode($code) > 0) {
            setFlash('error', 'Un registre avec le code « ' . $code . ' » existe déjà.');
            redirect(url('settings', ['tab' => 'registres']));
        }

        $maxOrder = 0;
        foreach ($repo->findAll() as $r) {
            $o = (int) ($r['sort_order'] ?? 0);
            if ($o > $maxOrder) {
                $maxOrder = $o;
            }
        }

        $repo->create([
            'code'               => $code,
            'label'              => $label,
            'short_label'        => $shortLabel,
            'description'        => trim((string) ($postData['new_description'] ?? '')),
            'icon'               => '📋',
            'color_theme'        => VisibilityMode::AgentChoice->value,
            'is_enabled'         => 1,
            'is_system'          => 0,
            'sort_order'         => $maxOrder + 1,
            'default_visibility' => VisibilityMode::AgentChoice->value,
        ]);

        setFlash('success', 'Registre « ' . $label . ' » ajouté.');
        redirect(url('settings', ['tab' => 'registres']));
    }

    // ── Save all registres ────────────────────────────────────────────────
    /** @var array<int, array<string, mixed>> $registres */
    $registres = is_array($postData['registres'] ?? null) ? $postData['registres'] : [];

    foreach ($registres as $regId => $data) {
        $id = (int) $regId;
        if ($id <= 0) {
            continue;
        }

        $existing = $repo->findById($id);
        if ($existing === null) {
            continue;
        }

        $isSystem = (int) $existing['is_system'] === 1;
        $isEnabled = $isSystem ? 1 : (!empty($data['is_enabled']) ? 1 : 0);

        $updateData = [
            'is_enabled'         => $isEnabled,
            'label'              => trim((string) ($data['label'] ?? $existing['label'])),
            'short_label'        => trim((string) ($data['short_label'] ?? $existing['short_label'])),
            'description'        => trim((string) ($data['description'] ?? '')),
            'sort_order'         => (int) ($data['sort_order'] ?? $existing['sort_order']),
            'default_visibility' => (string) ($data['default_visibility'] ?? $existing['default_visibility']),
            'notify_chsct'       => !empty($data['notify_chsct']) ? 1 : 0,
            'legal_note'         => trim((string) ($data['legal_note'] ?? '')),
        ];

        // Color theme
        if (!empty($data['color_theme'])) {
            $theme = (string) $data['color_theme'];
            $validThemes = RegistryRepository::availableThemes();
            if (in_array($theme, $validThemes, true)) {
                $updateData['color_theme'] = $theme;
            }
        }

        // Icon
        if (!empty($data['icon'])) {
            $updateData['icon'] = (string) $data['icon'];
        }

        $repo->update($id, $updateData);
    }

    setFlash('success', 'Registres mis à jour avec succès.');
    redirect(url('settings', ['tab' => 'registres']));
}
