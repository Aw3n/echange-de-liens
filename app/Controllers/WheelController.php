<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Database;

/**
 * Contrôleur « Roue de la fortune » (wheel of fortune)
 *
 * Module installé et activé par défaut : un tour gratuit toutes les
 * wheel_interval_hours (défaut 3 h) pour tout utilisateur inscrit.
 * Le résultat (segment + points) est TOUJOURS décidé côté serveur
 * (tirage pondéré), puis la roue côté client s'anime vers ce segment :
 * le navigateur ne peut jamais choisir ni modifier le gain.
 *
 * Au lancement du tour, le lien wheel_url configuré par l'admin est
 * ouvert dans un nouvel onglet via window.open() SYNCHRONE dans le
 * gestionnaire de clic : c'est la seule ouverture d'onglet fiable
 * (geste utilisateur explicite, non bloquée par les anti-popups).
 */
class WheelController extends Controller
{
    /** Couleurs des 9 segments (lisibles sur tous les thèmes) */
    private const COLORS = [
        '#6366f1', '#10b981', '#f59e0b', '#ef4444', '#3b82f6',
        '#8b5cf6', '#14b8a6', '#f97316', '#ec4899',
    ];

    /**
     * Page de la roue
     */
    public function index(Request $request, Response $response): never
    {
        $this->requireAuth($request);
        $user = Session::user();
        $userId = (int) $user['id'];

        $db = Database::getInstance();
        $enabled = $this->setting($db, 'wheel_enabled', '1') === '1';
        $intervalHours = max(1, (int) $this->setting($db, 'wheel_interval_hours', '3'));
        $wheelUrl = trim((string) $this->setting($db, 'wheel_url', ''));

        // Segments configurés par l'admin (valeurs + poids + couleurs)
        $segments = [];
        for ($i = 1; $i <= 9; $i++) {
            $segments[] = [
                'value' => max(0, (int) $this->setting($db, "wheel_seg{$i}", '0')),
                'weight' => max(0, (int) $this->setting($db, "wheel_weight{$i}", '0')),
                'color' => self::COLORS[$i - 1],
            ];
        }

        // Prochain tour autorisé (cooldown serveur)
        $nextAt = 0;
        if ($enabled) {
            $last = $db->queryOne(
                "SELECT created_at FROM wheel_spins WHERE user_id = ? ORDER BY id DESC LIMIT 1",
                [$userId]
            );
            if ($last) {
                $nextAt = strtotime((string) $last['created_at']) + $intervalHours * 3600;
            }
        }

        // Page toujours fraîche : interdiction formelle de cache navigateur/proxy,
        // pour que les valeurs réglées par l'admin soient visibles immédiatement
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        $this->view('pages.wheel', [
            'title' => 'Roue de la fortune',
            'wheel_enabled' => $enabled,
            'wheel_segments' => $segments,
            'wheel_url' => $wheelUrl,
            'wheel_interval_hours' => $intervalHours,
            'wheel_next_at' => $nextAt,
            'wheel_csrf' => Session::csrfToken(),
            'page' => 'wheel',
        ]);
    }

    /**
     * AJAX : exécute un tour (cooldown + tirage pondéré + crédit atomique)
     */
    public function spin(Request $request, Response $response): void
    {
        $this->requireAuth($request);
        $user = Session::user();
        $userId = (int) $user['id'];

        $db = Database::getInstance();
        $fail = function (string $msg) use ($response): void {
            $response->json(['success' => false, 'message' => $msg], 400);
        };

        if ($this->setting($db, 'wheel_enabled', '1') !== '1') {
            $fail(tr('Module désactivé par l\'administrateur.'));
            return;
        }
        if (!$this->verifyCsrf($request)) {
            $fail(tr('Jeton CSRF invalide.'));
            return;
        }

        $intervalHours = max(1, (int) $this->setting($db, 'wheel_interval_hours', '3'));

        // Segments + poids (valeurs invalides ignorées)
        $values = [];
        $weights = [];
        for ($i = 1; $i <= 9; $i++) {
            $values[] = max(0, (int) $this->setting($db, "wheel_seg{$i}", '0'));
            $weights[] = max(0, (int) $this->setting($db, "wheel_weight{$i}", '0'));
        }
        $totalWeight = array_sum($weights);
        if ($totalWeight <= 0) {
            $fail(tr('Roue mal configurée (poids nuls).'));
            return;
        }

        try {
            $db->beginTransaction();

            // Cooldown anti double-tour : verrou sur le dernier spin
            $last = $db->queryOne(
                "SELECT created_at FROM wheel_spins WHERE user_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE",
                [$userId]
            );
            $now = time();
            if ($last && strtotime((string) $last['created_at']) + $intervalHours * 3600 > $now) {
                $db->rollBack();
                $fail(tr('Patience avant le prochain tour...'));
                return;
            }

            // Tirage pondéré côté serveur (le client ne choisit jamais)
            $r = random_int(1, $totalWeight);
            $index = 0;
            foreach ($weights as $i => $w) {
                $r -= $w;
                if ($r <= 0) {
                    $index = $i;
                    break;
                }
            }
            $points = $values[$index];

            $spinId = $db->insert('wheel_spins', [
                'user_id' => $userId,
                'points' => $points,
                'segment' => $index,
            ]);

            // Crédit atomique des points
            $db->execute("UPDATE users SET points = points + ? WHERE id = ?", [$points, $userId]);

            $db->insert('points_history', [
                'user_id' => $userId,
                'amount' => $points,
                'type' => 'wheel',
                'description' => 'Roue de la fortune : +' . $points . ' points',
                'reference_id' => $spinId,
                'reference_type' => 'wheel_spin',
            ]);

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->getPdo()->inTransaction()) {
                $db->rollBack();
            }
            $fail(tr('Erreur pendant le tour, réessayez.'));
            return;
        }

        $response->json([
            'success' => true,
            'index' => $index,
            'points' => $points,
            'next_at' => $now + $intervalHours * 3600,
        ]);
    }

    private function setting(Database $db, string $key, string $default): string
    {
        try {
            $row = $db->queryOne("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
            return (string) ($row['setting_value'] ?? $default);
        } catch (\Throwable $e) {
            return $default;
        }
    }
}
