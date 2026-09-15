<?php
/**
 * error_notify subprocess runner — collects the error_log verdict + throttle
 * state in an isolated process (the in-process `error_log` ini can't be trusted
 * uner PHPUnit, and sstGetAdminEmail() caches statically per process).
 *
 * Usage: php tests/error_notify_runner.php <config.json>
 */

if (($configPath = $argv[1] ?? '') === '' || !file_exists($configPath)) {
    fwrite(STDERR, "Usage: php error_notify_runner.php <config.json>\n");
    exit(1);
}
$config = json_decode((string) file_get_contents($configPath), true);
if (!is_array($config)) {
    fwrite(STDERR, "Invalid config JSON\n");
    exit(1);
}

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/mail.php';
require_once __DIR__ . '/../src/error_notify.php';

$logFile = tempnam(sys_get_temp_dir(), 'sst_errlog_');
ini_set('display_errors', '0');
ini_set('error_log', (string) $logFile);
if (file_exists(ERROR_THROTTLE_FILE)) {
    @unlink(ERROR_THROTTLE_FILE);
}

getConfigService()->set('app_admin_email', (string) ($config['admin_email'] ?? 'admin.ops@dreets-bfc.gouv.fr'));

$mailerSeam = $config['mailer_seam'] ?? null;
if ($mailerSeam === 'ok') {
    setMailerSeam(static fn(string $to, string $subject, string $body, string $from = ''): bool => true);
} elseif ($mailerSeam === 'fail') {
    setMailerSeam(static fn(string $to, string $subject, string $body, string $from = ''): bool => false);
}

$level = (string) ($config['level'] ?? 'Fatal error');
$message = (string) ($config['message'] ?? 'msg');
$line = (int) ($config['line'] ?? 1);

sstNotifyAdminError($level, $message, __FILE__, $line, 256);

$errorKey = md5($level . $message . basename(__FILE__) . $line);

echo json_encode([
    'log' => (string) file_get_contents((string) $logFile),
    'throttled' => sstIsThrottled($errorKey),
    'key' => $errorKey,
]);

@unlink((string) $logFile);
if (file_exists(ERROR_THROTTLE_FILE)) {
    @unlink(ERROR_THROTTLE_FILE);
}