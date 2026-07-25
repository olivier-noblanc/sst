<?php
/**
 * Test Suite: canAccessReport() — Application SST DREETS BFC
 *
 * Standalone test script covering the authorization matrix:
 *   role (agent/superviseur/chsct) × visibility (confidential/agent_choice/public)
 *   × site (same/different) × is_confidential (0/1) × declarant (self/other)
 *
 * Uses the REAL canAccessReport() from src/helpers.php by passing
 * the visibility mode as the 3rd argument ($forcedVisibility),
 * avoiding any DB dependency.
 *
 * Usage:
 *   php tools/tests/test_can_access_report.php
 *
 * Exit code: 0 = all tests passed, 1 = one or more failures
 */

// Only run from CLI
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$projectRoot = dirname(__DIR__, 2);

// Stub getDB() so helpers.php can be loaded without database.php
// (canAccessReport with $forcedVisibility never calls getDB/getConfig,
//  but helpers.php defines functions that reference them)
if (!function_exists('getDB')) {
    function getDB(): PDO {
        throw new \RuntimeException('getDB() should not be called in tests — use $forcedVisibility parameter.');
    }
}

require_once $projectRoot . '/src/helpers.php';

/** @param array<string, mixed> $overrides */
function makeReportData(array $overrides): \App\DTO\ReportData {
    return new \App\DTO\ReportData(
        uuid: $overrides['uuid'] ?? '',
        reference: $overrides['reference'] ?? '',
        type: $overrides['type'] ?? 'rsst',
        objet: $overrides['objet'] ?? '',
        description: $overrides['description'] ?? '',
        dateEvenement: $overrides['date_evenement'] ?? '',
        heureEvenement: $overrides['heure_evenement'] ?? '',
        lieu: $overrides['lieu'] ?? '',
        declarantId: $overrides['declarant_id'] ?? 0,
        declarantNom: $overrides['declarant_nom'] ?? '',
        declarantPrenom: $overrides['declarant_prenom'] ?? '',
        pourCompteDe: $overrides['pour_compte_de'] ?? '',
        pourCompteNom: $overrides['pour_compte_nom'] ?? '',
        pourComptePrenom: $overrides['pour_compte_prenom'] ?? '',
        natureAuteur: $overrides['nature_auteur'] ?? '',
        typeActe: $overrides['type_acte'] ?? '',
        siteId: $overrides['site_id'] ?? 0,
        siteText: $overrides['site_text'] ?? '',
        pole: $overrides['pole'] ?? '',
        serviceAffectation: $overrides['service_affectation'] ?? '',
        telephoneMobile: $overrides['telephone_mobile'] ?? '',
        isConfidential: $overrides['is_confidential'] ?? 0,
        consentSyndicat: $overrides['consent_syndicat'] ?? 0,
        etat: $overrides['etat'] ?? '',
        repondantId: $overrides['repondant_id'] ?? null,
        dateReponse: $overrides['date_reponse'] ?? null,
        reponse: $overrides['reponse'] ?? null,
        attachmentName: $overrides['attachment_name'] ?? null,
        attachmentMime: $overrides['attachment_mime'] ?? null,
        createdAt: $overrides['created_at'] ?? '',
        updatedAt: $overrides['updated_at'] ?? '',
        siteCode: $overrides['site_code'] ?? '',
        siteNom: $overrides['site_nom'] ?? '',
        repondantNom: $overrides['repondant_nom'] ?? null,
        repondantPrenom: $overrides['repondant_prenom'] ?? null,
    );
}

$passed = 0;
$failed = 0;
$total = 0;

function assert_can_access(bool $expected, bool $actual, string $label): void {
    global $passed, $failed, $total;
    $total++;
    if ($expected === $actual) {
        $passed++;
        echo "  ✅ {$label}\n";
    } else {
        $failed++;
        $expStr = $expected ? 'ALLOW' : 'DENY';
        $actStr = $actual ? 'ALLOW' : 'DENY';
        echo "  ❌ {$label} — expected {$expStr}, got {$actStr}\n";
    }
}

echo "\n=== Tests canAccessReport() — SST DREETS BFC ===\n";
echo "(testing the REAL function from helpers.php via \$forcedVisibility)\n\n";

// ============================================================
// Test matrix
// ============================================================

$roles = ['agent', 'superviseur', 'chsct'];
$visibilities = ['confidential', 'agent_choice', 'public'];
$sites = ['same', 'different'];
$confidentiality = [0, 1]; // is_confidential
$declarant = ['self', 'other'];

$siteId = 1;
$userId = 42;
$otherSiteId = 2;
$otherUserId = 99;

echo "Matrice : rôle × visibilité × site × confidentialité × déclarant\n";
echo str_repeat('=', 70) . "\n\n";

foreach ($roles as $role) {
    echo "--- Rôle : " . strtoupper($role) . " ---\n";

    foreach ($visibilities as $visibility) {
        foreach ($sites as $siteCase) {
            foreach ($confidentiality as $isConf) {
                foreach ($declarant as $declCase) {
                    $reportSiteId = ($siteCase === 'same') ? $siteId : $otherSiteId;
                    $reportDeclarantId = ($declCase === 'self') ? $userId : $otherUserId;

                    $report = makeReportData([
                        'site_id' => $reportSiteId,
                        'declarant_id' => $reportDeclarantId,
                        'is_confidential' => $isConf,
                        'type' => 'rsst',
                    ]);

                    $user = [
                        'id' => $userId,
                        'site_id' => $siteId,
                        'role' => $role,
                    ];

                    // Expected result: derive from the rules
                    if (in_array($role, ['superviseur', 'chsct'])) {
                        $expected = true; // Always can access
                    } elseif ($siteCase === 'different') {
                        $expected = false; // Agent can't see other sites
                    } elseif ($visibility === 'confidential' && $declCase === 'other') {
                        $expected = false; // Confidential: only own reports
                    } elseif ($visibility === 'agent_choice' && $isConf === 1 && $declCase === 'other') {
                        $expected = false; // Agent_choice: can't see others' confidential
                    } else {
                        $expected = true; // All other cases: can access
                    }

                    $actual = canAccessReport($report, $user, $visibility);

                    $label = sprintf(
                        "vis=%s site=%s conf=%d decl=%s → %s",
                        $visibility,
                        $siteCase,
                        $isConf,
                        $declCase,
                        $expected ? 'ALLOW' : 'DENY'
                    );

                    assert_can_access($expected, $actual, $label);
                }
            }
        }
    }
    echo "\n";
}

// ============================================================
// Edge cases
// ============================================================

echo "--- Cas limites ---\n";

// 1. Superviseur with different site — should still have access
$report = makeReportData(['site_id' => 999, 'declarant_id' => $otherUserId, 'is_confidential' => 1, 'type' => 'rami']);
$user = ['id' => $userId, 'site_id' => $siteId, 'role' => 'superviseur'];
assert_can_access(true, canAccessReport($report, $user, 'confidential'), 'superviseur × other site × confidential = ALLOW');

// 2. CHSCT with different site — should still have access
$user = ['id' => $userId, 'site_id' => $siteId, 'role' => 'chsct'];
assert_can_access(true, canAccessReport($report, $user, 'confidential'), 'chsct × other site × confidential = ALLOW');

// 3. Agent in public mode, different site — DENY
$user = ['id' => $userId, 'site_id' => $siteId, 'role' => 'agent'];
assert_can_access(false, canAccessReport($report, $user, 'public'), 'agent × public × other site = DENY');

// 4. Agent in agent_choice mode, not confidential, other declarant — ALLOW
$report = makeReportData(['site_id' => $siteId, 'declarant_id' => $otherUserId, 'is_confidential' => 0, 'type' => 'rsst']);
assert_can_access(true, canAccessReport($report, $user, 'agent_choice'), 'agent × agent_choice × not_confidential × other = ALLOW');

// 5. Agent in confidential mode, own report — ALLOW
$report = makeReportData(['site_id' => $siteId, 'declarant_id' => $userId, 'is_confidential' => 1, 'type' => 'dgi']);
assert_can_access(true, canAccessReport($report, $user, 'confidential'), 'agent × confidential × own = ALLOW');

// 6. Agent in agent_choice mode, own confidential report — ALLOW
assert_can_access(true, canAccessReport($report, $user, 'agent_choice'), 'agent × agent_choice × own_confidential = ALLOW');

// 7. Agent in public mode, own confidential report — ALLOW
assert_can_access(true, canAccessReport($report, $user, 'public'), 'agent × public × own_confidential = ALLOW');

echo "\n";

// ============================================================
// Summary
// ============================================================

echo str_repeat('=', 70) . "\n";
echo "RÉSULTAT : {$passed}/{$total} test(s) réussi(s)";
if ($failed > 0) {
    echo " — {$failed} ÉCHEC(S)";
}
echo "\n";

if ($failed > 0) {
    echo "\n❌ Certains tests ont échoué. Vérifiez la logique de canAccessReport().\n\n";
    exit(1);
} else {
    echo "\n✅ Tous les tests ont réussi.\n\n";
    exit(0);
}
