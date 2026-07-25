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
        }
        $next();
    }
}
