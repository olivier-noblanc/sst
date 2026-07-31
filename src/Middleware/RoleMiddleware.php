<?php

namespace App\Middleware;

use App\Services\SessionService;
use App\Services\HttpService;

class RoleMiddleware
{
    /** @param list<string> $roles */
    public function __construct(private readonly array $roles) {}
    public function __invoke(callable $next): void
    {
        if (!hasAnyRole($this->roles)) {
            SessionService::getInstance()->setFlash('error', 'Accès refusé.');
            new HttpService()->redirect(new HttpService()->url('home'));
            return; // Bug #26 — explicit return for defense-in-depth
            // even though redirect() calls exit(), this makes the
            // control flow explicit and survives any future refactor
            // of HttpService::redirect().
        }
        $next();
    }
}
