<?php
/**
 * PHPStan Bootstrap — load runtime constants via config.php.
 *
 * PHPStan analyses files in isolation and cannot infer constants
 * defined via define() in config.php. This file pre-loads config.php
 * so all constants are available during static analysis.
 *
 * No manual define() calls — config.php is the single source of truth.
 * No other require_once needed — PHPStan's "paths" config discovers
 * all functions and classes automatically.
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/config.php';
