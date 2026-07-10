<?php

/**
 * Authentication Flow — Application SST DREETS BFC
 *
 * Delegates to App\Services\AuthService.
 */

function handleAutoAuth(): void
{
    getAuthServiceInstance()->handleAutoAuth();
}

function handleLoginPage(string $page): void
{
    getAuthServiceInstance()->handleLoginPage($page);
}

function handleNotAuthenticated(): void
{
    getAuthServiceInstance()->handleNotAuthenticated();
}

function handleLogout(): void
{
    getAuthServiceInstance()->handleLogout();
}
