<?php

namespace App\Middleware;

class CsrfMiddleware
{
    public function __invoke(callable $next): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? '';
            if (!validateCsrfToken($token)) {
                setFlash('error', 'Erreur de sécurité.');
                redirect(url('home'));
            }
        }
        $next();
    }
}
