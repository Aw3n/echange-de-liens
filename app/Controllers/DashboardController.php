<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Database;
use App\Core\View;
use App\Repositories\LinkRepository;
use App\Repositories\VisitRepository;
use App\Repositories\PointsHistoryRepository;
use App\Repositories\UserRepository;
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
 * Contrôleur du tableau de bord
 */
class DashboardController extends Controller
{
    /**
     * Tableau de bord principal
     */
    public function index(Request $request, Response $response): never
    {
        $this->requireAuth($request);
        $user = Session::user();
        $userId = (int) $user['id'];

        $linkRepo = new LinkRepository();
        $visitRepo = new VisitRepository();
        $pointsRepo = new PointsHistoryRepository();
        $userRepo = new UserRepository();

        // Données utilisateur complètes
        $userData = $userRepo->findById($userId);

        // Liens de l'utilisateur
        $links = $linkRepo->findByUserId($userId, 20, 0);
        $totalLinks = $linkRepo->countByUserId($userId);

        // Statistiques de visites
        $visitStats = $visitRepo->getStats($userId);

        // Historique récent des points : pagination côté serveur —
        // 10 entrées au chargement initial, le reste via « Charger plus »
        $pointsHistory = $pointsRepo->getByUserId($userId, 10, 0);
        $historyTotal = $pointsRepo->countByUserId($userId);

        // Visites récentes
        $recentVisits = $visitRepo->getRecentByUserId($userId, 10);

        // Parrainage
        $referrals = $userRepo->getReferrals($userId);
        $referralCount = count($referrals);
        $referralVisitCount = $userRepo->countReferralVisits($userId);

        // Affiliation (module optionnel) : sync paresseux des commissions
        // des commandes validées des filleuls, puis solde du parrain
        $affiliateService = new AffiliateService();
        $affiliateEnabled = $affiliateService->isEnabled();
        $affiliateStats = ['earned' => 0.0, 'paid' => 0.0, 'available' => 0.0, 'referrals' => $referralCount, 'commissions' => []];
        if ($affiliateEnabled) {
            $affiliateService->syncCommissions();
            $affiliateStats = $affiliateService->getUserStats($userId);
        }

        $this->view('pages.dashboard', [
            'title' => 'Tableau de bord',
            'user_data' => $userData,
            'links' => $links,
            'total_links' => $totalLinks,
            'visit_stats' => $visitStats,
            'points_history' => $pointsHistory,
            'history_total' => $historyTotal,
            'recent_visits' => $recentVisits,
            'referrals' => $referrals,
            'referral_count' => $referralCount,
            'referral_visit_count' => $referralVisitCount,
            'referral_link' => referral_link($userId, $userRepo->getReferralCode($userId)),
            'referral_register_link' => referral_register_link($userId, $userRepo->getReferralCode($userId)),
            // Compléments : liens de parrainage vers la FAQ (FR / EN),
            // même fonctionnement (1 pt/visite IP unique 24h + bonus inscription)
            'referral_faq_link_fr' => referral_faq_link($userId, $userRepo->getReferralCode($userId), 'fr'),
            'referral_faq_link_en' => referral_faq_link($userId, $userRepo->getReferralCode($userId), 'en'),
            'affiliate_enabled' => $affiliateEnabled,
            'affiliate_stats' => $affiliateStats,
            'affiliate_tiers' => $affiliateEnabled ? $affiliateService->getTiers() : [],
            'affiliate_min_payout' => $affiliateService->getMinPayout(),
            'page' => 'dashboard',
        ]);
    }

    /**
     * Pagination de l'historique des points (bouton « Charger plus ») :
     * renvoie le lot de lignes suivant déjà rendu en HTML (partial),
     * pour éviter de dupliquer le balisage côté JavaScript.
     */
    public function historyMore(Request $request, Response $response): never
    {
        $this->requireAuth($request);
        $userId = (int) Session::user()['id'];

        $limit = 10;
        $offset = max(0, (int) $request->get('offset', 0));

        $repo = new PointsHistoryRepository();
        $rows = $repo->getByUserId($userId, $limit, $offset);
        $loaded = $offset + count($rows);

        $response->json([
            'success' => true,
            'html' => View::renderPartial('partials.history_rows', ['points_history' => $rows]),
            'next_offset' => $loaded,
            'has_more' => $loaded < $repo->countByUserId($userId),
        ]);
    }

    /**
     * Page Mon compte : adresses de paiement crypto de l'utilisateur
     * (visibles uniquement par lui et l'admin) + synthèse affiliation
     */
    public function account(Request $request, Response $response): never
    {
        $this->requireAuth($request);
        $user = Session::user();
        $userId = (int) $user['id'];

        $userRepo = new UserRepository();
        $userData = $userRepo->findById($userId);

        $affiliateService = new AffiliateService();
        $affiliateEnabled = $affiliateService->isEnabled();
        $affiliateStats = ['earned' => 0.0, 'paid' => 0.0, 'available' => 0.0, 'referrals' => 0, 'commissions' => []];
        if ($affiliateEnabled) {
            $affiliateService->syncCommissions();
            $affiliateStats = $affiliateService->getUserStats($userId);
        }

        $this->view('pages.account', [
            'title' => 'Mon compte',
            'user_data' => $userData,
            'wallet_methods' => AffiliateService::METHODS,
            'affiliate_enabled' => $affiliateEnabled,
            'affiliate_stats' => $affiliateStats,
            'affiliate_tiers' => $affiliateEnabled ? $affiliateService->getTiers() : [],
            'affiliate_min_payout' => $affiliateService->getMinPayout(),
            'page' => 'account',
        ]);
    }

    /**
     * Enregistrement des adresses de paiement crypto de l'utilisateur.
     * Chaque adresse non vide est validée avec le validateur strict de
     * sa crypto (les services de paiement refusent déjà les adresses
     * invalides à la réception : mêmes règles ici).
     */
    public function saveAccount(Request $request, Response $response): never
    {
        $this->requireAuth($request);
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/account', 'CSRF invalide.');
        }

        $user = Session::user();
        $userId = (int) $user['id'];

        $affiliateService = new AffiliateService();
        $updates = [];
        $errors = [];

        foreach (AffiliateService::METHODS as $method => $meta) {
            $column = $meta['column'];
            $value = trim((string) $request->post($column, ''));
            if ($value === '') {
                // Champ vidé : efface l'adresse
                $updates[$column] = null;
                continue;
            }
            if (mb_strlen($value) > 255) {
                $errors[] = $meta['label'] . ' : adresse trop longue.';
                continue;
            }
            if (!$affiliateService->validateWalletAddress($method, $value)) {
                $errors[] = $meta['label'] . ' : adresse invalide, vérifiez le format.';
                continue;
            }
            $updates[$column] = $value;
        }

        if (!empty($errors)) {
            $this->redirectWithError('/account', implode(' ', $errors));
        }

        (new UserRepository())->update($userId, $updates);

        $this->redirectWithSuccess('/account', 'Vos adresses de paiement ont été enregistrées.');
    }

    /**
     * Page VIP / Achat de points
     */
    public function vip(Request $request, Response $response): never
    {
        $this->requireAuth($request);

        $db = Database::getInstance();
        $packs = $db->query(
            "SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'vip_%' ORDER BY setting_key"
        );

        // Organiser les packs
        $vipPacks = [];
        foreach ($packs as $pack) {
            if (preg_match('/vip_points_(\d+)/', $pack['setting_key'], $m)) {
                $vipPacks[(int) $m[1]]['points'] = (int) $pack['setting_value'];
            }
            if (preg_match('/vip_price_(\d+)/', $pack['setting_key'], $m)) {
                $vipPacks[(int) $m[1]]['price'] = $pack['setting_value'];
            }
        }

        // Prix XELIS en temps réel
        $xelisService = new XelisService();
        $xelPriceEur = $xelisService->getXelPriceEur();

        // Prix Kaspa en temps réel
        $kaspaService = new KaspaService();
        $kasPriceEur = $kaspaService->getKasPriceEur();

        // Prix Firo en temps réel
        $firoService = new FiroService();
        $firoPriceEur = $firoService->getFiroPriceEur();

        // Prix Verge en temps réel
        $vergeService = new VergeService();
        $xvgPriceEur = $vergeService->getXvgPriceEur();

        // Prix Pepecoin en temps réel
        $pepecoinService = new PepecoinService();
        $pepePriceEur = $pepecoinService->getPepePriceEur();

        // Prix Vertcoin en temps réel
        $vertcoinService = new VertcoinService();
        $vtcPriceEur = $vertcoinService->getVtcPriceEur();

        // Prix DragonX en temps réel
        $dragonxService = new DragonxService();
        $drgxPriceEur = $dragonxService->getDrgxPriceEur();

        // Prix Monero en temps réel
        $moneroService = new MoneroService();
        $xmrPriceEur = $moneroService->getXmrPriceEur();

        // Convertir les prix EUR en XEL, KAS, FIRO, XVG, PEPE, VTC, DRGX et XMR pour l'affichage
        foreach ($vipPacks as $id => $pack) {
            $priceEur = (float) ($pack['price'] ?? 0);
            $vipPacks[$id]['price_xel'] = $xelPriceEur > 0 ? round($priceEur / $xelPriceEur, 8) : 0;
            $vipPacks[$id]['price_kas'] = $kasPriceEur > 0 ? round($priceEur / $kasPriceEur, 8) : 0;
            $vipPacks[$id]['price_firo'] = $firoPriceEur > 0 ? round($priceEur / $firoPriceEur, 8) : 0;
            $vipPacks[$id]['price_xvg'] = $xvgPriceEur > 0 ? round($priceEur / $xvgPriceEur, 8) : 0;
            $vipPacks[$id]['price_pepe'] = $pepePriceEur > 0 ? round($priceEur / $pepePriceEur, 8) : 0;
            $vipPacks[$id]['price_vtc'] = $vtcPriceEur > 0 ? round($priceEur / $vtcPriceEur, 8) : 0;
            $vipPacks[$id]['price_drgx'] = $drgxPriceEur > 0 ? round($priceEur / $drgxPriceEur, 8) : 0;
            $vipPacks[$id]['price_xmr'] = $xmrPriceEur > 0 ? round($priceEur / $xmrPriceEur, 8) : 0;
        }

        $this->view('pages.vip', [
            'title' => 'VIP - Acheter des points',
            'vip_packs' => $vipPacks,
            'xel_price_eur' => $xelPriceEur,
            'kas_price_eur' => $kasPriceEur,
            'firo_price_eur' => $firoPriceEur,
            'xvg_price_eur' => $xvgPriceEur,
            'pepe_price_eur' => $pepePriceEur,
            'vtc_price_eur' => $vtcPriceEur,
            'drgx_price_eur' => $drgxPriceEur,
            'xmr_price_eur' => $xmrPriceEur,
            'page' => 'vip',
        ]);
    }

    /**
     * Traitement d'achat PayPal
     */
    public function purchasePaypal(Request $request, Response $response): never
    {
        $this->requireAuth($request);
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/vip', 'CSRF invalide.');
        }

        $user = Session::user();
        $packId = (int) $request->post('pack_id');
        $db = Database::getInstance();

        $points = (int) ($db->queryOne("SELECT setting_value FROM settings WHERE setting_key = ?", ["vip_points_{$packId}"])['setting_value'] ?? 0);
        $price = $db->queryOne("SELECT setting_value FROM settings WHERE setting_key = ?", ["vip_price_{$packId}"])['setting_value'] ?? '0';

        if ($points <= 0 || (float) $price <= 0) {
            $this->redirectWithError('/vip', 'Pack invalide.');
        }

        // Enregistre l'achat en attente
        $db->insert('purchases', [
            'user_id' => (int) $user['id'],
            'amount_eur' => (float) $price,
            'payment_method' => 'paypal',
            'points_purchased' => $points,
            'status' => 'pending',
        ]);

        // Notification admin
        $adminEmail = $db->queryOne("SELECT setting_value FROM settings WHERE setting_key = 'paypal_email'")['setting_value'] ?? '';
        $db->insert('notifications', [
            'user_id' => 1, // Admin
            'type' => 'purchase_pending',
            'title' => 'Nouvel achat PayPal',
            'message' => "L'utilisateur {$user['username']} souhaite acheter {$points} points pour {$price}€.",
        ]);

        $this->redirectWithSuccess('/vip', "Votre demande d'achat a été enregistrée. L'administrateur validera après réception du paiement PayPal.");
    }

    /**
     * Traitement d'achat Xelis - Crée un paiement et redirige vers la page de paiement
     */
    public function purchaseXelis(Request $request, Response $response): never
    {
        $this->requireAuth($request);
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/vip', 'CSRF invalide.');
        }

        $user = Session::user();
        $packId = (int) $request->post('pack_id');

        $xelisService = new XelisService();
        $payment = $xelisService->createPayment((int) $user['id'], $packId);

        if (!$payment) {
            $this->redirectWithError('/vip', 'Impossible de créer le paiement XELIS. Vérifiez que l\'adresse XELIS est configurée.');
        }

        $this->redirectWithSuccess(
            '/xelis/payment/' . $payment['purchase_id'],
            "Paiement XELIS créé. Envoyez exactement {$payment['xel_amount']} XEL."
        );
    }

    /**
     * Page de paiement XELIS - Affiche l'adresse, le montant et le QR code
     */
    public function xelisPayment(Request $request, Response $response): never
    {
        $this->requireAuth($request);
        $user = Session::user();
        $purchaseId = (int) $request->getParam('id', '0');

        $db = Database::getInstance();
        $purchase = $db->queryOne(
            "SELECT * FROM purchases WHERE id = ? AND user_id = ? AND payment_method = 'xelis'",
            [$purchaseId, (int) $user['id']]
        );

        if (!$purchase) {
            $this->redirectWithError('/vip', 'Paiement non trouvé.');
        }

        $xelisService = new XelisService();
        $priceEur = $xelisService->getXelPriceEur();

        $this->view('pages.xelis_payment', [
            'title' => 'Paiement XELIS',
            'purchase' => $purchase,
            'xel_price_eur' => $priceEur,
            'page' => 'xelis-payment',
        ]);
    }

    /**
     * API AJAX - Statut du paiement XELIS
     */
    public function xelisStatus(Request $request, Response $response): void
    {
        $this->requireAuth($request);
        $user = Session::user();
        $purchaseId = (int) $request->getParam('id', '0');

        // Vérifier que c'est bien le propriétaire
        $db = Database::getInstance();
        $purchase = $db->queryOne(
            "SELECT user_id FROM purchases WHERE id = ? AND payment_method = 'xelis'",
            [$purchaseId]
        );

        if (!$purchase || (int) $purchase['user_id'] !== (int) $user['id']) {
            $response->json(['status' => 'error', 'message' => 'Non autorisé.'], 403);
            return;
        }

        $xelisService = new XelisService();
        $status = $xelisService->getPaymentStatus($purchaseId);

        $response->json($status);
    }

    /**
     * Soumission du hash de transaction XELIS
     */
    public function xelisSubmitTx(Request $request, Response $response): never
    {
        $this->requireAuth($request);
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/vip', 'CSRF invalide.');
        }

        $user = Session::user();
        $purchaseId = (int) $request->post('purchase_id');
        $txHash = trim($request->post('tx_hash', ''));

        if (empty($txHash)) {
            $this->redirectWithError('/xelis/payment/' . $purchaseId, 'Veuillez saisir le hash de transaction.');
        }

        $xelisService = new XelisService();
        $result = $xelisService->submitTransactionHash($purchaseId, $txHash);

        if ($result['success']) {
            $this->redirectWithSuccess('/xelis/payment/' . $purchaseId, $result['message']);
        } else {
            $this->redirectWithError('/xelis/payment/' . $purchaseId, $result['message']);
        }
    }

    // ========================================
    // PAIEMENT KASPA
    // ========================================

    /**
     * Traitement d'achat Kaspa - Crée un paiement et redirige vers la page de paiement
     */
    public function purchaseKaspa(Request $request, Response $response): never
    {
        $this->requireAuth($request);
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/vip', 'CSRF invalide.');
        }

        $user = Session::user();
        $packId = (int) $request->post('pack_id');

        $kaspaService = new KaspaService();
        $payment = $kaspaService->createPayment((int) $user['id'], $packId);

        if (!$payment) {
            // Vérifier si c'est un rate limit ou une erreur de config
            $errors = $kaspaService->getErrors();
            $errorMsg = 'Impossible de créer le paiement Kaspa.';
            if (!empty($errors)) {
                $lastError = end($errors);
                if (str_contains($lastError, 'Rate limit') || str_contains($lastError, 'Limite atteinte')) {
                    $errorMsg = 'Vous avez déjà un paiement Kaspa en attente. Veuillez patienter ou annuler l\'ancien paiement.';
                } elseif (str_contains($lastError, 'Adresse Kaspa non configurée')) {
                    $errorMsg = 'Le paiement Kaspa n\'est pas configuré. Contactez l\'administrateur.';
                } elseif (str_contains($lastError, 'CoinGecko')) {
                    $errorMsg = 'Service de conversion temporairement indisponible. Réessayez dans quelques minutes.';
                }
            }
            $this->redirectWithError('/vip', $errorMsg);
        }

        $this->redirectWithSuccess(
            '/kaspa/payment/' . $payment['purchase_id'],
            "Paiement Kaspa créé. Envoyez exactement {$payment['kas_amount']} KAS."
        );
    }

    /**
     * Page de paiement Kaspa - Affiche l'adresse, le montant et le QR code
     */
    public function kaspaPayment(Request $request, Response $response): never
    {
        $this->requireAuth($request);
        $user = Session::user();
        $purchaseId = (int) $request->getParam('id', '0');

        $db = Database::getInstance();
        $purchase = $db->queryOne(
            "SELECT * FROM purchases WHERE id = ? AND user_id = ? AND payment_method = 'kaspa'",
            [$purchaseId, (int) $user['id']]
        );

        if (!$purchase) {
            $this->redirectWithError('/vip', 'Paiement non trouvé.');
        }

        $kaspaService = new KaspaService();
        $priceEur = $kaspaService->getKasPriceEur();
        $kaspaUri = $kaspaService->buildKaspaUri(
            $purchase['payment_address'] ?? '',
            (float) $purchase['amount_xelis']
        );

        $this->view('pages.kaspa_payment', [
            'title' => 'Paiement Kaspa',
            'purchase' => $purchase,
            'kas_price_eur' => $priceEur,
            'kaspa_uri' => $kaspaUri,
            'page' => 'kaspa-payment',
        ]);
    }

    /**
     * API AJAX - Statut du paiement Kaspa
     */
    public function kaspaStatus(Request $request, Response $response): void
    {
        $this->requireAuth($request);
        $user = Session::user();
        $purchaseId = (int) $request->getParam('id', '0');

        // Vérifier que c'est bien le propriétaire
        $db = Database::getInstance();
        $purchase = $db->queryOne(
            "SELECT user_id FROM purchases WHERE id = ? AND payment_method = 'kaspa'",
            [$purchaseId]
        );

        if (!$purchase || (int) $purchase['user_id'] !== (int) $user['id']) {
            $response->json(['status' => 'error', 'message' => 'Non autorisé.'], 403);
            return;
        }

        $kaspaService = new KaspaService();
        $status = $kaspaService->getPaymentStatus($purchaseId);

        $response->json($status);
    }

    /**
     * Soumission du hash de transaction Kaspa
     * Vérifie l'ownership du paiement avant soumission
     */
    public function kaspaSubmitTx(Request $request, Response $response): never
    {
        $this->requireAuth($request);
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/vip', 'CSRF invalide.');
        }

        $user = Session::user();
        $purchaseId = (int) $request->post('purchase_id');
        $txHash = trim($request->post('tx_hash', ''));

        // Vérification d'ownership : seul le propriétaire peut soumettre une TX
        $db = Database::getInstance();
        $purchase = $db->queryOne(
            "SELECT id, user_id FROM purchases WHERE id = ? AND payment_method = 'kaspa' AND status = 'pending'",
            [$purchaseId]
        );

        if (!$purchase || (int) $purchase['user_id'] !== (int) $user['id']) {
            $this->redirectWithError('/kaspa/payment/' . $purchaseId, 'Paiement non trouvé ou non autorisé.');
        }

        if (empty($txHash)) {
            $this->redirectWithError('/kaspa/payment/' . $purchaseId, 'Veuillez saisir le hash de transaction.');
        }

        $kaspaService = new KaspaService();
        $result = $kaspaService->submitTransactionHash($purchaseId, $txHash);

        if ($result['success']) {
            $this->redirectWithSuccess('/kaspa/payment/' . $purchaseId, $result['message']);
        } else {
            $this->redirectWithError('/kaspa/payment/' . $purchaseId, $result['message']);
        }
    }

    // ========================================
    // PAIEMENT FIRO
    // ========================================

    /**
     * Traitement d'achat Firo - Crée un paiement et redirige vers la page de paiement
     */
    public function purchaseFiro(Request $request, Response $response): never
    {
        $this->requireAuth($request);
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/vip', 'CSRF invalide.');
        }

        $user = Session::user();
        $packId = (int) $request->post('pack_id');

        $firoService = new FiroService();
        $payment = $firoService->createPayment((int) $user['id'], $packId);

        if (!$payment) {
            $errors = $firoService->getErrors();
            $errorMsg = 'Impossible de créer le paiement Firo.';
            if (!empty($errors)) {
                $lastError = end($errors);
                if (str_contains($lastError, 'Rate limit') || str_contains($lastError, 'Limite atteinte')) {
                    $errorMsg = 'Vous avez déjà un paiement Firo en attente. Veuillez patienter.';
                } elseif (str_contains($lastError, 'Adresse Firo non configurée')) {
                    $errorMsg = 'Le paiement Firo n\'est pas configuré. Contactez l\'administrateur.';
                } elseif (str_contains($lastError, 'CoinGecko')) {
                    $errorMsg = 'Service de conversion temporairement indisponible. Réessayez dans quelques minutes.';
                }
            }
            $this->redirectWithError('/vip', $errorMsg);
        }

        $this->redirectWithSuccess(
            '/firo/payment/' . $payment['purchase_id'],
            "Paiement Firo créé. Envoyez exactement {$payment['firo_amount']} FIRO."
        );
    }

    /**
     * Page de paiement Firo
     */
    public function firoPayment(Request $request, Response $response): never
    {
        $this->requireAuth($request);
        $user = Session::user();
        $purchaseId = (int) $request->getParam('id', '0');

        $db = Database::getInstance();
        $purchase = $db->queryOne(
            "SELECT * FROM purchases WHERE id = ? AND user_id = ? AND payment_method = 'firo'",
            [$purchaseId, (int) $user['id']]
        );

        if (!$purchase) {
            $this->redirectWithError('/vip', 'Paiement non trouvé.');
        }

        $firoService = new FiroService();
        $priceEur = $firoService->getFiroPriceEur();
        $firoUri = $firoService->buildFiroUri(
            $purchase['payment_address'] ?? '',
            (float) $purchase['amount_xelis']
        );

        $this->view('pages.firo_payment', [
            'title' => 'Paiement Firo',
            'purchase' => $purchase,
            'firo_price_eur' => $priceEur,
            'firo_uri' => $firoUri,
            'page' => 'firo-payment',
        ]);
    }

    /**
     * API AJAX - Statut du paiement Firo
     */
    public function firoStatus(Request $request, Response $response): void
    {
        $this->requireAuth($request);
        $user = Session::user();
        $purchaseId = (int) $request->getParam('id', '0');

        $db = Database::getInstance();
        $purchase = $db->queryOne(
            "SELECT user_id FROM purchases WHERE id = ? AND payment_method = 'firo'",
            [$purchaseId]
        );

        if (!$purchase || (int) $purchase['user_id'] !== (int) $user['id']) {
            $response->json(['status' => 'error', 'message' => 'Non autorisé.'], 403);
            return;
        }

        $firoService = new FiroService();
        $status = $firoService->getPaymentStatus($purchaseId);

        $response->json($status);
    }

    /**
     * Soumission du hash de transaction Firo
     */
    public function firoSubmitTx(Request $request, Response $response): never
    {
        $this->requireAuth($request);
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/vip', 'CSRF invalide.');
        }

        $user = Session::user();
        $purchaseId = (int) $request->post('purchase_id');
        $txHash = trim($request->post('tx_hash', ''));

        // Vérification d'ownership
        $db = Database::getInstance();
        $purchase = $db->queryOne(
            "SELECT id, user_id FROM purchases WHERE id = ? AND payment_method = 'firo' AND status = 'pending'",
            [$purchaseId]
        );

        if (!$purchase || (int) $purchase['user_id'] !== (int) $user['id']) {
            $this->redirectWithError('/firo/payment/' . $purchaseId, 'Paiement non trouvé ou non autorisé.');
        }

        if (empty($txHash)) {
            $this->redirectWithError('/firo/payment/' . $purchaseId, 'Veuillez saisir le hash de transaction.');
        }

        $firoService = new FiroService();
        $result = $firoService->submitTransactionHash($purchaseId, $txHash);

        if ($result['success']) {
            $this->redirectWithSuccess('/firo/payment/' . $purchaseId, $result['message']);
        } else {
            $this->redirectWithError('/firo/payment/' . $purchaseId, $result['message']);
        }
    }

    // ========================================
    // PAIEMENT VERGE (XVG)
    // ========================================

    /**
     * Traitement d'achat Verge - Crée un paiement et redirige vers la page de paiement
     */
    public function purchaseVerge(Request $request, Response $response): never
    {
        $this->requireAuth($request);
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/vip', 'CSRF invalide.');
        }

        $user = Session::user();
        $packId = (int) $request->post('pack_id');

        $vergeService = new VergeService();
        $payment = $vergeService->createPayment((int) $user['id'], $packId);

        if (!$payment) {
            $errors = $vergeService->getErrors();
            $errorMsg = 'Impossible de créer le paiement Verge.';
            if (!empty($errors)) {
                $lastError = end($errors);
                if (str_contains($lastError, 'Rate limit') || str_contains($lastError, 'Limite atteinte')) {
                    $errorMsg = 'Vous avez déjà un paiement Verge en attente. Veuillez patienter.';
                } elseif (str_contains($lastError, 'Adresse Verge non configurée')) {
                    $errorMsg = 'Le paiement Verge n\'est pas configuré. Contactez l\'administrateur.';
                } elseif (str_contains($lastError, 'CoinGecko')) {
                    $errorMsg = 'Service de conversion temporairement indisponible. Réessayez dans quelques minutes.';
                }
            }
            $this->redirectWithError('/vip', $errorMsg);
        }

        $this->redirectWithSuccess(
            '/verge/payment/' . $payment['purchase_id'],
            "Paiement Verge créé. Envoyez exactement {$payment['xvg_amount']} XVG."
        );
    }

    /**
     * Page de paiement Verge
     */
    public function vergePayment(Request $request, Response $response): never
    {
        $this->requireAuth($request);
        $user = Session::user();
        $purchaseId = (int) $request->getParam('id', '0');

        $db = Database::getInstance();
        $purchase = $db->queryOne(
            "SELECT * FROM purchases WHERE id = ? AND user_id = ? AND payment_method = 'verge'",
            [$purchaseId, (int) $user['id']]
        );

        if (!$purchase) {
            $this->redirectWithError('/vip', 'Paiement non trouvé.');
        }

        $vergeService = new VergeService();
        $priceEur = $vergeService->getXvgPriceEur();
        $vergeUri = $vergeService->buildVergeUri(
            $purchase['payment_address'] ?? '',
            (float) $purchase['amount_xelis']
        );

        $this->view('pages.verge_payment', [
            'title' => 'Paiement Verge',
            'purchase' => $purchase,
            'xvg_price_eur' => $priceEur,
            'verge_uri' => $vergeUri,
            'page' => 'verge-payment',
        ]);
    }

    /**
     * API AJAX - Statut du paiement Verge
     */
    public function vergeStatus(Request $request, Response $response): void
    {
        $this->requireAuth($request);
        $user = Session::user();
        $purchaseId = (int) $request->getParam('id', '0');

        $db = Database::getInstance();
        $purchase = $db->queryOne(
            "SELECT user_id FROM purchases WHERE id = ? AND payment_method = 'verge'",
            [$purchaseId]
        );

        if (!$purchase || (int) $purchase['user_id'] !== (int) $user['id']) {
            $response->json(['status' => 'error', 'message' => 'Non autorisé.'], 403);
            return;
        }

        $vergeService = new VergeService();
        $status = $vergeService->getPaymentStatus($purchaseId);

        $response->json($status);
    }

    /**
     * Soumission du hash de transaction Verge
     */
    public function vergeSubmitTx(Request $request, Response $response): never
    {
        $this->requireAuth($request);
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/vip', 'CSRF invalide.');
        }

        $user = Session::user();
        $purchaseId = (int) $request->post('purchase_id');
        $txHash = trim($request->post('tx_hash', ''));

        // Vérification d'ownership
        $db = Database::getInstance();
        $purchase = $db->queryOne(
            "SELECT id, user_id FROM purchases WHERE id = ? AND payment_method = 'verge' AND status = 'pending'",
            [$purchaseId]
        );

        if (!$purchase || (int) $purchase['user_id'] !== (int) $user['id']) {
            $this->redirectWithError('/verge/payment/' . $purchaseId, 'Paiement non trouvé ou non autorisé.');
        }

        if (empty($txHash)) {
            $this->redirectWithError('/verge/payment/' . $purchaseId, 'Veuillez saisir le hash de transaction.');
        }

        $vergeService = new VergeService();
        $result = $vergeService->submitTransactionHash($purchaseId, $txHash);

        if ($result['success']) {
            $this->redirectWithSuccess('/verge/payment/' . $purchaseId, $result['message']);
        } else {
            $this->redirectWithError('/verge/payment/' . $purchaseId, $result['message']);
        }
    }

    // ========================================
    // PAIEMENT PEPECOIN (PEPE)
    // ========================================

    /**
     * Traitement d'achat Pepecoin - Crée un paiement et redirige vers la page de paiement
     */
    public function purchasePepecoin(Request $request, Response $response): never
    {
        $this->requireAuth($request);
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/vip', 'CSRF invalide.');
        }

        $user = Session::user();
        $packId = (int) $request->post('pack_id');

        $pepecoinService = new PepecoinService();
        $payment = $pepecoinService->createPayment((int) $user['id'], $packId);

        if (!$payment) {
            $errors = $pepecoinService->getErrors();
            $errorMsg = 'Impossible de créer le paiement Pepecoin.';
            if (!empty($errors)) {
                $lastError = end($errors);
                if (str_contains($lastError, 'Rate limit') || str_contains($lastError, 'Limite atteinte')) {
                    $errorMsg = 'Vous avez déjà un paiement Pepecoin en attente. Veuillez patienter.';
                } elseif (str_contains($lastError, 'Adresse Pepecoin non configurée')) {
                    $errorMsg = 'Le paiement Pepecoin n\'est pas configuré. Contactez l\'administrateur.';
                } elseif (str_contains($lastError, 'CoinGecko')) {
                    $errorMsg = 'Service de conversion temporairement indisponible. Réessayez dans quelques minutes.';
                }
            }
            $this->redirectWithError('/vip', $errorMsg);
        }

        $this->redirectWithSuccess(
            '/pepecoin/payment/' . $payment['purchase_id'],
            "Paiement Pepecoin créé. Envoyez exactement {$payment['pepe_amount']} PEPE."
        );
    }

    /**
     * Page de paiement Pepecoin
     */
    public function pepecoinPayment(Request $request, Response $response): never
    {
        $this->requireAuth($request);
        $user = Session::user();
        $purchaseId = (int) $request->getParam('id', '0');

        $db = Database::getInstance();
        $purchase = $db->queryOne(
            "SELECT * FROM purchases WHERE id = ? AND user_id = ? AND payment_method = 'pepecoin'",
            [$purchaseId, (int) $user['id']]
        );

        if (!$purchase) {
            $this->redirectWithError('/vip', 'Paiement non trouvé.');
        }

        $pepecoinService = new PepecoinService();
        $priceEur = $pepecoinService->getPepePriceEur();
        $pepecoinUri = $pepecoinService->buildPepecoinUri(
            $purchase['payment_address'] ?? '',
            (float) $purchase['amount_xelis']
        );

        $this->view('pages.pepecoin_payment', [
            'title' => 'Paiement Pepecoin',
            'purchase' => $purchase,
            'pepe_price_eur' => $priceEur,
            'pepecoin_uri' => $pepecoinUri,
            'page' => 'pepecoin-payment',
        ]);
    }

    /**
     * API AJAX - Statut du paiement Pepecoin
     */
    public function pepecoinStatus(Request $request, Response $response): void
    {
        $this->requireAuth($request);
        $user = Session::user();
        $purchaseId = (int) $request->getParam('id', '0');

        $db = Database::getInstance();
        $purchase = $db->queryOne(
            "SELECT user_id FROM purchases WHERE id = ? AND payment_method = 'pepecoin'",
            [$purchaseId]
        );

        if (!$purchase || (int) $purchase['user_id'] !== (int) $user['id']) {
            $response->json(['status' => 'error', 'message' => 'Non autorisé.'], 403);
            return;
        }

        $pepecoinService = new PepecoinService();
        $status = $pepecoinService->getPaymentStatus($purchaseId);

        $response->json($status);
    }

    /**
     * Soumission du hash de transaction Pepecoin
     */
    public function pepecoinSubmitTx(Request $request, Response $response): never
    {
        $this->requireAuth($request);
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/vip', 'CSRF invalide.');
        }

        $user = Session::user();
        $purchaseId = (int) $request->post('purchase_id');
        $txHash = trim($request->post('tx_hash', ''));

        // Vérification d'ownership
        $db = Database::getInstance();
        $purchase = $db->queryOne(
            "SELECT id, user_id FROM purchases WHERE id = ? AND payment_method = 'pepecoin' AND status = 'pending'",
            [$purchaseId]
        );

        if (!$purchase || (int) $purchase['user_id'] !== (int) $user['id']) {
            $this->redirectWithError('/pepecoin/payment/' . $purchaseId, 'Paiement non trouvé ou non autorisé.');
        }

        if (empty($txHash)) {
            $this->redirectWithError('/pepecoin/payment/' . $purchaseId, 'Veuillez saisir le hash de transaction.');
        }

        $pepecoinService = new PepecoinService();
        $result = $pepecoinService->submitTransactionHash($purchaseId, $txHash);

        if ($result['success']) {
            $this->redirectWithSuccess('/pepecoin/payment/' . $purchaseId, $result['message']);
        } else {
            $this->redirectWithError('/pepecoin/payment/' . $purchaseId, $result['message']);
        }
    }

    // ========================================
    // PAIEMENT VERTCOIN (VTC)
    // ========================================

    /**
     * Traitement d'achat Vertcoin - Crée un paiement et redirige vers la page de paiement
     */
    public function purchaseVertcoin(Request $request, Response $response): never
    {
        $this->requireAuth($request);
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/vip', 'CSRF invalide.');
        }

        $user = Session::user();
        $packId = (int) $request->post('pack_id');

        $vertcoinService = new VertcoinService();
        $payment = $vertcoinService->createPayment((int) $user['id'], $packId);

        if (!$payment) {
            $errors = $vertcoinService->getErrors();
            $errorMsg = 'Impossible de créer le paiement Vertcoin.';
            if (!empty($errors)) {
                $lastError = end($errors);
                if (str_contains($lastError, 'Rate limit') || str_contains($lastError, 'Limite atteinte')) {
                    $errorMsg = 'Vous avez déjà un paiement Vertcoin en attente. Veuillez patienter.';
                } elseif (str_contains($lastError, 'Adresse Vertcoin non configurée')) {
                    $errorMsg = 'Le paiement Vertcoin n\'est pas configuré. Contactez l\'administrateur.';
                } elseif (str_contains($lastError, 'CoinGecko')) {
                    $errorMsg = 'Service de conversion temporairement indisponible. Réessayez dans quelques minutes.';
                }
            }
            $this->redirectWithError('/vip', $errorMsg);
        }

        $this->redirectWithSuccess(
            '/vertcoin/payment/' . $payment['purchase_id'],
            "Paiement Vertcoin créé. Envoyez exactement {$payment['vtc_amount']} VTC."
        );
    }

    /**
     * Page de paiement Vertcoin
     */
    public function vertcoinPayment(Request $request, Response $response): never
    {
        $this->requireAuth($request);
        $user = Session::user();
        $purchaseId = (int) $request->getParam('id', '0');

        $db = Database::getInstance();
        $purchase = $db->queryOne(
            "SELECT * FROM purchases WHERE id = ? AND user_id = ? AND payment_method = 'vertcoin'",
            [$purchaseId, (int) $user['id']]
        );

        if (!$purchase) {
            $this->redirectWithError('/vip', 'Paiement non trouvé.');
        }

        $vertcoinService = new VertcoinService();
        $priceEur = $vertcoinService->getVtcPriceEur();
        $vertcoinUri = $vertcoinService->buildVertcoinUri(
            $purchase['payment_address'] ?? '',
            (float) $purchase['amount_xelis']
        );

        $this->view('pages.vertcoin_payment', [
            'title' => 'Paiement Vertcoin',
            'purchase' => $purchase,
            'vtc_price_eur' => $priceEur,
            'vertcoin_uri' => $vertcoinUri,
            'page' => 'vertcoin-payment',
        ]);
    }

    /**
     * API AJAX - Statut du paiement Vertcoin
     */
    public function vertcoinStatus(Request $request, Response $response): void
    {
        $this->requireAuth($request);
        $user = Session::user();
        $purchaseId = (int) $request->getParam('id', '0');

        $db = Database::getInstance();
        $purchase = $db->queryOne(
            "SELECT user_id FROM purchases WHERE id = ? AND payment_method = 'vertcoin'",
            [$purchaseId]
        );

        if (!$purchase || (int) $purchase['user_id'] !== (int) $user['id']) {
            $response->json(['status' => 'error', 'message' => 'Non autorisé.'], 403);
            return;
        }

        $vertcoinService = new VertcoinService();
        $status = $vertcoinService->getPaymentStatus($purchaseId);

        $response->json($status);
    }

    /**
     * Soumission du hash de transaction Vertcoin
     */
    public function vertcoinSubmitTx(Request $request, Response $response): never
    {
        $this->requireAuth($request);
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/vip', 'CSRF invalide.');
        }

        $user = Session::user();
        $purchaseId = (int) $request->post('purchase_id');
        $txHash = trim($request->post('tx_hash', ''));

        // Vérification d'ownership
        $db = Database::getInstance();
        $purchase = $db->queryOne(
            "SELECT id, user_id FROM purchases WHERE id = ? AND payment_method = 'vertcoin' AND status = 'pending'",
            [$purchaseId]
        );

        if (!$purchase || (int) $purchase['user_id'] !== (int) $user['id']) {
            $this->redirectWithError('/vertcoin/payment/' . $purchaseId, 'Paiement non trouvé ou non autorisé.');
        }

        if (empty($txHash)) {
            $this->redirectWithError('/vertcoin/payment/' . $purchaseId, 'Veuillez saisir le hash de transaction.');
        }

        $vertcoinService = new VertcoinService();
        $result = $vertcoinService->submitTransactionHash($purchaseId, $txHash);

        if ($result['success']) {
            $this->redirectWithSuccess('/vertcoin/payment/' . $purchaseId, $result['message']);
        } else {
            $this->redirectWithError('/vertcoin/payment/' . $purchaseId, $result['message']);
        }
    }

    // ========================================
    // PAIEMENT DRAGONX (DRGX)
    // ========================================

    /**
     * Traitement d'achat DragonX - Crée un paiement et redirige vers la page de paiement
     */
    public function purchaseDragonx(Request $request, Response $response): never
    {
        $this->requireAuth($request);
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/vip', 'CSRF invalide.');
        }

        $user = Session::user();
        $packId = (int) $request->post('pack_id');

        $dragonxService = new DragonxService();
        $payment = $dragonxService->createPayment((int) $user['id'], $packId);

        if (!$payment) {
            $errors = $dragonxService->getErrors();
            $errorMsg = 'Impossible de créer le paiement DragonX.';
            if (!empty($errors)) {
                $lastError = end($errors);
                if (str_contains($lastError, 'Rate limit') || str_contains($lastError, 'Limite atteinte')) {
                    $errorMsg = 'Vous avez déjà un paiement DragonX en attente. Veuillez patienter.';
                } elseif (str_contains($lastError, 'Adresse DragonX non configurée')) {
                    $errorMsg = 'Le paiement DragonX n\'est pas configuré. Contactez l\'administrateur.';
                } elseif (str_contains($lastError, 'CoinGecko')) {
                    $errorMsg = 'Service de conversion temporairement indisponible. Réessayez dans quelques minutes.';
                }
            }
            $this->redirectWithError('/vip', $errorMsg);
        }

        $this->redirectWithSuccess(
            '/dragonx/payment/' . $payment['purchase_id'],
            "Paiement DragonX créé. Envoyez exactement {$payment['drgx_amount']} DRGX."
        );
    }

    /**
     * Page de paiement DragonX
     */
    public function dragonxPayment(Request $request, Response $response): never
    {
        $this->requireAuth($request);
        $user = Session::user();
        $purchaseId = (int) $request->getParam('id', '0');

        $db = Database::getInstance();
        $purchase = $db->queryOne(
            "SELECT * FROM purchases WHERE id = ? AND user_id = ? AND payment_method = 'dragonx'",
            [$purchaseId, (int) $user['id']]
        );

        if (!$purchase) {
            $this->redirectWithError('/vip', 'Paiement non trouvé.');
        }

        $dragonxService = new DragonxService();
        $priceEur = $dragonxService->getDrgxPriceEur();
        $dragonxUri = $dragonxService->buildDragonxUri(
            $purchase['payment_address'] ?? '',
            (float) $purchase['amount_xelis']
        );

        $this->view('pages.dragonx_payment', [
            'title' => 'Paiement DragonX',
            'purchase' => $purchase,
            'drgx_price_eur' => $priceEur,
            'dragonx_uri' => $dragonxUri,
            'page' => 'dragonx-payment',
        ]);
    }

    /**
     * API AJAX - Statut du paiement DragonX
     */
    public function dragonxStatus(Request $request, Response $response): void
    {
        $this->requireAuth($request);
        $user = Session::user();
        $purchaseId = (int) $request->getParam('id', '0');

        $db = Database::getInstance();
        $purchase = $db->queryOne(
            "SELECT user_id FROM purchases WHERE id = ? AND payment_method = 'dragonx'",
            [$purchaseId]
        );

        if (!$purchase || (int) $purchase['user_id'] !== (int) $user['id']) {
            $response->json(['status' => 'error', 'message' => 'Non autorisé.'], 403);
            return;
        }

        $dragonxService = new DragonxService();
        $status = $dragonxService->getPaymentStatus($purchaseId);

        $response->json($status);
    }

    /**
     * Soumission du hash de transaction DragonX
     */
    public function dragonxSubmitTx(Request $request, Response $response): never
    {
        $this->requireAuth($request);
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/vip', 'CSRF invalide.');
        }

        $user = Session::user();
        $purchaseId = (int) $request->post('purchase_id');
        $txHash = trim($request->post('tx_hash', ''));

        // Vérification d'ownership
        $db = Database::getInstance();
        $purchase = $db->queryOne(
            "SELECT id, user_id FROM purchases WHERE id = ? AND payment_method = 'dragonx' AND status = 'pending'",
            [$purchaseId]
        );

        if (!$purchase || (int) $purchase['user_id'] !== (int) $user['id']) {
            $this->redirectWithError('/dragonx/payment/' . $purchaseId, 'Paiement non trouvé ou non autorisé.');
        }

        if (empty($txHash)) {
            $this->redirectWithError('/dragonx/payment/' . $purchaseId, 'Veuillez saisir le hash de transaction.');
        }

        $dragonxService = new DragonxService();
        $result = $dragonxService->submitTransactionHash($purchaseId, $txHash);

        if ($result['success']) {
            $this->redirectWithSuccess('/dragonx/payment/' . $purchaseId, $result['message']);
        } else {
            $this->redirectWithError('/dragonx/payment/' . $purchaseId, $result['message']);
        }
    }

    /**
     * Statistiques personnelles
     */
    public function stats(Request $request, Response $response): never
    {
        $this->requireAuth($request);
        $user = Session::user();
        $userId = (int) $user['id'];

        $visitRepo = new VisitRepository();
        $pointsRepo = new PointsHistoryRepository();
        $linkRepo = new LinkRepository();

        $visitStats = $visitRepo->getStats($userId);
        $pointsSummary = $pointsRepo->getSummary($userId);
        $totalLinks = $linkRepo->countByUserId($userId);

        $this->view('pages.stats', [
            'title' => 'Statistiques',
            'visit_stats' => $visitStats,
            'points_summary' => $pointsSummary,
            'total_links' => $totalLinks,
            'page' => 'stats',
        ]);
    }

    // ========================================
    // PAIEMENT MONERO (XMR)
    // ========================================

    /**
     * Traitement d'achat Monero - Crée un paiement et redirige vers la page de paiement
     */
    public function purchaseMonero(Request $request, Response $response): never
    {
        $this->requireAuth($request);
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/vip', 'CSRF invalide.');
        }

        $user = Session::user();
        $packId = (int) $request->post('pack_id');

        $moneroService = new MoneroService();
        $payment = $moneroService->createPayment((int) $user['id'], $packId);

        if (!$payment) {
            $errors = $moneroService->getErrors();
            $errorMsg = 'Impossible de créer le paiement Monero.';
            if (!empty($errors)) {
                $lastError = end($errors);
                if (str_contains($lastError, 'Rate limit') || str_contains($lastError, 'Limite atteinte')) {
                    $errorMsg = 'Vous avez déjà un paiement Monero en attente. Veuillez patienter.';
                } elseif (str_contains($lastError, 'Adresse Monero non configurée') || str_contains($lastError, 'Adresse Monero invalide')) {
                    $errorMsg = 'Le paiement Monero n\'est pas configuré correctement. Contactez l\'administrateur.';
                } elseif (str_contains($lastError, 'CoinGecko')) {
                    $errorMsg = 'Service de conversion temporairement indisponible. Réessayez dans quelques minutes.';
                }
            }
            $this->redirectWithError('/vip', $errorMsg);
        }

        $this->redirectWithSuccess(
            '/monero/payment/' . $payment['purchase_id'],
            "Paiement Monero créé. Envoyez exactement {$payment['xmr_amount']} XMR."
        );
    }

    /**
     * Page de paiement Monero
     */
    public function moneroPayment(Request $request, Response $response): never
    {
        $this->requireAuth($request);
        $user = Session::user();
        $purchaseId = (int) $request->getParam('id', '0');

        $db = Database::getInstance();
        $purchase = $db->queryOne(
            "SELECT * FROM purchases WHERE id = ? AND user_id = ? AND payment_method = 'monero'",
            [$purchaseId, (int) $user['id']]
        );

        if (!$purchase) {
            $this->redirectWithError('/vip', 'Paiement non trouvé.');
        }

        $moneroService = new MoneroService();
        $priceEur = $moneroService->getXmrPriceEur();
        $moneroUri = $moneroService->buildMoneroUri(
            $purchase['payment_address'] ?? '',
            (float) $purchase['amount_xelis'],
            $moneroService->getPaymentIdFromPurchase($purchase) ?? ''
        );

        $this->view('pages.monero_payment', [
            'title' => 'Paiement Monero',
            'purchase' => $purchase,
            'xmr_price_eur' => $priceEur,
            'monero_uri' => $moneroUri,
            'explorer_url' => $moneroService->getExplorerUrl(),
            'page' => 'monero-payment',
        ]);
    }

    /**
     * API AJAX - Statut du paiement Monero
     */
    public function moneroStatus(Request $request, Response $response): void
    {
        $this->requireAuth($request);
        $user = Session::user();
        $purchaseId = (int) $request->getParam('id', '0');

        $db = Database::getInstance();
        $purchase = $db->queryOne(
            "SELECT user_id FROM purchases WHERE id = ? AND payment_method = 'monero'",
            [$purchaseId]
        );

        if (!$purchase || (int) $purchase['user_id'] !== (int) $user['id']) {
            $response->json(['status' => 'error', 'message' => 'Non autorisé.'], 403);
            return;
        }

        $moneroService = new MoneroService();
        $status = $moneroService->getPaymentStatus($purchaseId);

        $response->json($status);
    }

    /**
     * Soumission du hash de transaction Monero
     */
    public function moneroSubmitTx(Request $request, Response $response): never
    {
        $this->requireAuth($request);
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/vip', 'CSRF invalide.');
        }

        $user = Session::user();
        $purchaseId = (int) $request->post('purchase_id');
        $txHash = trim($request->post('tx_hash', ''));

        // Vérification d'ownership
        $db = Database::getInstance();
        $purchase = $db->queryOne(
            "SELECT id, user_id FROM purchases WHERE id = ? AND payment_method = 'monero' AND status = 'pending'",
            [$purchaseId]
        );

        if (!$purchase || (int) $purchase['user_id'] !== (int) $user['id']) {
            $this->redirectWithError('/monero/payment/' . $purchaseId, 'Paiement non trouvé ou non autorisé.');
        }

        if (empty($txHash)) {
            $this->redirectWithError('/monero/payment/' . $purchaseId, 'Veuillez saisir le hash de transaction.');
        }

        $moneroService = new MoneroService();
        $result = $moneroService->submitTransactionHash($purchaseId, $txHash);

        if ($result['success']) {
            $this->redirectWithSuccess('/monero/payment/' . $purchaseId, $result['message']);
        } else {
            $this->redirectWithError('/monero/payment/' . $purchaseId, $result['message']);
        }
    }
}
