<?php

/**
 * Settings Handler: Registres — Application SST DREETS BFC
 *
 * POST handler: save registry settings (enable/disable, color, icon).
 * Access: superviseur only (enforced by Router middleware)
 */

use App\Repository\RegistryRepository;

/**
 * Handle the 'registres' settings tab.
 *
 * @param PDO    $pdo      Database connection
 * @param array<string, mixed> $postData POST data
 */
function handleSettingsRegistresTab(PDO $pdo, array $postData): void
{
    $repo = RegistryRepository::instance();
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

        // System registres can't be disabled
        $isSystem = (int) $existing['is_system'] === 1;
        $isEnabled = !empty($data['is_enabled']) ? 1 : 0;

        $updateData = ['is_enabled' => $isEnabled];

        // Color theme (only for non-system registres)
        if (!$isSystem && !empty($data['color_theme'])) {
            $theme = (string) $data['color_theme'];
            $validThemes = RegistryRepository::availableThemes();
            if (in_array($theme, $validThemes, true)) {
                $updateData['color_theme'] = $theme;
            }
        }

        // Icon (only for non-system registres)
        if (!$isSystem && !empty($data['icon'])) {
            $updateData['icon'] = (string) $data['icon'];
        }

        $repo->update($id, $updateData);
    }
}
