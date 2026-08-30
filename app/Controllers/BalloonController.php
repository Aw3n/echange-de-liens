<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Database;

/**
 * Module « Balloon Pop-Up » (optionnel, config via panneau admin).
 * Le déclenchement (toutes les X visites validées) est géré par
 * VisitService + helpers balloon_* ; ce contrôleur ne gère que le
 * gain de points quand un ballon est éclaté : tirage 100% serveur.
 */
class BalloonController extends Controller
{
    /**
     * Ballon éclaté (AJAX, un appel par ballon cliqué) :
     * tirage aléatoire entre balloon_points_min et balloon_points_max
     * côté serveur (jamais confié au navigateur), crédit atomique du
     * solde + historique de points type « balloon ».
     */
    public function pop(Request $request, Response $response): never
    {
        $this->requireAuth($request);

        if (!$this->verifyCsrf($request)) {
            $this->json(['success' => false, 'message' => tr('Jeton CSRF invalide.')], 400);
        }

        if (setting_value('balloon_enabled', '1') !== '1') {
            $this->json(['success' => false, 'points' => 0], 403);
        }

        $min = max(0, (int) setting_value('balloon_points_min', '1'));
        $max = max($min, (int) setting_value('balloon_points_max', '15'));
        $points = random_int($min, $max);

        $userId = (int) Session::user()['id'];
        $db = Database::getInstance();

        $db->beginTransaction();
        try {
            // Crédit atomique (pas de lecture-modification-écriture)
            $db->execute("UPDATE users SET points = points + ? WHERE id = ?", [$points, $userId]);

            $db->insert('points_history', [
                'user_id' => $userId,
                'amount' => $points,
                'type' => 'balloon',
                'description' => 'Ballon éclaté : +' . $points . ' points',
                'reference_id' => null,
                'reference_type' => 'balloon',
            ]);

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->getPdo()->inTransaction()) {
                $db->rollBack();
            }
            $this->json(['success' => false, 'message' => tr('Une erreur est survenue, réessayez.')], 500);
        }

        $this->json(['success' => true, 'points' => $points]);
    }
}
