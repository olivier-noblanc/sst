<?php

use App\Services\SessionService;

/**
 * Session Patch — Fix for PHP built-in server compatibility
 *
 * Delegates to App\Services\SessionService.
 */
function safeSessionRegenerate(): void
{
    new SessionService()->safeSessionRegenerate();
}
