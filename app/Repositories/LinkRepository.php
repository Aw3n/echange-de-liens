<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Repository des liens
 */
class LinkRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Trouve un lien par ID
     */
    public function findById(int $id): array|false
    {
        return $this->db->queryOne(
            "SELECT l.*, u.username, u.email
             FROM links l
             LEFT JOIN users u ON u.id = l.user_id
             WHERE l.id = ?",
            [$id]
        );
    }

    /**
     * Récupère le classement des liens
     */
    public function getRanking(int $limit = 50, int $offset = 0): array
    {
        return $this->db->query(
            "SELECT l.*, u.username
             FROM links l
             LEFT JOIN users u ON u.id = l.user_id
             WHERE l.is_active = 1 AND l.is_blacklisted = 0
             ORDER BY l.points DESC, l.created_at ASC
             LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
    }

    /**
     * Classement public filtré par visiteur : exclut les liens sur
     * lesquels ce visiteur (compte connecté ou IP) a déjà une visite
     * validée dans les dernières 24h (règle « 1 vote / 24h / lien »).
     * Le lien masqué réapparaît automatiquement délai écoulé.
     */
    public function getRankingFiltered(int $limit, int $offset, ?int $userId, string $ip): array
    {
        return $this->db->query(
            "SELECT l.*, u.username
             FROM links l
             LEFT JOIN users u ON u.id = l.user_id
             WHERE l.is_active = 1 AND l.is_blacklisted = 0
               AND NOT EXISTS (
                   SELECT 1 FROM link_visits lv
                   WHERE lv.link_id = l.id
                     AND lv.is_validated = 1
                     AND lv.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                     AND (lv.visitor_id = ? OR lv.visitor_ip = ?)
               )
             ORDER BY l.points DESC, l.created_at ASC
             LIMIT ? OFFSET ?",
            [$userId ?? 0, $ip, $limit, $offset]
        );
    }

    /**
     * Nombre de liens du classement filtré (pagination « Charger plus »)
     */
    public function countRankingFiltered(?int $userId, string $ip): int
    {
        $result = $this->db->queryOne(
            "SELECT COUNT(*) as total
             FROM links l
             WHERE l.is_active = 1 AND l.is_blacklisted = 0
               AND NOT EXISTS (
                   SELECT 1 FROM link_visits lv
                   WHERE lv.link_id = l.id
                     AND lv.is_validated = 1
                     AND lv.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                     AND (lv.visitor_id = ? OR lv.visitor_ip = ?)
               )",
            [$userId ?? 0, $ip]
        );
        return (int) ($result['total'] ?? 0);
    }

    /**
     * Récupère les liens d'un utilisateur
     */
    public function findByUserId(int $userId, int $limit = 50, int $offset = 0): array
    {
        return $this->db->query(
            "SELECT * FROM links WHERE user_id = ? ORDER BY points DESC, created_at ASC LIMIT ? OFFSET ?",
            [$userId, $limit, $offset]
        );
    }

    /**
     * Compte les liens d'un utilisateur
     */
    public function countByUserId(int $userId): int
    {
        return $this->db->count('links', 'user_id = ?', [$userId]);
    }

    /**
     * Crée un lien
     */
    public function create(array $data): int
    {
        return $this->db->insert('links', [
            'user_id' => $data['user_id'],
            'url' => $data['url'],
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'points' => $data['points'] ?? 0,
            'is_active' => 1,
            'http_status' => $data['http_status'] ?? null,
            'last_checked_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Met à jour un lien
     */
    public function update(int $id, array $data): int
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db->update('links', $data, 'id = ?', [$id]);
    }

    /**
     * Supprime un lien
     */
    public function delete(int $id): int
    {
        return $this->db->delete('links', 'id = ?', [$id]);
    }

    /**
     * Met à jour les points d'un lien
     */
    public function updatePoints(int $linkId, int $amount): int
    {
        return $this->db->execute(
            "UPDATE links SET points = GREATEST(0, points + ?) WHERE id = ?",
            [$amount, $linkId]
        );
    }

    /**
     * Incrémente le compteur de visites
     */
    public function incrementVisits(int $linkId): int
    {
        return $this->db->execute(
            "UPDATE links SET total_visits = total_visits + 1 WHERE id = ?",
            [$linkId]
        );
    }

    /**
     * Récupère tous les liens (admin)
     */
    public function findAll(int $limit = 50, int $offset = 0): array
    {
        return $this->db->query(
            "SELECT l.*, u.username
             FROM links l
             LEFT JOIN users u ON u.id = l.user_id
             ORDER BY l.created_at DESC
             LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
    }

    /**
     * Compte tous les liens
     */
    public function count(): int
    {
        return $this->db->count('links');
    }

    /**
     * Vérifie si l'URL est blacklistée
     */
    public function isBlacklisted(string $url): bool
    {
        $result = $this->db->queryOne(
            "SELECT COUNT(*) as total FROM blacklist WHERE ? LIKE CONCAT('%', url_pattern, '%')",
            [$url]
        );
        return (int) ($result['total'] ?? 0) > 0;
    }

    /**
     * Récupère les liens pour la prochaine visite
     * (liens des autres utilisateurs avec des points > 0)
     */
    public function getNextVisitLink(int $excludeUserId, int $excludeLinkId = 0): array|false
    {
        return $this->db->queryOne(
            "SELECT l.*, u.username
             FROM links l
             LEFT JOIN users u ON u.id = l.user_id
             WHERE l.is_active = 1
               AND l.is_blacklisted = 0
               AND l.points > 0
               AND l.user_id != ?
               AND l.id != ?
             ORDER BY l.points DESC, RAND()
             LIMIT 1",
            [$excludeUserId, $excludeLinkId]
        );
    }
}
