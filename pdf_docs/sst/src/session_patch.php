<?php
/**
 * Session Patch — Fix for PHP built-in server compatibility
 * 
 * The PHP built-in server crashes with session_regenerate_id(true).
 * This patch provides a safe wrapper that uses false in dev mode.
 * In production on IIS, the original session_regenerate_id(true) is fine.
 * 
 * DEPLOYMENT: In production, delete this file and restore original
 * session_regenerate_id(true) calls in auth.php and login_handler.php.
 */

function safeSessionRegenerate(): void {
    // Use false (don't delete old session) in dev to prevent built-in server crash
    // Use true (delete old session) in production for security
    session_regenerate_id(!DEV_MODE);
}
