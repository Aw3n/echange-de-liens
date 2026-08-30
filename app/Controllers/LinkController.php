<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\LinkService;
use App\Repositories\LinkRepository;

/**
 * Contrôleur de gestion des liens
 */
class LinkController extends Controller
{
    private LinkService $linkService;
    private LinkRepository $linkRepo;

    public function __construct()
    {
        $this->linkService = new LinkService();
        $this->linkRepo = new LinkRepository();
    }

    /**
     * Page d'ajout de lien
     */
    public function addForm(Request $request, Response $response): never
    {
        $user = Session::user();
        $this->view('pages.links.add', [
            'title' => 'Ajouter un lien',
            'page' => 'add-link',
            'user_points' => $user ? (int) ($user['points'] ?? 0) : 0,
        ]);
    }

    /**
     * Traitement de l'ajout de lien
     */
    public function add(Request $request, Response $response): never
    {
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/links/add', 'Jeton CSRF invalide.');
        }

        $user = Session::user();
        if (!$user) {
            $this->redirectWithError('/login', 'Connectez-vous pour ajouter un lien.');
        }

        $url = trim($request->post('url', ''));
        $title = trim($request->post('title', ''));
        $points = max(0, (int) $request->post('points', '0'));

        if (empty($url)) {
            $this->redirectWithError('/links/add', 'Veuillez saisir une URL.');
        }

        if ($points > 0 && $points > (int) ($user['points'] ?? 0)) {
            $this->redirectWithError('/links/add', 'Points insuffisants : vous ne pouvez attribuer que les points que vous possédez.');
        }

        $result = $this->linkService->addLink((int) $user['id'], $url, $title ?: null);

        if (!$result['success']) {
            $this->redirectWithError('/links/add', $result['message']);
        }

        $message = $result['message'];
        if ($points > 0) {
            $assign = $this->linkService->assignPoints((int) $result['link_id'], (int) $user['id'], $points);
            $message .= ' ' . ($assign['success']
                ? $assign['message']
                : 'Attention : les points n\'ont pas pu être attribués (' . ($assign['message'] ?? 'erreur inconnue') . ').');
        }

        $this->redirectWithSuccess('/dashboard', $message);
    }

    /**
     * Page du classement
     */
    public function ranking(Request $request, Response $response): never
    {
        $page = max(1, (int) $request->get('page', '1'));
        $user = Session::user();
        $viewerId = $user ? (int) $user['id'] : null;
        $ranking = $this->linkService->getRanking($page, 100, $viewerId, $request->getIp());

        $this->view('pages.links.ranking', [
            'title' => 'Classement',
            'ranking' => $ranking,
            'page' => 'ranking',
        ]);
    }

    /**
     * Suppression d'un lien
     */
    public function delete(Request $request, Response $response): never
    {
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/dashboard', 'Jeton CSRF invalide.');
        }

        $user = Session::user();
        if (!$user) {
            $this->redirect('/login');
        }

        $linkId = (int) $request->getParam('id');
        $isAdmin = ($user['role_slug'] ?? '') === 'admin';

        if ($this->linkService->deleteLink($linkId, (int) $user['id'], $isAdmin)) {
            $this->redirectWithSuccess('/dashboard', 'Lien supprimé et points remboursés.');
        }

        $this->redirectWithError('/dashboard', 'Impossible de supprimer ce lien.');
    }

    /**
     * Attribution de points à un lien
     */
    public function assignPoints(Request $request, Response $response): never
    {
        if (!$this->verifyCsrf($request)) {
            $this->json(['success' => false, 'message' => 'CSRF invalide'], 403);
        }

        $user = Session::user();
        if (!$user) {
            $this->json(['success' => false, 'message' => 'Non connecté'], 401);
        }

        $linkId = (int) $request->post('link_id');
        $points = (int) $request->post('points');

        if ($points <= 0) {
            $this->json(['success' => false, 'message' => 'Points invalides']);
        }

        $result = $this->linkService->assignPoints($linkId, (int) $user['id'], $points);

        if ($request->isAjax()) {
            $this->json($result);
        }

        if ($result['success']) {
            $this->redirectWithSuccess('/dashboard', $result['message']);
        }
        $this->redirectWithError('/dashboard', $result['message']);
    }

    /**
     * Liste des liens (mes liens)
     */
    public function myList(Request $request, Response $response): never
    {
        $user = Session::user();
        if (!$user) {
            $this->redirect('/login');
        }

        $page = max(1, (int) $request->get('page', '1'));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $links = $this->linkRepo->findByUserId((int) $user['id'], $perPage, $offset);
        $total = $this->linkRepo->countByUserId((int) $user['id']);

        $this->view('pages.links.mylist', [
            'title' => 'Mes liens',
            'links' => $links,
            'total' => $total,
            'pages' => (int) ceil($total / $perPage),
            'current_page' => $page,
            'page' => 'my-links',
        ]);
    }

    /**
     * Signaler un lien
     */
    public function report(Request $request, Response $response): never
    {
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/', 'CSRF invalide.');
        }

        $linkId = (int) $request->post('link_id');
        $reason = trim($request->post('reason', ''));
        $user = Session::user();

        $db = \App\Core\Database::getInstance();
        $db->insert('reports', [
            'link_id' => $linkId,
            'reporter_id' => $user ? (int) $user['id'] : null,
            'reason' => $reason ?: 'Signalement utilisateur',
            'status' => 'pending',
        ]);

        $this->redirectWithSuccess('/', 'Signalement envoyé. Merci !');
    }
}
