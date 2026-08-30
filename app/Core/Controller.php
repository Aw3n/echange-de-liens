<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Contrôleur de base
 * Fournit des méthodes utilitaires pour tous les contrôleurs
 */
abstract class Controller
{
    /**
     * Rendu d'une vue
     */
    protected function view(string $view, array $data = [], ?string $layout = 'layouts/main'): never
    {
        $response = new Response();
        $response->html(View::render($view, $data, $layout));
    }

    /**
     * Réponse JSON
     */
    protected function json(mixed $data, int $status = 200): never
    {
        $response = new Response();
        $response->json($data, $status);
    }

    /**
     * Redirection
     */
    protected function redirect(string $url, int $status = 302): never
    {
        $response = new Response();
        $response->redirect($url, $status);
    }

    /**
     * Redirection avec message flash de succès
     */
    protected function redirectWithSuccess(string $url, string $message): never
    {
        Session::setFlash('success', $message);
        $this->redirect($url);
    }

    /**
     * Redirection avec message flash d'erreur
     */
    protected function redirectWithError(string $url, string $message): never
    {
        Session::setFlash('error', $message);
        $this->redirect($url);
    }

    /**
     * Vérifie la validité du jeton CSRF
     */
    protected function verifyCsrf(Request $request): bool
    {
        $token = $request->post('_csrf_token', '');
        return Session::verifyCsrf($token);
    }

    /**
     * Exige que l'utilisateur soit connecté
     */
    protected function requireAuth(Request $request): void
    {
        if (!Session::isLoggedIn()) {
            Session::setFlash('error', 'Vous devez être connecté pour accéder à cette page.');
            $this->redirect('/login');
        }
    }

    /**
     * Exige un rôle admin
     */
    protected function requireAdmin(Request $request): void
    {
        $this->requireAuth($request);
        $user = Session::user();
        if (!$user || ($user['role_slug'] ?? '') !== 'admin') {
            $this->redirectWithError('/', 'Accès refusé.');
        }
    }
}
