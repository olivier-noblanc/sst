<?php

namespace App\Middleware;

class RoleMiddleware
{
    /** @param list<string> $roles */
    public function __construct(private readonly array $roles) {}
    public function __invoke(callable $next): void
    {
        if (!hasAnyRole($this->roles)) {
            \App\Services\SessionService::getInstance()->setFlash('error', 'Accès refusé.');
            new \App\Services\HttpService()->redirect(new \App\Services\HttpService()->url('home'));
        }
        $next();
    }
}
