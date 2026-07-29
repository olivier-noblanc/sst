<?php
/**
 * Validation function test runner — executes a require*Respondable/Editable
 * check in a subprocess, since HttpService::redirect() calls a raw exit()
 * that no in-process try/catch can intercept (PHP exit() is never
 * catchable — confirmed the hard way tonight, twice: once on
 * pages/report_list.php, again here). Same register_shutdown_function
 * pattern as tests/handler_runner.php, scoped to just the validation layer
 * instead of a full handler.
 *
 * Usage: php tests/validation_runner.php <functionName> <etat>
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/validation.php';

$functionName = $argv[1] ?? '';
$etat = $argv[2] ?? '';
if ($functionName === '' || $etat === '') {
    fwrite(STDERR, "Usage: php validation_runner.php <functionName> <etat>\n");
    exit(1);
}
if (!in_array($functionName, ['requireReportRespondable', 'requireReportEditable'], true)) {
    fwrite(STDERR, "Unknown function: $functionName\n");
    exit(1);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION = [];
$GLOBALS['_PHP_REDIRECT'] = null;

register_shutdown_function(function () {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode([
        'redirect' => $GLOBALS['_PHP_REDIRECT'] ?? null,
        'flash' => $_SESSION['flash'] ?? null,
    ]);
});

$report = new \App\DTO\ReportData(
    uuid: 'test-uuid', reference: 'RSST-25-001', type: 'rsst',
    objet: 'Objet test', description: 'Description test',
    dateEvenement: '2025-01-01', heureEvenement: '', lieu: '',
    declarantId: 1, declarantNom: 'Dupont', declarantPrenom: 'Jean',
    pourCompteDe: '', pourCompteNom: '', pourComptePrenom: '',
    natureAuteur: '', typeActe: '', siteId: 1, siteText: '',
    pole: '', serviceAffectation: '', telephoneMobile: '',
    isConfidential: 0, consentSyndicat: 0, etat: $etat,
    repondantId: null, dateReponse: null, reponse: null,
    attachmentName: null, attachmentMime: null,
    createdAt: '2025-01-01 10:00:00', updatedAt: '2025-01-01 10:00:00',
    siteCode: 'UR21', siteNom: 'UR Test', repondantNom: null, repondantPrenom: null,
);

ob_start();
$functionName($report, 'test-uuid', 'répondu');
