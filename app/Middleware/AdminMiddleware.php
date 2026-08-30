<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Session;

/**
 * Middleware administrateur
 * Vérifie que l'utilisateur est un admin
 */
class AdminMiddleware implements MiddlewareInterface
{
    public function handle(Request $request): bool
    {
        if (!Session::isLoggedIn()) {
            Session::setFlash('error', 'Vous devez être connecté.');
            header('Location: /login');
            exit(302);
        }

        $user = Session::user();
        if (!$user || ($user['role_slug'] ?? '') !== 'admin') {
            Session::setFlash('error', 'Accès réservé aux administrateurs.');
            header('Location: /');
            exit(302);
        }

        return true;
    }
}
