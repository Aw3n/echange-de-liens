<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Database;
use App\Services\VisitService;
use App\Repositories\LinkRepository;

/**
 * Contrôleur du système de visite (viewer)
 */
class VisitController extends Controller
{
    private VisitService $visitService;

    public function __construct()
    {
        $this->visitService = new VisitService();
    }

    /**
     * Redirection vers la visionneuse (go.php?id=X)
     */
    public function go(Request $request, Response $response): never
    {
        $linkId = (int) $request->get('id', '0');

        if ($linkId <= 0) {
            $this->redirectWithError('/', 'Lien invalide.');
        }

        $user = Session::user();
        $visitorId = $user ? (int) $user['id'] : null;

        $result = $this->visitService->startVisit(
            $linkId,
            $visitorId,
            $request->getIp(),
            $request->getUserAgent()
        );

        if (!$result['success']) {
            $this->redirectWithError('/', $result['message']);
        }

        // Affiche la visionneuse
        $this->view('pages.visit.viewer', [
            'title' => 'Visite en cours...',
            'link' => $result['link'],
            'visit_id' => $result['visit_id'],
            'duration' => $result['duration'],
            'session_token' => $result['session_token'],
            'viewer_ads' => viewer_ads(),
            'page' => 'viewer',
        ], null); // Pas de layout pour la visionneuse
    }

    /**
     * Validation de la visite (AJAX)
     */
    public function validate(Request $request, Response $response): never
    {
        $visitId = (int) $request->post('visit_id', '0');
        $duration = (int) $request->post('duration', '0');
        $sessionToken = (string) $request->post('session_token', '');

        if ($visitId <= 0) {
            $this->json(['success' => false, 'message' => 'Visite invalide.']);
        }

        $result = $this->visitService->validateVisit($visitId, $duration, $sessionToken);
        $this->json($result);
    }

    /**
     * Validation clic bannière bonus (AJAX)
     */
    public function validateBanner(Request $request, Response $response): never
    {
        $adId = (int) $request->post('ad_id', '0');
        $duration = (int) $request->post('duration', '0');

        $user = Session::user();
        $visitorId = $user ? (int) $user['id'] : null;

        $result = $this->visitService->validateBannerClick(
            $adId,
            $visitorId,
            $request->getIp(),
            $duration
        );

        $this->json($result);
    }

    /**
     * Visionneuse bannière bonus
     */
    public function bonusViewer(Request $request, Response $response): never
    {
        $adId = (int) $request->get('id', '0');
        $db = Database::getInstance();

        $ad = $db->queryOne("SELECT * FROM ads WHERE id = ? AND is_active = 1 AND is_approved = 1", [$adId]);

        if (!$ad) {
            $this->redirectWithError('/bonus', 'Bannière non disponible.');
        }

        // Budget épuisé : la bannière ne distribue plus de points
        $perClick = (int) \App\Core\Config::get('app.points.per_banner_click', 5);
        if ((int) $ad['points_assigned'] < $perClick) {
            $this->redirectWithError('/bonus', 'Le budget de cette bannière est épuisé.');
        }

        // Le compteur de clics est incrémenté uniquement lors de la
        // validation (validateBannerClick), pas à l'ouverture du viewer.
        $this->view('pages.visit.bonus_viewer', [
            'title' => 'Bonus - Visite en cours...',
            'ad' => $ad,
            'duration' => (int) \App\Core\Config::get('app.points.bonus_visit_duration', 15),
            'page' => 'bonus-viewer',
        ], null);
    }
}
