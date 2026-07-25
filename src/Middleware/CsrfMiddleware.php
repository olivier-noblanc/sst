<?php

namespace App\Middleware;

class CsrfMiddleware
{
    public function __invoke(callable $next): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? '';
            if (!validateCsrfToken($token)) {
                \App\Services\SessionService::getInstance()->setFlash('error', 'Erreur de sécurité.');
                new \App\Services\HttpService()->redirect(new \App\Services\HttpService()->url('home'));
            }
        }
        $next();
    }
}
