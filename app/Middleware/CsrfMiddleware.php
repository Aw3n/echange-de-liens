<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Session;

/**
 * Middleware CSRF
 * Vérifie le jeton CSRF sur les requêtes POST/PUT/DELETE
 */
class CsrfMiddleware implements MiddlewareInterface
{
    public function handle(Request $request): bool
    {
        if (in_array($request->getMethod(), ['POST', 'PUT', 'DELETE'])) {
            $token = $request->post('_csrf_token', '');
            if (!Session::verifyCsrf($token)) {
                Session::setFlash('error', 'Jeton CSRF invalide. Veuillez réessayer.');
                // Ne jamais rediriger vers HTTP_REFERER : en-tête contrôlable par l'attaquant (open redirect)
                header('Location: /');
                exit(302);
            }
        }
        return true;
    }
}
