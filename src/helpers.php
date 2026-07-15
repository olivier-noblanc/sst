<?php

/**
 * Helper Functions — Application SST DREETS BFC
 *
 * This file is a LOADER that includes all helper sub-modules.
 * Functions were previously in a single 720-line monolith; they are now
 * split into focused files under src/helpers/ for maintainability.
 *
 * Order matters: config.php must load first (other helpers call getConfig()).
 */

require_once __DIR__ . '/helpers/config.php';
require_once __DIR__ . '/helpers/crypto.php';
require_once __DIR__ . '/helpers/access.php';
require_once __DIR__ . '/helpers/assets.php';
require_once __DIR__ . '/helpers/formatting.php';
require_once __DIR__ . '/helpers/http.php';
require_once __DIR__ . '/helpers/registry_card_renderer.php';
