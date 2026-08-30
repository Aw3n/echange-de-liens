<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;

/**
 * Interface Middleware
 */
interface MiddlewareInterface
{
    /**
     * Traite la requête
     * @return bool true pour continuer, false pour arrêter
     */
    public function handle(Request $request): bool;
}
