<?php
/**
 * Helpers Bootstrap — Application SST DREETS BFC
 *
 * Loads all helper modules and provides container-backed global wrappers.
 * This file bridges the legacy procedural helpers with the OOP DI Container:
 *   - Loads src/helpers.php (which includes all src/helpers/*.php sub-modules)
 *   - Provides thin global function wrappers that delegate to Container services
 *
 * The existing helpers/*.php files already delegate to Services, but they
 * instantiate new Service objects on every call. This file provides the same
 * functions backed by the shared Container singletons when available.
 *
 * Usage: require_once __DIR__ . '/helpers_bootstrap.php';
 *        (instead of requiring helpers.php directly)
 */

// Load all existing helpers (they define the global functions)
require_once __DIR__ . '/helpers.php';

// Only provide container-backed wrappers if the Container is available.
// During early bootstrap (config.php loading), getContainer() may not exist yet.
if (!function_exists('getContainer')) {
    return;
}

// ═══════════════════════════════════════════════════════════════════════════════
// Note: The helper functions in src/helpers/*.php already delegate to Services.
// They create `new ServiceName()` on each call.
//
// The Container is available via getContainer() for any code that needs a
// specific service instance with shared state (e.g. ConfigService cache,
// EventDispatcher listeners).
//
// For backward compatibility, no global functions are redefined here —
// the existing helpers already work correctly through the Services layer.
// This file ensures helpers.php is loaded exactly once and the Container
// is initialized for the request lifecycle.
// ═══════════════════════════════════════════════════════════════════════════════

// Ensure the Container is initialized on first helpers_bootstrap.php load.
// This is a side-effect: the Container's PDO connection is created early,
// which also triggers schema migration if needed.
if (php_sapi_name() !== 'cli' || (defined('PHPUNIT_RUNNING') && PHPUNIT_RUNNING)) {
    getContainer();
}
