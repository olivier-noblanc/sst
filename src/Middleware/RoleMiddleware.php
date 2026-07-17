<?php

namespace App\Middleware;

class RoleMiddleware
{
    /** @param list<string> $roles */
    public function __construct(private readonly array $roles) {}
    public function __invoke(callable $next): void
    {
        if (!hasAnyRole($this->roles)) {
            setFlash('error', 'Accès refusé.');
            redirect(url('home'));
        }
        $next();
    }
}
