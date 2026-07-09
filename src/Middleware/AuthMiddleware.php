<?php
namespace App\Middleware;

class AuthMiddleware {
    public function __invoke(callable $next): void {
        if (!isUserLoggedIn()) {
            if (DEV_MODE) {
                setIntendedUrl($_SERVER['REQUEST_URI'] ?? '');
                redirect(url('login'));
            } else {
                http_response_code(500);
                echo '<h1>Erreur de configuration</h1><p>Auth non disponible.</p>';
                exit;
            }
        }
        $next();
    }
}
