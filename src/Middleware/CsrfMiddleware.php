<?php

namespace App\Middleware;

class CsrfMiddleware
{
    public function __invoke(callable $next): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? '';
            $tokens = $_SESSION['csrf_tokens'] ?? [];
            error_log('[SST-CSRF] POST received token=' . substr($token, 0, 8) . '... stored_tokens=' . count($tokens) . ' session_id=' . substr(session_id(), 0, 8));
            if (!validateCsrfToken($token)) {
                error_log('[SST-CSRF] FAILED — token not found in session');
                setFlash('error', 'Erreur de sécurité.');
                redirect(url('home'));
            }
        }
        $next();
    }
}
