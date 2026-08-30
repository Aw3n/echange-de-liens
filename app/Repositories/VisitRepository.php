<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Repository des visites
 */
class VisitRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Enregistre une visite
     */
    public function create(array $data): int
    {
        return $this->db->insert('link_visits', [
            'link_id' => $data['link_id'],
            'visitor_id' => $data['visitor_id'] ?? null,
            'visitor_ip' => $data['visitor_ip'],
            'visitor_user_agent' => $data['visitor_user_agent'] ?? null,
            'duration_seconds' => $data['duration_seconds'] ?? 0,
            'is_validated' => $data['is_validated'] ?? 0,
            'points_earned' => $data['points_earned'] ?? 0,
            'points_spent' => $data['points_spent'] ?? 0,
            'session_token' => $data['session_token'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Valide une visite
     */
    public function validate(int $visitId, int $pointsEarned = 1, int $pointsSpent = 1): int
    {
        return $this->db->update('link_visits', [
            'is_validated' => 1,
            'points_earned' => $pointsEarned,
            'points_spent' => $pointsSpent,
        ], 'id = ?', [$visitId]);
    }

    /**
     * Vérifie si une visite récente existe (anti-spam)
     */
    public function hasRecentVisit(int $linkId, string $ip, int $window = 300): bool
    {
        return $this->db->exists(
            'link_visits',
            'link_id = ? AND visitor_ip = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)',
            [$linkId, $ip, $window]
        );
    }

    /**
     * Règle « 1 vote par lien et par 24h » : vrai si ce visiteur
     * (compte connecté ou IP) a déjà une visite VALIDÉE sur ce lien
     * dans les dernières 24 heures. Sert aussi à masquer le lien du
     * classement tant que le délai n'est pas écoulé.
     */
    public function hasValidatedVisitInLast24h(int $linkId, ?int $visitorId, string $ip): bool
    {
        return $this->db->exists(
            'link_visits',
            'link_id = ? AND is_validated = 1 AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) AND (visitor_id = ? OR visitor_ip = ?)',
            [$linkId, $visitorId ?? 0, $ip]
        );
    }

    /**
     * Récupère les visites récentes d'un utilisateur
     */
    public function getRecentByUserId(int $userId, int $limit = 20): array
    {
        return $this->db->query(
            "SELECT lv.*, l.url, l.title
             FROM link_visits lv
             LEFT JOIN links l ON l.id = lv.link_id
             WHERE lv.visitor_id = ?
             ORDER BY lv.created_at DESC
             LIMIT ?",
            [$userId, $limit]
        );
    }

    /**
     * Récupère les statistiques de visites
     */
    public function getStats(int $userId): array
    {
        return $this->db->queryOne(
            "SELECT
                COUNT(*) as total_visits,
                SUM(CASE WHEN is_validated = 1 THEN 1 ELSE 0 END) as validated_visits,
                SUM(points_earned) as total_points_earned,
                SUM(points_spent) as total_points_spent,
                AVG(duration_seconds) as avg_duration
             FROM link_visits
             WHERE visitor_id = ?",
            [$userId]
        ) ?: [];
    }

    /**
     * Compte les visites totales du site
     */
    public function countTotal(): int
    {
        return $this->db->count('link_visits');
    }

    /**
     * Compte les visites d'aujourd'hui
     */
    public function countToday(): int
    {
        $result = $this->db->queryOne(
            "SELECT COUNT(*) as total FROM link_visits WHERE DATE(created_at) = CURDATE()"
        );
        return (int) ($result['total'] ?? 0);
    }
}
