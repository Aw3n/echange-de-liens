<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Database;

/**
 * Contrôleur de la page Bonus
 */
class BonusController extends Controller
{
    /**
     * Page Bonus - Bannières
     */
    public function index(Request $request, Response $response): never
    {
        $db = Database::getInstance();

        // Bannières actives et approuvées disposant encore d'un budget :
        // une bannière épuisée (0 point) disparaît de la page Bonus
        // (valable aussi pour les bannières créées par l'admin).
        $banners = $db->query(
            "SELECT a.*, u.username
             FROM ads a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE a.is_active = 1
               AND a.is_approved = 1
               AND a.points_assigned > 0
               AND a.type = 'banner'
             ORDER BY a.points_assigned DESC, RAND()"
        );

        $this->view('pages.bonus', [
            'title' => 'Page Bonus - Gagnez 5 points par clic',
            'banners' => $banners,
            'page' => 'bonus',
        ]);
    }

    /**
     * Formulaire d'ajout de bannière
     */
    public function addForm(Request $request, Response $response): never
    {
        $this->requireAuth($request);

        $this->view('pages.bonus_add', [
            'title' => 'Ajouter une bannière',
            'page' => 'bonus-add',
        ]);
    }

    /**
     * Traitement d'ajout de bannière
     */
    public function add(Request $request, Response $response): never
    {
        $this->requireAuth($request);
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/bonus/add', 'CSRF invalide.');
        }

        $user = Session::user();
        $targetUrl = trim($request->post('target_url', ''));
        $imageUrl = trim($request->post('image_url', ''));
        $points = (int) $request->post('points', '0');
        $title = trim($request->post('title', ''));

        // Validation
        if (!filter_var($targetUrl, FILTER_VALIDATE_URL) || !str_starts_with($targetUrl, 'https://')) {
            $this->redirectWithError('/bonus/add', 'URL cible invalide (HTTPS requis).');
        }

        // Protection SSRF : l'URL cible est chargée dans une iframe côté client,
        // mais on refuse quand même les hôtes internes/réservés
        if (!url_points_to_public_host($targetUrl)) {
            $this->redirectWithError('/bonus/add', 'Cette URL cible n\'est pas autorisée.');
        }

        if (!filter_var($imageUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $imageUrl)) {
            $this->redirectWithError('/bonus/add', 'URL de la bannière invalide.');
        }

        if ($points <= 0) {
            $this->redirectWithError('/bonus/add', 'Vous devez attribuer des points à votre bannière.');
        }

        // Vérifie et débite les points de l'utilisateur (UPDATE atomique conditionnel :
        // impossible de passer en négatif, même avec des requêtes concurrentes)
        $db = Database::getInstance();
        $debited = $db->execute(
            "UPDATE users SET points = points - ? WHERE id = ? AND points >= ?",
            [$points, (int) $user['id'], $points]
        );

        if ($debited === 0) {
            $this->redirectWithError('/bonus/add', 'Points insuffisants.');
        }

        // Crée la bannière (en attente d'approbation admin)
        $db->insert('ads', [
            'user_id' => (int) $user['id'],
            'title' => $title ?: 'Bannière',
            'type' => 'banner',
            'image_url' => $imageUrl,
            'target_url' => $targetUrl,
            'position' => 'bottom',
            'is_active' => 0,
            'is_approved' => 0,
            'points_assigned' => $points,
            'width' => 468,
            'height' => 60,
        ]);

        // Historique des points
        $db->insert('points_history', [
            'user_id' => (int) $user['id'],
            'amount' => -$points,
            'type' => 'spend_visit',
            'description' => "Points attribués à une bannière (en attente d'approbation)",
        ]);

        // Notification admin
        $db->insert('notifications', [
            'user_id' => 1,
            'type' => 'banner_pending',
            'title' => 'Nouvelle bannière à approuver',
            'message' => "{$user['username']} a soumis une bannière avec {$points} points.",
        ]);

        $this->redirectWithSuccess('/bonus', "Bannière soumise ! Elle sera activée après approbation de l'administrateur.");
    }
}
