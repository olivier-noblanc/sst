<?php

namespace App\Middleware;

use App\Services\SessionService;
use App\Services\HttpService;

class CsrfMiddleware
{
    public function __invoke(callable $next): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? '';
            if (!validateCsrfToken($token)) {
                SessionService::getInstance()->setFlash('error', 'Erreur de sécurité.');
                new HttpService()->redirect(new HttpService()->url('home'));
                return; // Bug #26 — defense-in-depth
            }
        }
        $next();
    }
}
