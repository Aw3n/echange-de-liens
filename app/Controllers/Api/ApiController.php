<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\Session;
use App\Repositories\LinkRepository;
use App\Repositories\VisitRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\LinkService;
use App\Services\VisitService;

/**
 * Contrôleur API REST
 * Fournit des endpoints JSON
 */
class ApiController extends Controller
{
    /**
     * GET /api/links - Liste des liens (classement)
     */
    public function getLinks(Request $request, Response $response): never
    {
        $linkRepo = new LinkRepository();
        $page = max(1, (int) $request->get('page', '1'));
        $limit = min(100, max(1, (int) $request->get('limit', '50')));

        $links = $linkRepo->getRanking($limit, ($page - 1) * $limit);
        $total = $linkRepo->count();

        $this->json([
            'data' => $links,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => (int) ceil($total / $limit),
            ],
        ]);
    }

    /**
     * POST /api/links - Créer un lien
     */
    public function createLink(Request $request, Response $response): never
    {
        $userId = $request->getParam('api_user_id');
        if (!$userId) {
            $this->json(['error' => 'Authentification requise'], 401);
        }

        $data = $request->getJsonBody();
        $url = $data['url'] ?? '';
        $title = $data['title'] ?? null;

        $linkService = new LinkService();
        $result = $linkService->addLink((int) $userId, $url, $title);

        $status = $result['success'] ? 201 : 400;
        $this->json($result, $status);
    }

    /**
     * PUT /api/links/{id} - Mettre à jour un lien
     */
    public function updateLink(Request $request, Response $response): never
    {
        $userId = $request->getParam('api_user_id');
        $linkId = (int) $request->getParam('id');

        $linkRepo = new LinkRepository();
        $link = $linkRepo->findById($linkId);

        if (!$link || (int) $link['user_id'] !== (int) $userId) {
            $this->json(['error' => 'Lien non trouvé'], 404);
        }

        $data = $request->getJsonBody();
        $updateData = [];

        if (isset($data['title'])) $updateData['title'] = $data['title'];
        if (isset($data['description'])) $updateData['description'] = $data['description'];

        if (empty($updateData)) {
            $this->json(['error' => 'Aucune donnée à mettre à jour'], 400);
        }

        $linkRepo->update($linkId, $updateData);
        $this->json(['success' => true, 'message' => 'Lien mis à jour']);
    }

    /**
     * DELETE /api/links/{id} - Supprimer un lien
     */
    public function deleteLink(Request $request, Response $response): never
    {
        $userId = (int) $request->getParam('api_user_id');
        $linkId = (int) $request->getParam('id');

        $linkService = new LinkService();
        $result = $linkService->deleteLink($linkId, $userId);

        if ($result) {
            $this->json(['success' => true, 'message' => 'Lien supprimé']);
        }
        $this->json(['error' => 'Impossible de supprimer ce lien'], 400);
    }

    /**
     * GET /api/ranking - Classement
     */
    public function getRanking(Request $request, Response $response): never
    {
        $linkRepo = new LinkRepository();
        $limit = min(100, max(1, (int) $request->get('limit', '50')));
        $links = $linkRepo->getRanking($limit, 0);

        $this->json(['data' => $links]);
    }

    /**
     * GET /api/stats - Statistiques
     */
    public function getStats(Request $request, Response $response): never
    {
        $db = Database::getInstance();
        $visitRepo = new VisitRepository();

        $this->json([
            'data' => [
                'total_links' => $db->count('links'),
                'total_users' => $db->count('users'),
                'total_visits' => $visitRepo->countTotal(),
                'visits_today' => $visitRepo->countToday(),
            ],
        ]);
    }

    /**
     * POST /api/visit - Démarrer une visite
     */
    public function startVisit(Request $request, Response $response): never
    {
        $data = $request->getJsonBody();
        $linkId = (int) ($data['link_id'] ?? 0);

        if ($linkId <= 0) {
            $this->json(['error' => 'link_id requis'], 400);
        }

        $userId = $request->getParam('api_user_id');
        $visitService = new VisitService();

        $result = $visitService->startVisit(
            $linkId,
            $userId ? (int) $userId : null,
            $request->getIp(),
            $request->getUserAgent()
        );

        $status = $result['success'] ? 200 : 400;
        $this->json($result, $status);
    }

    /**
     * POST /api/login - Connexion
     */
    public function login(Request $request, Response $response): never
    {
        $data = $request->getJsonBody();
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        $authService = new AuthService();
        $user = $authService->login($email, $password, $request->getIp(), $request->getUserAgent());

        if (!$user) {
            $this->json(['error' => 'Identifiants incorrects'], 401);
        }

        // Génère un token API
        $token = $authService->generateApiToken((int) $user['id'], 'api_login');

        $this->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => (int) $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'points' => (int) $user['points'],
            ],
        ]);
    }

    /**
     * POST /api/register - Inscription
     */
    public function register(Request $request, Response $response): never
    {
        $data = $request->getJsonBody();

        $authService = new AuthService();

        $username = trim((string) ($data['username'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        // Validation (mêmes règles que l'inscription web)
        if ($username === '' || $email === '' || $password === '') {
            $this->json(['error' => 'Tous les champs sont requis'], 400);
        }

        if (!preg_match('/^[a-zA-Z0-9_.-]{3,30}$/', $username)) {
            $this->json(['error' => "Nom d'utilisateur invalide (3 à 30 caractères : lettres, chiffres, tirets, points)"], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['error' => 'Adresse email invalide'], 400);
        }

        if (strlen($password) < 8) {
            $this->json(['error' => 'Le mot de passe doit contenir au moins 8 caractères'], 400);
        }

        if ($authService->emailExists($email)) {
            $this->json(['error' => 'Email déjà utilisé'], 400);
        }

        if ($authService->usernameExists($username)) {
            $this->json(['error' => "Nom d'utilisateur déjà pris"], 400);
        }

        // Anti-fraude : limiter le nombre d'inscriptions par IP sur 24h
        $userRepo = new UserRepository();
        $maxRegistrations = (int) \App\Core\Config::get('app.security.max_registrations_per_ip', 3);
        if ($userRepo->countRegistrationsByIp($request->getIp()) >= $maxRegistrations) {
            $this->json(['error' => 'Trop d\'inscriptions depuis cette adresse IP'], 429);
        }

        $userId = $authService->register([
            'username' => $username,
            'email' => $email,
            'password' => $password,
        ], null, $request->getIp());
        $token = $authService->generateApiToken($userId, 'api_register');

        $this->json([
            'success' => true,
            'token' => $token,
            'user_id' => $userId,
        ], 201);
    }
}
