<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Session;
use App\Repositories\VisitRepository;
use App\Repositories\LinkRepository;
use App\Repositories\UserRepository;
use App\Repositories\PointsHistoryRepository;

/**
 * Service de gestion des visites
 * Gère le système de visite, les points et l'anti-fraude
 */
class VisitService
{
    private VisitRepository $visitRepo;
    private LinkRepository $linkRepo;
    private UserRepository $userRepo;
    private PointsHistoryRepository $pointsHistory;

    public function __construct()
    {
        $this->visitRepo = new VisitRepository();
        $this->linkRepo = new LinkRepository();
        $this->userRepo = new UserRepository();
        $this->pointsHistory = new PointsHistoryRepository();
    }

    /**
     * Démarre une visite (crée l'enregistrement et retourne les infos)
     */
    public function startVisit(int $linkId, ?int $visitorId, string $ip, string $userAgent): array
    {
        $link = $this->linkRepo->findById($linkId);

        if (!$link || !$link['is_active'] || $link['is_blacklisted']) {
            return ['success' => false, 'message' => 'Lien non disponible.'];
        }

        // Anti auto-visite
        if ($visitorId && (int) $link['user_id'] === $visitorId) {
            return ['success' => false, 'message' => 'Vous ne pouvez pas visiter vos propres liens.'];
        }

        // Anti-spam : pas de double visite sur le même lien dans les 5 minutes
        if ($this->visitRepo->hasRecentVisit($linkId, $ip)) {
            return ['success' => false, 'message' => 'Vous avez déjà visité ce lien récemment.'];
        }

        // Règle de vote : 1 visite validée par lien et par 24h
        // (compte connecté ou IP) — le lien masqué du classement
        // réapparaît automatiquement une fois le délai écoulé
        if ($this->visitRepo->hasValidatedVisitInLast24h($linkId, $visitorId, $ip)) {
            return ['success' => false, 'message' => 'Vous avez déjà voté pour ce lien dans les dernières 24 heures.'];
        }

        $sessionToken = bin2hex(random_bytes(16));

        $visitId = $this->visitRepo->create([
            'link_id' => $linkId,
            'visitor_id' => $visitorId,
            'visitor_ip' => $ip,
            'visitor_user_agent' => $userAgent,
            'session_token' => $sessionToken,
        ]);

        return [
            'success' => true,
            'visit_id' => $visitId,
            'link' => $link,
            'session_token' => $sessionToken,
            'duration' => Config::get('app.points.visit_duration', 5),
        ];
    }

    /**
     * Valide une visite après le temps minimum
     * Sécurité : la durée est vérifiée côté SERVEUR (created_at) et le
     * session_token remis au démarrage de la visite doit être renvoyé.
     */
    public function validateVisit(int $visitId, int $duration, string $sessionToken = ''): array
    {
        $minDuration = Config::get('app.points.visit_duration', 5);

        if ($duration < $minDuration) {
            return ['success' => false, 'message' => 'Durée de visite insuffisante.'];
        }

        // Récupère la visite
        $db = Database::getInstance();
        $visit = $db->queryOne("SELECT * FROM link_visits WHERE id = ?", [$visitId]);

        if (!$visit || $visit['is_validated']) {
            return ['success' => false, 'message' => 'Visite non trouvée ou déjà validée.'];
        }

        // Anti-fraude : vérifier le token de session (empêche de valider une visite
        // dont on aurait deviné l'ID sans être passé par la visionneuse)
        if (empty($sessionToken) || !hash_equals((string) ($visit['session_token'] ?? ''), $sessionToken)) {
            return ['success' => false, 'message' => 'Session de visite invalide.'];
        }

        // Anti-fraude : la durée réelle est calculée côté serveur,
        // la valeur "duration" envoyée par le client n'est pas fiable
        $elapsed = time() - strtotime((string) $visit['created_at']);
        if ($elapsed < $minDuration) {
            return ['success' => false, 'message' => 'Durée de visite insuffisante.'];
        }

        $visitorId = $visit['visitor_id'] ? (int) $visit['visitor_id'] : null;
        $linkId = (int) $visit['link_id'];
        $pointsPerVisit = Config::get('app.points.per_visit', 1);

        // Validation atomique : empêche une double validation concurrente (race condition)
        $updated = $db->execute(
            "UPDATE link_visits
             SET is_validated = 1, points_earned = ?, points_spent = ?, duration_seconds = ?
             WHERE id = ? AND is_validated = 0",
            [$pointsPerVisit, $pointsPerVisit, min($elapsed, 86400), $visitId]
        );

        if ($updated === 0) {
            return ['success' => false, 'message' => 'Visite déjà validée.'];
        }

        // Transaction pour la cohérence des points
        $db->beginTransaction();

        try {
            // +1 point au visiteur
            if ($visitorId) {
                $this->userRepo->updatePoints($visitorId, $pointsPerVisit);
                $this->pointsHistory->record([
                    'user_id' => $visitorId,
                    'amount' => $pointsPerVisit,
                    'type' => 'earn_visit',
                    'description' => "Visite du lien #{$linkId}",
                    'reference_id' => $linkId,
                    'reference_type' => 'link',
                ]);
            }

            // -1 point au lien visité
            $this->linkRepo->updatePoints($linkId, -$pointsPerVisit);
            $this->linkRepo->incrementVisits($linkId);

            $db->commit();

            // Module Balloon Pop-Up : compte les visites validées des
            // utilisateurs connectés (toutes les X visites → ballons).
            // Hors transaction et jamais bloquant.
            if ($visitorId) {
                balloon_register_validated_visit($visitorId);
            }

            $result = [
                'success' => true,
                'message' => "Visite validée ! +{$pointsPerVisit} point(s).",
                'points_earned' => $pointsPerVisit,
            ];

            // Visiteur non connecté : les points partent dans sa cagnotte
            // invité (cookie), transférable lors de son inscription.
            if (!$visitorId) {
                try {
                    $wallet = (new GuestWalletService())->creditForGuest($pointsPerVisit, (string) $visit['visitor_ip']);
                    $result['guest'] = true;
                    $result['wallet_credited'] = $wallet['credited'];
                    $result['wallet_total'] = $wallet['total'];
                } catch (\Throwable $e) {
                    // Cagnotte indisponible : la visite reste validée
                }
            }

            return $result;
        } catch (\Throwable $e) {
            $db->rollBack();
            return ['success' => false, 'message' => 'Erreur lors de la validation.'];
        }
    }

    /**
     * Valide un clic bannière (page bonus)
     * Anti-spam : 1 clic crédité par IP et par bannière toutes les 5 minutes (table banner_clicks)
     */
    public function validateBannerClick(int $adId, ?int $visitorId, string $ip, int $duration): array
    {
        $minDuration = Config::get('app.points.bonus_visit_duration', 15);
        $pointsPerClick = Config::get('app.points.per_banner_click', 5);

        if ($duration < $minDuration) {
            return ['success' => false, 'message' => 'Durée insuffisante (min ' . $minDuration . 's).'];
        }

        $db = Database::getInstance();
        $ad = $db->queryOne("SELECT * FROM ads WHERE id = ? AND is_active = 1 AND is_approved = 1", [$adId]);

        if (!$ad) {
            return ['success' => false, 'message' => 'Bannière non disponible.'];
        }

        // Le propriétaire ne peut pas gagner de points sur sa propre bannière
        if ($visitorId !== null && (int) ($ad['user_id'] ?? 0) === $visitorId) {
            return ['success' => false, 'message' => 'Impossible de gagner des points sur votre propre bannière.'];
        }

        // Anti-spam : 1 clic crédité par IP/bannière toutes les 5 minutes
        $recent = $db->queryOne(
            "SELECT COUNT(*) as total FROM banner_clicks
             WHERE ad_id = ? AND visitor_ip = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)",
            [$adId, $ip]
        );

        if ((int) ($recent['total'] ?? 0) > 0) {
            return ['success' => false, 'message' => 'Déjà visité récemment.'];
        }

        if ($visitorId !== null) {
            // Bannière dotée d'un budget (assigné par l'admin ou propriétaire
            // utilisateur) : débite atomiquement le budget (5 pts/clic).
            // Une bannière admin sans budget reste promotionnelle (clic compté
            // sans débit).
            if ((int) $ad['user_id'] > 0 || (int) $ad['points_assigned'] > 0) {
                $updated = $db->execute(
                    "UPDATE ads SET points_assigned = GREATEST(0, points_assigned - ?), clicks = clicks + 1
                     WHERE id = ? AND is_active = 1 AND is_approved = 1 AND points_assigned >= ?",
                    [$pointsPerClick, $adId, $pointsPerClick]
                );
                if ($updated === 0) {
                    return ['success' => false, 'message' => 'Budget de la bannière épuisé.'];
                }
            } else {
                $db->execute("UPDATE ads SET clicks = clicks + 1 WHERE id = ?", [$adId]);
            }

            // Enregistre le clic (verrou anti-spam)
            $db->insert('banner_clicks', [
                'ad_id' => $adId,
                'visitor_id' => $visitorId,
                'visitor_ip' => $ip,
                'points_earned' => $pointsPerClick,
            ]);

            // Crédite les points au visiteur
            $this->userRepo->updatePoints($visitorId, $pointsPerClick);
            $this->pointsHistory->record([
                'user_id' => $visitorId,
                'amount' => $pointsPerClick,
                'type' => 'banner_click',
                'description' => "Clic bannière bonus #{$adId}",
                'reference_id' => $adId,
                'reference_type' => 'ad',
            ]);

            return [
                'success' => true,
                'message' => "+{$pointsPerClick} points bonus !",
                'points_earned' => $pointsPerClick,
            ];
        }

        // Visiteur non connecté : clic compté mais aucun point distribué,
        // le budget du propriétaire n'est pas débité.
        $db->execute("UPDATE ads SET clicks = clicks + 1 WHERE id = ?", [$adId]);
        $db->insert('banner_clicks', [
            'ad_id' => $adId,
            'visitor_id' => null,
            'visitor_ip' => $ip,
            'points_earned' => 0,
        ]);

        return ['success' => false, 'message' => 'Connectez-vous pour gagner des points bonus.'];
    }
}
