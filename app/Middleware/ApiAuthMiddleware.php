<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Database;
use App\Core\Request;

/**
 * Middleware API
 * Authentification par Bearer Token
 */
class ApiAuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request): bool
    {
        $token = $request->getBearerToken();

        if (!$token) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Token d\'authentification requis']);
            exit(401);
        }

        $db = Database::getInstance();
        $apiToken = $db->queryOne(
            "SELECT at.*, u.id as user_id, u.username, u.role_id
             FROM api_tokens at
             JOIN users u ON u.id = at.user_id
             WHERE at.token = ? AND (at.expires_at IS NULL OR at.expires_at > NOW())",
            [$token]
        );

        if (!$apiToken) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Token invalide ou expiré']);
            exit(401);
        }

        // Met à jour la date de dernière utilisation
        $db->execute("UPDATE api_tokens SET last_used_at = NOW() WHERE id = ?", [(int) $apiToken['id']]);

        // Stocke les infos utilisateur dans les paramètres de la requête
        $request->setParam('api_user', $apiToken);
        $request->setParam('api_user_id', (int) $apiToken['user_id']);

        return true;
    }
}
