<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Session;

/**
 * Middleware d'authentification
 * Vérifie que l'utilisateur est connecté
 */
class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request): bool
    {
        if (!Session::isLoggedIn()) {
            Session::setFlash('error', 'Vous devez être connecté.');
            header('Location: /login');
            exit(302);
        }
        return true;
    }
}
