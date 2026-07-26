<?php
/** @var string $screenshotBase */
/** @var list<array<string, mixed>> $enabledRegistries */
/** @var int $registryCount */
?>
<!-- 4. Les registres -->
<div id="registres" class="card card--spaced content-section">
    <h2>Les <?php echo $registryCount; ?> registres</h2>
    <p class="help-description">L'application gère <strong><?php echo $registryCount; ?> registre<?php echo $registryCount > 1 ? 's' : ''; ?></strong> distinct<?php echo $registryCount > 1 ? 's' : ''; ?> pour la santé et sécurité au travail.</p>

    <div class="help-profiles-grid">
        <?php
        // Modular-audit P2.5 — iterate over enabled registries dynamically.
        // Before this fix, 3 cards were hardcoded (RSST + RAMI + DGI).
        // Custom registries were never documented in the help page.
        foreach ($enabledRegistries as $reg):
            $regCode = (string) $reg['code'];
            $regLabel = (string) $reg['label'];
            $regShortLabel = (string) $reg['short_label'];
            $regDescription = (string) ($reg['description'] ?? '');
            $regIcon = (string) ($reg['icon'] ?? '📋');
            $colorTheme = (string) ($reg['color_theme'] ?? 'rsst');
        ?>
        <div class="help-profile-card help-profile-card--<?php echo e($colorTheme); ?>">
            <h3><?php echo e($regShortLabel); ?></h3>
            <p class="help-description help-description--title"><?php echo e($regLabel); ?></p>
            <?php if ($regDescription !== ''): ?>
            <p class="help-description"><?php echo e($regDescription); ?></p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <?php
    // Screenshots — only show for the 3 historical registres (RSST, RAMI, DGI)
    // since custom registres don't have screenshots bundled.
    // Modular-audit P2.5 — use ReportType enum values instead of magic strings.
    foreach ($enabledRegistries as $reg):
        $regCode = (string) $reg['code'];
        $screenshotFile = match ($regCode) {
            \App\Enum\ReportType::Rsst->value => '/cu2-creation-rsst.html',
            \App\Enum\ReportType::Rami->value => '/cu3-creation-rami.html',
            \App\Enum\ReportType::Dgi->value  => '/cu4-creation-dgi.html',
            default => null,
        };
        if ($screenshotFile !== null):
            echo helpScreenshot($screenshotBase . $screenshotFile, "Formulaire de création d'un signalement " . $reg['short_label']);
        endif;
    endforeach;
    ?>
</div>
