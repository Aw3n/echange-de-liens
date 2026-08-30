<?php
declare(strict_types=1);

/**
 * Routes API REST
 */

use App\Controllers\Api\ApiController;
use App\Middleware\ApiAuthMiddleware;

/** @var \App\Core\App $this */
$router = $this->getRouter();

// ========================================
// Endpoints publics
// ========================================
$router->get('/api/links', [ApiController::class, 'getLinks']);
$router->get('/api/ranking', [ApiController::class, 'getRanking']);
$router->get('/api/stats', [ApiController::class, 'getStats']);
$router->post('/api/login', [ApiController::class, 'login']);
$router->post('/api/register', [ApiController::class, 'register']);

// ========================================
// Endpoints authentifiés (Bearer Token)
// ========================================
$router->group('/api', function ($router) {
    $router->post('/links', [ApiController::class, 'createLink']);
    $router->put('/links/{id}', [ApiController::class, 'updateLink']);
    $router->delete('/links/{id}', [ApiController::class, 'deleteLink']);
    $router->post('/visit', [ApiController::class, 'startVisit']);
}, [ApiAuthMiddleware::class]);
