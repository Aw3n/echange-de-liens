<?php
declare(strict_types=1);

/**
 * Routes Web de l'application
 */

use App\Controllers\HomeController;
use App\Controllers\AuthController;
use App\Controllers\LinkController;
use App\Controllers\VisitController;
use App\Controllers\DashboardController;
use App\Controllers\BonusController;
use App\Controllers\WheelController;
use App\Controllers\BalloonController;
use App\Controllers\Admin\AdminController;
use App\Middleware\AuthMiddleware;
use App\Middleware\AdminMiddleware;
use App\Middleware\CsrfMiddleware;

/** @var \App\Core\App $this */
$router = $this->getRouter();

// ========================================
// Pages publiques
// ========================================
$router->get('/', [HomeController::class, 'index']);
$router->get('/faq', [HomeController::class, 'faq']);
$router->get('/page/{slug}', [HomeController::class, 'page']);
$router->get('/ranking', [LinkController::class, 'ranking']);
$router->get('/ranking/more', [HomeController::class, 'rankingMore']);
$router->get('/bonus', [BonusController::class, 'index']);

// ========================================
// SEO : sitemap.xml et robots.txt dynamiques
// ========================================
$router->get('/sitemap.xml', [HomeController::class, 'sitemap']);
$router->get('/robots.txt', [HomeController::class, 'robots']);

// ========================================
// Authentification
// ========================================
$router->get('/login', [AuthController::class, 'loginForm']);
$router->post('/login', [AuthController::class, 'login'], [CsrfMiddleware::class]);
$router->get('/register', [AuthController::class, 'registerForm']);
$router->post('/register', [AuthController::class, 'register'], [CsrfMiddleware::class]);
$router->get('/logout', [AuthController::class, 'logout']);
$router->get('/forgot-password', [AuthController::class, 'forgotForm']);
$router->post('/forgot-password', [AuthController::class, 'forgot'], [CsrfMiddleware::class]);
$router->get('/reset-password', [AuthController::class, 'resetForm']);
$router->post('/reset-password', [AuthController::class, 'reset'], [CsrfMiddleware::class]);
$router->get('/verify-email', [AuthController::class, 'verifyEmail']);

// ========================================
// Système de visite
// ========================================
$router->get('/go', [VisitController::class, 'go']);
$router->post('/visit/validate', [VisitController::class, 'validate']);
$router->post('/visit/validate-banner', [VisitController::class, 'validateBanner']);
$router->get('/bonus/viewer', [VisitController::class, 'bonusViewer']);

// ========================================
// Routes authentifiées
// ========================================
$router->group('', function ($router) {
    // Dashboard
    $router->get('/dashboard', [DashboardController::class, 'index']);
    $router->get('/dashboard/history', [DashboardController::class, 'historyMore']);
    $router->get('/stats', [DashboardController::class, 'stats']);

    // Mon compte (adresses de paiement crypto + affiliation)
    $router->get('/account', [DashboardController::class, 'account']);
    $router->post('/account', [DashboardController::class, 'saveAccount']);

    $router->get('/vip', [DashboardController::class, 'vip']);
    $router->post('/vip/paypal', [DashboardController::class, 'purchasePaypal']);
    $router->post('/vip/xelis', [DashboardController::class, 'purchaseXelis']);
    $router->post('/vip/kaspa', [DashboardController::class, 'purchaseKaspa']);
    $router->post('/vip/firo', [DashboardController::class, 'purchaseFiro']);
    $router->post('/vip/verge', [DashboardController::class, 'purchaseVerge']);
    $router->post('/vip/pepecoin', [DashboardController::class, 'purchasePepecoin']);
    $router->post('/vip/vertcoin', [DashboardController::class, 'purchaseVertcoin']);
    $router->post('/vip/dragonx', [DashboardController::class, 'purchaseDragonx']);
    $router->post('/vip/monero', [DashboardController::class, 'purchaseMonero']);

    // Paiement XELIS
    $router->get('/xelis/payment/{id}', [DashboardController::class, 'xelisPayment']);
    $router->get('/xelis/status/{id}', [DashboardController::class, 'xelisStatus']);
    $router->post('/xelis/submit-tx', [DashboardController::class, 'xelisSubmitTx']);

    // Paiement Kaspa
    $router->get('/kaspa/payment/{id}', [DashboardController::class, 'kaspaPayment']);
    $router->get('/kaspa/status/{id}', [DashboardController::class, 'kaspaStatus']);
    $router->post('/kaspa/submit-tx', [DashboardController::class, 'kaspaSubmitTx']);

    // Paiement Firo
    $router->get('/firo/payment/{id}', [DashboardController::class, 'firoPayment']);
    $router->get('/firo/status/{id}', [DashboardController::class, 'firoStatus']);
    $router->post('/firo/submit-tx', [DashboardController::class, 'firoSubmitTx']);

    // Paiement Verge
    $router->get('/verge/payment/{id}', [DashboardController::class, 'vergePayment']);
    $router->get('/verge/status/{id}', [DashboardController::class, 'vergeStatus']);
    $router->post('/verge/submit-tx', [DashboardController::class, 'vergeSubmitTx']);

    // Paiement Pepecoin
    $router->get('/pepecoin/payment/{id}', [DashboardController::class, 'pepecoinPayment']);
    $router->get('/pepecoin/status/{id}', [DashboardController::class, 'pepecoinStatus']);
    $router->post('/pepecoin/submit-tx', [DashboardController::class, 'pepecoinSubmitTx']);
    
    // Paiement Vertcoin
    $router->get('/vertcoin/payment/{id}', [DashboardController::class, 'vertcoinPayment']);
    $router->get('/vertcoin/status/{id}', [DashboardController::class, 'vertcoinStatus']);
    $router->post('/vertcoin/submit-tx', [DashboardController::class, 'vertcoinSubmitTx']);
    
    // Paiement DragonX
    $router->get('/dragonx/payment/{id}', [DashboardController::class, 'dragonxPayment']);
    $router->get('/dragonx/status/{id}', [DashboardController::class, 'dragonxStatus']);
    $router->post('/dragonx/submit-tx', [DashboardController::class, 'dragonxSubmitTx']);

    // Paiement Monero
    $router->get('/monero/payment/{id}', [DashboardController::class, 'moneroPayment']);
    $router->get('/monero/status/{id}', [DashboardController::class, 'moneroStatus']);
    $router->post('/monero/submit-tx', [DashboardController::class, 'moneroSubmitTx']);

    // Liens
    $router->get('/links/add', [LinkController::class, 'addForm']);
    $router->post('/links/add', [LinkController::class, 'add']);
    $router->get('/links/my', [LinkController::class, 'myList']);
    $router->post('/links/delete/{id}', [LinkController::class, 'delete']);
    $router->post('/links/assign-points', [LinkController::class, 'assignPoints']);
    $router->post('/links/report', [LinkController::class, 'report']);

    // Bonus
    $router->get('/bonus/add', [BonusController::class, 'addForm']);
    $router->post('/bonus/add', [BonusController::class, 'add']);

    // Roue de la fortune
    $router->get('/wheel', [WheelController::class, 'index']);
    $router->post('/wheel/spin', [WheelController::class, 'spin']);

    // Balloon Pop-Up (ballon éclaté = points, tirage serveur)
    $router->post('/balloon/pop', [BalloonController::class, 'pop']);
}, [AuthMiddleware::class, CsrfMiddleware::class]);

// ========================================
// Administration
// ========================================
$router->group('/admin', function ($router) {
    $router->get('', [AdminController::class, 'index']);
    $router->get('/users', [AdminController::class, 'users']);
    $router->post('/users/edit', [AdminController::class, 'editUser']);
        $router->post('/users/points', [AdminController::class, 'addPoints']);
    $router->get('/links', [AdminController::class, 'links']);
    $router->get('/links/more', [AdminController::class, 'linksMore']);
    $router->post('/links/add', [AdminController::class, 'addLink']);
    $router->post('/links/edit', [AdminController::class, 'editLink']);
    $router->post('/links/delete', [AdminController::class, 'deleteLink']);
    $router->get('/reports', [AdminController::class, 'reports']);
    $router->post('/reports/resolve', [AdminController::class, 'resolveReport']);
    $router->get('/banners', [AdminController::class, 'banners']);
    $router->post('/banners/approve', [AdminController::class, 'approveBanner']);
    $router->post('/banners/add', [AdminController::class, 'addBanner']);
    $router->post('/banners/delete', [AdminController::class, 'deleteBanner']);
    $router->post('/banners/points', [AdminController::class, 'updateBannerPoints']);
    $router->get('/purchases', [AdminController::class, 'purchases']);
    $router->post('/purchases/confirm', [AdminController::class, 'confirmPurchase']);
    $router->post('/purchases/delete', [AdminController::class, 'deletePurchase']);
    $router->post('/purchases/clear', [AdminController::class, 'clearPurchases']);
    $router->get('/xelis/check', [AdminController::class, 'checkXelisPayments']);
    $router->get('/kaspa/check', [AdminController::class, 'checkKaspaPayments']);
    $router->get('/firo/check', [AdminController::class, 'checkFiroPayments']);
    $router->get('/verge/check', [AdminController::class, 'checkVergePayments']);
    $router->get('/pepecoin/check', [AdminController::class, 'checkPepecoinPayments']);
    $router->get('/vertcoin/check', [AdminController::class, 'checkVertcoinPayments']);
    $router->get('/dragonx/check', [AdminController::class, 'checkDragonxPayments']);
    $router->get('/monero/check', [AdminController::class, 'checkMoneroPayments']);
    $router->get('/settings', [AdminController::class, 'settings']);
    $router->post('/settings', [AdminController::class, 'settings']);
    $router->get('/affiliation', [AdminController::class, 'affiliation']);
    $router->post('/affiliation/payout', [AdminController::class, 'affiliationPayout']);
    $router->get('/blacklist', [AdminController::class, 'blacklist']);
    $router->post('/blacklist', [AdminController::class, 'blacklist']);
    $router->post('/blacklist/delete', [AdminController::class, 'deleteBlacklist']);
}, [AdminMiddleware::class, CsrfMiddleware::class]);
