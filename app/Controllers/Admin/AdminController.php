<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Repositories\UserRepository;
use App\Repositories\LinkRepository;
use App\Repositories\VisitRepository;
use App\Services\LinkService;
use App\Services\XelisService;
use App\Services\KaspaService;
use App\Services\FiroService;
use App\Services\VergeService;
use App\Services\PepecoinService;
use App\Services\VertcoinService;
use App\Services\DragonxService;
use App\Services\MoneroService;
use App\Services\AffiliateService;

/**
 * Contrôleur du panneau d'administration
 */
class AdminController extends Controller
{
    /**
     * Dashboard admin
     */
    public function index(Request $request, Response $response): never
    {
        $this->requireAdmin($request);
        $db = Database::getInstance();

        $stats = [
            'total_users' => $db->count('users'),
            'total_links' => $db->count('links'),
            'total_visits' => $db->count('link_visits'),
            'pending_reports' => $db->count('reports', "status = 'pending'"),
            'pending_banners' => $db->count('ads', "is_approved = 0"),
            'total_banners' => $db->count('ads'),
            'pending_purchases' => $db->count('purchases', "status = 'pending'"),
        ];

        $recentLogs = $db->query("SELECT * FROM logs ORDER BY created_at DESC LIMIT 20");
        $recentUsers = $db->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 10");

        $this->view('admin.dashboard', [
            'title' => 'Administration',
            'stats' => $stats,
            'recent_logs' => $recentLogs,
            'recent_users' => $recentUsers,
            'page' => 'admin',
        ]);
    }

    /**
     * Gestion des utilisateurs
     */
    public function users(Request $request, Response $response): never
    {
        $this->requireAdmin($request);
        $page = max(1, (int) $request->get('page', '1'));
        $perPage = 30;

        $userRepo = new UserRepository();
        $users = $userRepo->findAll($perPage, ($page - 1) * $perPage);
        $total = $userRepo->count();

        $this->view('admin.users', [
            'title' => 'Gestion des utilisateurs',
            'users' => $users,
            'total' => $total,
            'pages' => (int) ceil($total / $perPage),
            'current_page' => $page,
            'page' => 'admin-users',
        ]);
    }

    /**
     * Modifier un utilisateur
     */
    public function editUser(Request $request, Response $response): never
    {
        $this->requireAdmin($request);
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/admin/users', 'CSRF invalide.');
        }

        $userId = (int) $request->post('user_id');
        $points = (int) $request->post('points');
        $roleId = (int) $request->post('role_id');
        $isActive = (int) $request->post('is_active', '1');

        $userRepo = new UserRepository();
        $userRepo->update($userId, [
            'points' => $points,
            'role_id' => $roleId,
            'is_active' => $isActive,
        ]);

        $this->redirectWithSuccess('/admin/users', 'Utilisateur mis à jour.');
    }

    /**
     * Attribue (ou retire) des points à un utilisateur : crédit/débit
     * relatif avec plancher à zéro, historique et notification.
     */
    public function addPoints(Request $request, Response $response): never
    {
        $this->requireAdmin($request);
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/admin/users', 'CSRF invalide.');
        }

        $userId = (int) $request->post('user_id');
        $amount = (int) $request->post('amount', '0');

        if ($userId <= 0) {
            $this->redirectWithError('/admin/users', 'Utilisateur invalide.');
        }
        if ($amount === 0) {
            $this->redirectWithError('/admin/users', 'Saisissez un nombre de points différent de zéro.');
        }

        $db = Database::getInstance();
        $user = $db->queryOne("SELECT id, username, points FROM users WHERE id = ?", [$userId]);
        if (!$user) {
            $this->redirectWithError('/admin/users', 'Utilisateur non trouvé.');
        }

        // Débit borné à zéro : le solde ne passe jamais en négatif
        $before = (int) $user['points'];
        $after = max(0, $before + $amount);
        $delta = $after - $before;

        if ($delta !== 0) {
            $db->execute("UPDATE users SET points = ? WHERE id = ?", [$after, $userId]);
            $db->insert('points_history', [
                'user_id' => $userId,
                'amount' => $delta,
                'type' => 'admin_adjust',
                'description' => 'Attribution manuelle par un administrateur',
            ]);
            $db->insert('notifications', [
                'user_id' => $userId,
                'type' => 'points_adjusted',
                'title' => $delta > 0 ? 'Points crédités' : 'Points débités',
                'message' => 'Un administrateur a ' . ($delta > 0 ? "crédité {$delta}" : 'débité ' . abs($delta)) . " points sur votre compte. Nouveau solde : {$after}.",
            ]);
        }

        $this->redirectWithSuccess('/admin/users', "{$delta} point(s) appliqué(s) à {$user['username']}. Nouveau solde : {$after}.");
    }

    /**
     * Gestion des liens
     */
    public function links(Request $request, Response $response): never
    {
        $this->requireAdmin($request);

        // 20 liens au chargement initial, le reste via « Charger plus »
        $linkRepo = new LinkRepository();
        $links = $linkRepo->findAll(20, 0);
        $total = $linkRepo->count();

        // Liste des utilisateurs pour le formulaire d'ajout de lien
        $db = Database::getInstance();
        $users = $db->query("SELECT id, username FROM users ORDER BY username ASC LIMIT 200");

        $this->view('admin.links', [
            'title' => 'Gestion des liens',
            'links' => $links,
            'total' => $total,
            'users' => $users,
            'page' => 'admin-links',
        ]);
    }

    /**
     * Pagination de la liste des liens admin (bouton « Charger plus ») :
     * renvoie le lot de lignes suivant déjà rendu en HTML (partial).
     */
    public function linksMore(Request $request, Response $response): never
    {
        $this->requireAdmin($request);

        $limit = 20;
        $offset = max(0, (int) $request->get('offset', 0));

        $linkRepo = new LinkRepository();
        $rows = $linkRepo->findAll($limit, $offset);
        $loaded = $offset + count($rows);

        $response->json([
            'success' => true,
            'html' => View::renderPartial('partials.admin_link_rows', ['links' => $rows]),
            'next_offset' => $loaded,
            'has_more' => $loaded < $linkRepo->count(),
        ]);
    }

    /**
     * Ajouter un lien avec un nombre de points
     */
    public function addLink(Request $request, Response $response): never
    {
        $this->requireAdmin($request);
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/admin/links', 'CSRF invalide.');
        }

        $url = trim($request->post('url', ''));
        $title = trim($request->post('title', ''));
        $username = trim($request->post('username', ''));
        $points = max(0, (int) $request->post('points', '0'));

        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            $this->redirectWithError('/admin/links', 'URL invalide.');
        }

        // Propriétaire du lien : utilisateur choisi, sinon l'admin connecté
        $userId = (int) Session::user()['id'];
        if ($username !== '') {
            $userRepo = new UserRepository();
            $target = $userRepo->findByUsername($username);
            if (!$target) {
                $this->redirectWithError('/admin/links', "Utilisateur « {$username} » introuvable.");
            }
            $userId = (int) $target['id'];
        }

        $db = Database::getInstance();
        $existing = $db->queryOne("SELECT id FROM links WHERE url = ? AND user_id = ?", [$url, $userId]);
        if ($existing) {
            $this->redirectWithError('/admin/links', 'Ce lien existe déjà pour cet utilisateur.');
        }

        $linkRepo = new LinkRepository();
        $linkId = $linkRepo->create([
            'user_id' => $userId,
            'url' => $url,
            'title' => $title !== '' ? $title : null,
            'points' => $points,
        ]);

        $db->insert('logs', [
            'level' => 'info',
            'channel' => 'admin',
            'message' => "Lien #{$linkId} créé avec {$points} points",
            'user_id' => (int) Session::user()['id'],
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->redirectWithSuccess('/admin/links', "Lien ajouté avec {$points} points.");
    }

    /**
     * Modifier un lien : points, statut, blacklist — et en édition
     * complète, l'URL et le titre (champs absents du formulaire inline).
     */
    public function editLink(Request $request, Response $response): never
    {
        $this->requireAdmin($request);
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/admin/links', 'CSRF invalide.');
        }

        $linkId = (int) $request->post('link_id');
        $points = max(0, (int) $request->post('points', '0'));
        $isActive = (int) $request->post('is_active', '1');
        $isBlacklisted = (int) $request->post('is_blacklisted', '0');

        $data = [
            'points' => $points,
            'is_active' => $isActive,
            'is_blacklisted' => $isBlacklisted,
        ];

        // Édition complète : l'URL est fournie → valider et mettre à jour
        $url = trim((string) $request->post('url', ''));
        if ($url !== '') {
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                $this->redirectWithError('/admin/links', 'URL invalide.');
            }

            $db = Database::getInstance();
            $link = $db->queryOne("SELECT * FROM links WHERE id = ?", [$linkId]);
            if (!$link) {
                $this->redirectWithError('/admin/links', 'Lien introuvable.');
            }

            $dup = $db->queryOne(
                "SELECT id FROM links WHERE url = ? AND user_id = ? AND id <> ?",
                [$url, (int) $link['user_id'], $linkId]
            );
            if ($dup) {
                $this->redirectWithError('/admin/links', 'Cet utilisateur a déjà un lien avec cette URL.');
            }

            $title = trim((string) $request->post('title', ''));
            $data['url'] = $url;
            $data['title'] = $title !== '' ? $title : null;
        }

        $linkRepo = new LinkRepository();
        $linkRepo->update($linkId, $data);

        $this->redirectWithSuccess('/admin/links', 'Lien mis à jour.');
    }

    /**
     * Supprimer un lien (les points restants sont remboursés au propriétaire)
     */
    public function deleteLink(Request $request, Response $response): never
    {
        $this->requireAdmin($request);
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/admin/links', 'CSRF invalide.');
        }

        $linkId = (int) $request->post('link_id');
        $adminId = (int) Session::user()['id'];

        $linkService = new LinkService();
        if ($linkService->deleteLink($linkId, $adminId, true)) {
            $db = Database::getInstance();
            $db->insert('logs', [
                'level' => 'info',
                'channel' => 'admin',
                'message' => "Lien #{$linkId} supprimé par l'admin (points restants remboursés)",
                'user_id' => $adminId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $this->redirectWithSuccess('/admin/links', "Lien #{$linkId} supprimé. Points restants remboursés au propriétaire.");
        }

        $this->redirectWithError('/admin/links', 'Impossible de supprimer ce lien (introuvable ?).');
    }

    /**
     * Gestion des signalements
     */
    public function reports(Request $request, Response $response): never
    {
        $this->requireAdmin($request);
        $db = Database::getInstance();

        $reports = $db->query(
            "SELECT r.*, l.url as link_url, u.username as reporter_name
             FROM reports r
             LEFT JOIN links l ON l.id = r.link_id
             LEFT JOIN users u ON u.id = r.reporter_id
             ORDER BY r.created_at DESC"
        );

        $this->view('admin.reports', [
            'title' => 'Signalements',
            'reports' => $reports,
            'page' => 'admin-reports',
        ]);
    }

    /**
     * Traiter un signalement
     */
    public function resolveReport(Request $request, Response $response): never
    {
        $this->requireAdmin($request);
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/admin/reports', 'CSRF invalide.');
        }

        $reportId = (int) $request->post('report_id');
        $action = $request->post('action'); // blacklist, dismiss, remove_link
        $db = Database::getInstance();
        $adminId = (int) Session::user()['id'];

        $report = $db->queryOne("SELECT * FROM reports WHERE id = ?", [$reportId]);

        if ($action === 'blacklist' && $report) {
            $link = $db->queryOne("SELECT * FROM links WHERE id = ?", [(int) $report['link_id']]);
            if ($link) {
                $db->insert('blacklist', [
                    'url_pattern' => parse_url($link['url'], PHP_URL_HOST) ?: $link['url'],
                    'reason' => 'Signalé et blacklisté par admin',
                    'added_by' => $adminId,
                ]);
                $db->execute("UPDATE links SET is_blacklisted = 1 WHERE id = ?", [(int) $report['link_id']]);
            }
        }

        if ($action === 'remove_link' && $report) {
            $db->execute("UPDATE links SET is_active = 0 WHERE id = ?", [(int) $report['link_id']]);
        }

        $db->update('reports', [
            'status' => 'resolved',
            'admin_notes' => $request->post('notes', ''),
            'resolved_by' => $adminId,
            'resolved_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$reportId]);

        $this->redirectWithSuccess('/admin/reports', 'Signalement traité.');
    }

    /**
     * Gestion des bannières
     */
    public function banners(Request $request, Response $response): never
    {
        $this->requireAdmin($request);
        $db = Database::getInstance();

        $banners = $db->query(
            "SELECT a.*, u.username FROM ads a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.created_at DESC"
        );

        // Liste des utilisateurs pour le formulaire d'ajout
        $users = $db->query("SELECT id, username FROM users ORDER BY username ASC LIMIT 200");

        $this->view('admin.banners', [
            'title' => 'Gestion des bannières',
            'banners' => $banners,
            'users' => $users,
            'page' => 'admin-banners',
        ]);
    }

    /**
     * Ajouter une bannière (publiée immédiatement)
     */
    public function addBanner(Request $request, Response $response): never
    {
        $this->requireAdmin($request);
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/admin/banners', 'CSRF invalide.');
        }

        $title = trim($request->post('title', ''));
        $type = $request->post('type', 'banner');
        $imageUrl = trim($request->post('image_url', ''));
        $targetUrl = trim($request->post('target_url', ''));
        $htmlCode = trim($request->post('html_code', ''));
        $position = $request->post('position', 'home_top');
        $points = max(0, (int) $request->post('points', '0'));
        $width = max(1, (int) $request->post('width', '468'));
        $height = max(1, (int) $request->post('height', '60'));
        $username = trim($request->post('username', ''));

        if ($title === '') {
            $this->redirectWithError('/admin/banners', 'Le titre est obligatoire.');
        }
        if (!in_array($type, ['banner', 'html'], true)) {
            $this->redirectWithError('/admin/banners', 'Type de bannière invalide.');
        }
        $positions = ['home_top', 'home_bottom', 'top', 'bottom', 'viewer_bottom'];
        if (!in_array($position, $positions, true)) {
            $this->redirectWithError('/admin/banners', 'Position invalide.');
        }

        if ($type === 'banner') {
            if (!filter_var($imageUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $imageUrl)) {
                $this->redirectWithError('/admin/banners', 'URL de l\'image invalide.');
            }
            if (!filter_var($targetUrl, FILTER_VALIDATE_URL) || !str_starts_with($targetUrl, 'https://')) {
                $this->redirectWithError('/admin/banners', 'URL cible invalide (HTTPS requis).');
            }
            // Protection SSRF : refus des hôtes internes/réservés
            if (!url_points_to_public_host($targetUrl)) {
                $this->redirectWithError('/admin/banners', 'Cette URL cible n\'est pas autorisée.');
            }
        } elseif ($htmlCode === '') {
            $this->redirectWithError('/admin/banners', 'Le code HTML est obligatoire pour une bannière HTML.');
        }

        // Propriétaire optionnel (sinon bannière « admin » sans utilisateur)
        $userId = null;
        if ($username !== '') {
            $userRepo = new UserRepository();
            $target = $userRepo->findByUsername($username);
            if (!$target) {
                $this->redirectWithError('/admin/banners', "Utilisateur « {$username} » introuvable.");
            }
            $userId = (int) $target['id'];
        }

        $db = Database::getInstance();

        // Si la bannière a un propriétaire et un budget : débités de cet
        // utilisateur (cohérent avec /bonus/add) ; le remboursement ultérieur
        // (refus/suppression) rendra ainsi exactement ce qui a été payé.
        if ($userId !== null && $points > 0) {
            $debited = $db->execute(
                "UPDATE users SET points = points - ? WHERE id = ? AND points >= ?",
                [$points, $userId, $points]
            );
            if ($debited === 0) {
                $this->redirectWithError('/admin/banners', 'Points insuffisants pour cet utilisateur.');
            }
            $db->insert('points_history', [
                'user_id' => $userId,
                'amount' => -$points,
                'type' => 'spend_visit',
                'description' => 'Points attribués à une bannière (création admin)',
            ]);
        }

        $db->insert('ads', [
            'user_id' => $userId,
            'title' => $title,
            'type' => $type,
            'image_url' => $type === 'banner' ? $imageUrl : null,
            'target_url' => $type === 'banner' ? $targetUrl : null,
            'html_code' => $type === 'html' ? $htmlCode : null,
            'position' => $position,
            'is_active' => 1,
            'is_approved' => 1,
            'points_assigned' => $points,
            'width' => $width,
            'height' => $height,
        ]);

        $this->redirectWithSuccess('/admin/banners', 'Bannière ajoutée et publiée.');
    }

    /**
     * Supprimer une bannière
     */
    public function deleteBanner(Request $request, Response $response): never
    {
        $this->requireAdmin($request);
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/admin/banners', 'CSRF invalide.');
        }

        $adId = (int) $request->post('ad_id');
        $db = Database::getInstance();

        // Rembourse le budget restant au propriétaire avant suppression
        $ad = $db->queryOne("SELECT * FROM ads WHERE id = ?", [$adId]);
        if ($ad) {
            $this->refundBannerPoints($ad);
        }

        $db->delete('ads', 'id = ?', [$adId]);

        $this->redirectWithSuccess('/admin/banners', 'Bannière supprimée. Points restants remboursés.');
    }

    /**
     * Modifie les points attribués à une bannière (édition inline)
     */
    public function updateBannerPoints(Request $request, Response $response): never
    {
        $this->requireAdmin($request);
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/admin/banners', 'CSRF invalide.');
        }

        $adId = (int) $request->post('ad_id');
        $points = max(0, (int) $request->post('points', '0'));
        $db = Database::getInstance();

        $updated = $db->update('ads', ['points_assigned' => $points], 'id = ?', [$adId]);
        if ($updated === 0) {
            $this->redirectWithError('/admin/banners', 'Bannière introuvable.');
        }

        $this->redirectWithSuccess('/admin/banners', "Points de la bannière #{$adId} mis à jour : {$points}.");
    }

    /**
     * Approuver/Refuser une bannière
     */
    public function approveBanner(Request $request, Response $response): never
    {
        $this->requireAdmin($request);
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/admin/banners', 'CSRF invalide.');
        }

        $adId = (int) $request->post('ad_id');
        $action = $request->post('action'); // approve, reject
        $db = Database::getInstance();

        if ($action === 'approve') {
            $db->update('ads', ['is_approved' => 1, 'is_active' => 1], 'id = ?', [$adId]);
            $msg = 'Bannière approuvée.';
        } else {
            // Rembourse les points restants au propriétaire (idempotent)
            $ad = $db->queryOne("SELECT * FROM ads WHERE id = ?", [$adId]);
            if ($ad) {
                $this->refundBannerPoints($ad);
            }
            $db->update('ads', ['is_approved' => 0, 'is_active' => 0], 'id = ?', [$adId]);
            $msg = 'Bannière refusée. Points remboursés.';
        }

        $this->redirectWithSuccess('/admin/banners', $msg);
    }

    /**
     * Rembourse le budget restant d'une bannière à son propriétaire
     * (une seule fois : vérification idempotente via points_history
     * pour empêcher un double remboursement).
     */
    private function refundBannerPoints(array $ad): void
    {
        if ($ad['user_id'] === null || (int) $ad['points_assigned'] <= 0) {
            return; // Bannière admin ou sans budget : rien à rembourser
        }

        $db = Database::getInstance();
        $alreadyRefunded = $db->queryOne(
            "SELECT id FROM points_history WHERE type = 'refund' AND reference_type = 'ad' AND reference_id = ?",
            [(int) $ad['id']]
        );
        if ($alreadyRefunded) {
            return;
        }

        $db->execute(
            "UPDATE users SET points = points + ? WHERE id = ?",
            [(int) $ad['points_assigned'], (int) $ad['user_id']]
        );
        $db->insert('points_history', [
            'user_id' => (int) $ad['user_id'],
            'amount' => (int) $ad['points_assigned'],
            'type' => 'refund',
            'description' => "Remboursement bannière refusée/supprimée #{$ad['id']}",
            'reference_id' => (int) $ad['id'],
            'reference_type' => 'ad',
        ]);

        // Informe le propriétaire du remboursement
        $db->insert('notifications', [
            'user_id' => (int) $ad['user_id'],
            'type' => 'banner_refunded',
            'title' => 'Bannière refusée ou supprimée',
            'message' => "Votre bannière #{$ad['id']} a été refusée ou supprimée par l'administrateur. " . (int) $ad['points_assigned'] . ' points ont été remboursés sur votre compte.',
        ]);
    }

    /**
     * Gestion des achats VIP
     */
    public function purchases(Request $request, Response $response): never
    {
        $this->requireAdmin($request);
        $db = Database::getInstance();

        $purchases = $db->query(
            "SELECT p.*, u.username, u.email
             FROM purchases p
             LEFT JOIN users u ON u.id = p.user_id
             ORDER BY p.created_at DESC"
        );

        $moneroExplorerUrl = '';
        $setting = $db->queryOne("SELECT setting_value FROM settings WHERE setting_key = 'monero_explorer_url'");
        if ($setting) {
            $moneroExplorerUrl = rtrim($setting['setting_value'], '/');
        }
        if (empty($moneroExplorerUrl)) {
            $moneroExplorerUrl = 'https://xmrchain.net';
        }

        $this->view('admin.purchases', [
            'title' => 'Achats VIP',
            'purchases' => $purchases,
            'monero_explorer_url' => $moneroExplorerUrl,
            'page' => 'admin-purchases',
        ]);
    }

    /**
     * Confirmer un achat
     */
    public function confirmPurchase(Request $request, Response $response): never
    {
        $this->requireAdmin($request);
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/admin/purchases', 'CSRF invalide.');
        }

        $purchaseId = (int) $request->post('purchase_id');
        $action = $request->post('action'); // confirm, cancel
        $db = Database::getInstance();

        $purchase = $db->queryOne("SELECT * FROM purchases WHERE id = ?", [$purchaseId]);

        if (!$purchase) {
            $this->redirectWithError('/admin/purchases', 'Achat non trouvé.');
        }

        // Empêcher de confirmer/annuler un achat déjà traité (anti double-crédit)
        if ($purchase['status'] !== 'pending') {
            $this->redirectWithError('/admin/purchases', 'Cet achat a déjà été traité (' . $purchase['status'] . ').');
        }

        if ($action === 'confirm') {
            $db->update('purchases', [
                'status' => 'completed',
                'confirmed_at' => date('Y-m-d H:i:s'),
                'admin_notes' => $request->post('notes', ''),
            ], 'id = ?', [$purchaseId]);

            // Crédite les points
            $db->execute("UPDATE users SET points = points + ? WHERE id = ?", [(int) $purchase['points_purchased'], (int) $purchase['user_id']]);

            $db->insert('points_history', [
                'user_id' => (int) $purchase['user_id'],
                'amount' => (int) $purchase['points_purchased'],
                'type' => 'purchase',
                'description' => "Achat VIP confirmé - {$purchase['points_purchased']} points",
                'reference_id' => $purchaseId,
                'reference_type' => 'purchase',
            ]);

            $msg = 'Achat confirmé et points crédités.';
        } else {
            $db->update('purchases', ['status' => 'cancelled'], 'id = ?', [$purchaseId]);
            $msg = 'Achat annulé.';
        }

        $this->redirectWithSuccess('/admin/purchases', $msg);
    }

    /**
     * Supprime une entrée de l'historique des achats (jamais un achat en attente)
     */
    public function deletePurchase(Request $request, Response $response): never
    {
        $this->requireAdmin($request);
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/admin/purchases', 'CSRF invalide.');
        }

        $purchaseId = (int) $request->post('purchase_id');
        $db = Database::getInstance();

        // Garde-fou : un achat « pending » peut correspondre à un paiement réel,
        // il doit être confirmé ou annulé, pas supprimé.
        $deleted = $db->execute(
            "DELETE FROM purchases WHERE id = ? AND status <> 'pending'",
            [$purchaseId]
        );

        if ($deleted > 0) {
            $this->redirectWithSuccess('/admin/purchases', "Achat #{$purchaseId} supprimé de l'historique.");
        }
        $this->redirectWithError('/admin/purchases', 'Suppression impossible (achat inexistant ou encore en attente).');
    }

    /**
     * Vide l'historique des achats (conserve les achats en attente)
     */
    public function clearPurchases(Request $request, Response $response): never
    {
        $this->requireAdmin($request);
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/admin/purchases', 'CSRF invalide.');
        }

        $db = Database::getInstance();
        $deleted = $db->execute("DELETE FROM purchases WHERE status <> 'pending'");

        $this->redirectWithSuccess('/admin/purchases', "Historique vidé : {$deleted} entrée(s) supprimée(s). Les achats en attente sont conservés.");
    }

    /**
     * Configuration du site
     */
    public function settings(Request $request, Response $response): never
    {
        $this->requireAdmin($request);
        $db = Database::getInstance();

        if ($request->isPost()) {
            if (!$this->verifyCsrf($request)) {
                $this->redirectWithError('/admin/settings', 'CSRF invalide.');
            }

            $settings = $request->all();
            unset($settings['_csrf_token'], $settings['_method']);

            // Validation de l'adresse Firo selon les règles propres au réseau
            // (base58check + préfixe) avant tout enregistrement
            $firoAddress = trim((string) ($settings['firo_address'] ?? ''));
            if ($firoAddress !== '') {
                $firo = new \App\Services\FiroService();
                if (!$firo->validateAddress($firoAddress)) {
                    $this->redirectWithError('/admin/settings', 'Adresse Firo invalide : adresse base58check attendue (préfixe « a » en mainnet, « 4 » pour P2SH).');
                }
            }

            // Validation de l'adresse Pepecoin : mainnet stricte (base58check +
            // préfixe P/A), une adresse de test est refusée avant enregistrement
            $pepecoinAddress = trim((string) ($settings['pepecoin_address'] ?? ''));
            if ($pepecoinAddress !== '') {
                $pepecoin = new \App\Services\PepecoinService();
                if (!$pepecoin->validateAddress($pepecoinAddress)) {
                    $this->redirectWithError('/admin/settings', 'Adresse Pepecoin invalide : adresse mainnet base58check attendue (préfixe « P », ou « A » pour P2SH).');
                }
            }

            // Validation de l'adresse Vertcoin : mainnet stricte (base58check +
            // préfixe V), une adresse de test est refusée avant enregistrement
            $vertcoinAddress = trim((string) ($settings['vertcoin_address'] ?? ''));
            if ($vertcoinAddress !== '') {
                $vertcoin = new \App\Services\VertcoinService();
                if (!$vertcoin->validateAddress($vertcoinAddress)) {
                    $this->redirectWithError('/admin/settings', 'Adresse Vertcoin invalide : adresse mainnet base58check attendue (préfixe « V »).');
                }
            }

            // Validation de l'adresse DragonX : mainnet transparente stricte
            // (base58check + préfixe R), une adresse shielded ou invalide
            // est refusée avant enregistrement
            $dragonxAddress = trim((string) ($settings['dragonx_address'] ?? ''));
            if ($dragonxAddress !== '') {
                $dragonx = new \App\Services\DragonxService();
                if (!$dragonx->validateAddress($dragonxAddress)) {
                    $this->redirectWithError('/admin/settings', 'Adresse DragonX invalide : adresse transparente mainnet base58check attendue (préfixe « R »).');
                }
            }

            foreach ($settings as $key => $value) {
                // Upsert atomique : un UPDATE « valeur identique » renvoie 0 ligne
                // modifiée, ce qui déclenchait à tort l'INSERT de secours (doublon).
                $db->execute(
                    "INSERT INTO settings (setting_key, setting_value, type, description)
                     VALUES (?, ?, 'string', 'Paramètre ajouté automatiquement')
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                    [$key, $value]
                );
            }

            // Cohérence : si HTTPS forcé, l'URL officielle du site passe en https://
            if (($settings['force_https'] ?? '0') === '1') {
                $db->execute(
                    "UPDATE settings SET setting_value = REPLACE(setting_value, 'http://', 'https://')
                     WHERE setting_key = 'site_url' AND setting_value LIKE 'http://%'",
                    []
                );
            }

            $this->redirectWithSuccess('/admin/settings', 'Paramètres sauvegardés.');
        }

        $settings = $db->query("SELECT * FROM settings ORDER BY setting_key");

        $this->view('admin.settings', [
            'title' => 'Configuration',
            'settings' => $settings,
            'page' => 'admin-settings',
        ]);
    }

    /**
     * Vérifier automatiquement les paiements XELIS en attente
     */
    public function checkXelisPayments(Request $request, Response $response): never
    {
        $this->requireAdmin($request);

        $xelisService = new XelisService();
        $results = $xelisService->checkAllPendingPayments();

        $msg = "Vérification XELIS terminée. ";
        $msg .= "Vérifiés: {$results['checked']}, ";
        $msg .= "Confirmés: {$results['confirmed']}, ";
        $msg .= "Expirés: {$results['expired']}";

        $this->redirectWithSuccess('/admin/purchases', $msg);
    }

    /**
     * Vérifier automatiquement les paiements Kaspa en attente
     */
    public function checkKaspaPayments(Request $request, Response $response): never
    {
        $this->requireAdmin($request);

        $kaspaService = new KaspaService();
        $results = $kaspaService->checkAllPendingPayments();

        $msg = "Vérification Kaspa terminée. ";
        $msg .= "Vérifiés: {$results['checked']}, ";
        $msg .= "Confirmés: {$results['confirmed']}, ";
        $msg .= "Expirés: {$results['expired']}";

        $this->redirectWithSuccess('/admin/purchases', $msg);
    }

    /**
     * Vérifier automatiquement les paiements Firo en attente
     */
    public function checkFiroPayments(Request $request, Response $response): never
    {
        $this->requireAdmin($request);

        $firoService = new FiroService();
        $results = $firoService->checkAllPendingPayments();

        $msg = "Vérification Firo terminée. ";
        $msg .= "Vérifiés: {$results['checked']}, ";
        $msg .= "Confirmés: {$results['confirmed']}, ";
        $msg .= "Expirés: {$results['expired']}";

        $this->redirectWithSuccess('/admin/purchases', $msg);
    }

    /**
     * Vérifier automatiquement les paiements Verge en attente
     */
    public function checkVergePayments(Request $request, Response $response): never
    {
        $this->requireAdmin($request);

        $vergeService = new VergeService();
        $results = $vergeService->checkAllPendingPayments();

        $msg = "Vérification Verge terminée. ";
        $msg .= "Vérifiés: {$results['checked']}, ";
        $msg .= "Confirmés: {$results['confirmed']}, ";
        $msg .= "Expirés: {$results['expired']}";

        $this->redirectWithSuccess('/admin/purchases', $msg);
    }

    /**
     * Vérifier automatiquement les paiements Pepecoin en attente
     */
    public function checkPepecoinPayments(Request $request, Response $response): never
    {
        $this->requireAdmin($request);

        $pepecoinService = new PepecoinService();
        $results = $pepecoinService->checkAllPendingPayments();

        $msg = "Vérification Pepecoin terminée. ";
        $msg .= "Vérifiés: {$results['checked']}, ";
        $msg .= "Confirmés: {$results['confirmed']}, ";
        $msg .= "Expirés: {$results['expired']}";

        $this->redirectWithSuccess('/admin/purchases', $msg);
    }

    /**
     * Vérifier automatiquement les paiements Vertcoin en attente
     */
    public function checkVertcoinPayments(Request $request, Response $response): never
    {
        $this->requireAdmin($request);

        $vertcoinService = new VertcoinService();
        $results = $vertcoinService->checkAllPendingPayments();

        $msg = "Vérification Vertcoin terminée. ";
        $msg .= "Vérifiés: {$results['checked']}, ";
        $msg .= "Confirmés: {$results['confirmed']}, ";
        $msg .= "Expirés: {$results['expired']}";

        $this->redirectWithSuccess('/admin/purchases', $msg);
    }

    /**
     * Vérifier automatiquement les paiements DragonX en attente
     */
    public function checkDragonxPayments(Request $request, Response $response): never
    {
        $this->requireAdmin($request);

        $dragonxService = new DragonxService();
        $results = $dragonxService->checkAllPendingPayments();

        $msg = "Vérification DragonX terminée. ";
        $msg .= "Vérifiés: {$results['checked']}, ";
        $msg .= "Confirmés: {$results['confirmed']}, ";
        $msg .= "Expirés: {$results['expired']}";

        $this->redirectWithSuccess('/admin/purchases', $msg);
    }

    /**
     * Vérifier automatiquement les paiements Monero en attente
     */
    public function checkMoneroPayments(Request $request, Response $response): never
    {
        $this->requireAdmin($request);

        $moneroService = new MoneroService();
        $results = $moneroService->checkAllPendingPayments();

        $msg = "Vérification Monero terminée. ";
        $msg .= "Vérifiés: {$results['checked']}, ";
        $msg .= "Confirmés: {$results['confirmed']}, ";
        $msg .= "Expirés: {$results['expired']}";

        $this->redirectWithSuccess('/admin/purchases', $msg);
    }

    /**
     * Module d'affiliation : synthèse des parrains, commissions et
     * paiements. Le sync paresseux matérialise les commissions des
     * commandes validées des filleuls avant affichage.
     */
    public function affiliation(Request $request, Response $response): never
    {
        $this->requireAdmin($request);

        $affiliateService = new AffiliateService();
        $affiliateService->syncCommissions();

        // Soldes par parrain + moyens de paiement renseignés
        $referrers = $affiliateService->getReferrersSummary();
        $db = Database::getInstance();
        foreach ($referrers as $i => $r) {
            $user = $db->queryOne("SELECT * FROM users WHERE id = ?", [(int) $r['id']]);
            $referrers[$i]['payout_methods'] = $user ? $affiliateService->getUserPayoutMethods($user) : [];
        }

        $this->view('admin.affiliation', [
            'title' => 'Affiliation',
            'affiliate_enabled' => $affiliateService->isEnabled(),
            'referrers' => $referrers,
            'payouts' => $affiliateService->getPayouts(),
            'tiers' => $affiliateService->getTiers(),
            'min_payout' => $affiliateService->getMinPayout(),
            'page' => 'admin-affiliation',
        ]);
    }

    /**
     * Paiement d'un parrain : solde la totalité du disponible via le
     * moyen de paiement renseigné par l'utilisateur sur son compte.
     */
    public function affiliationPayout(Request $request, Response $response): never
    {
        $this->requireAdmin($request);
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/admin/affiliation', 'CSRF invalide.');
        }

        $userId = (int) $request->post('user_id');
        $method = (string) $request->post('method');

        $affiliateService = new AffiliateService();
        $affiliateService->syncCommissions();
        $result = $affiliateService->createPayout($userId, $method);

        if ($result['success']) {
            $this->redirectWithSuccess('/admin/affiliation', $result['message']);
        }
        $this->redirectWithError('/admin/affiliation', $result['message']);
    }

    /**
     * Gestion de la blacklist
     */
    public function blacklist(Request $request, Response $response): never
    {
        $this->requireAdmin($request);
        $db = Database::getInstance();

        if ($request->isPost()) {
            if (!$this->verifyCsrf($request)) {
                $this->redirectWithError('/admin/blacklist', 'CSRF invalide.');
            }

            $urlPattern = trim($request->post('url_pattern', ''));
            $reason = trim($request->post('reason', ''));

            if (!empty($urlPattern)) {
                $db->insert('blacklist', [
                    'url_pattern' => $urlPattern,
                    'reason' => $reason,
                    'added_by' => (int) Session::user()['id'],
                ]);
            }
        }

        $blacklist = $db->query("SELECT b.*, u.username FROM blacklist b LEFT JOIN users u ON u.id = b.added_by ORDER BY b.created_at DESC");

        $this->view('admin.blacklist', [
            'title' => 'Blacklist',
            'blacklist' => $blacklist,
            'page' => 'admin-blacklist',
        ]);
    }

    /**
     * Retire un domaine de la blacklist et réhabilite les liens concernés
     */
    public function deleteBlacklist(Request $request, Response $response): never
    {
        $this->requireAdmin($request);
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/admin/blacklist', 'CSRF invalide.');
        }

        $id = (int) $request->post('blacklist_id');
        $db = Database::getInstance();

        $entry = $db->queryOne("SELECT * FROM blacklist WHERE id = ?", [$id]);
        if (!$entry) {
            $this->redirectWithError('/admin/blacklist', 'Entrée introuvable.');
        }

        $db->execute("DELETE FROM blacklist WHERE id = ?", [$id]);

        // Réhabilite les liens marqués par ce pattern, sauf s'ils restent
        // couverts par un autre pattern de la blacklist.
        $db->execute(
            "UPDATE links SET is_blacklisted = 0
             WHERE is_blacklisted = 1
               AND url LIKE CONCAT('%', ?, '%')
               AND NOT EXISTS (
                   SELECT 1 FROM blacklist WHERE links.url LIKE CONCAT('%', blacklist.url_pattern, '%')
               )",
            [$entry['url_pattern']]
        );

        $this->redirectWithSuccess('/admin/blacklist', '« ' . $entry['url_pattern'] . ' » retiré de la blacklist. Les liens correspondants ont été réhabilités.');
    }
}
