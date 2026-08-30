<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Repository de l'historique des points
 */
class PointsHistoryRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Enregistre un mouvement de points
     */
    public function record(array $data): int
    {
        return $this->db->insert('points_history', [
            'user_id' => $data['user_id'],
            'amount' => $data['amount'],
            'type' => $data['type'],
            'description' => $data['description'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
            'reference_type' => $data['reference_type'] ?? null,
        ]);
    }

    /**
     * Récupère l'historique d'un utilisateur
     */
    public function getByUserId(int $userId, int $limit = 50, int $offset = 0): array
    {
        return $this->db->query(
            "SELECT * FROM points_history WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?",
            [$userId, $limit, $offset]
        );
    }

    /**
     * Nombre total d'entrées d'historique d'un utilisateur
     * (pagination « Charger plus » du tableau de bord)
     */
    public function countByUserId(int $userId): int
    {
        $result = $this->db->queryOne(
            "SELECT COUNT(*) as total FROM points_history WHERE user_id = ?",
            [$userId]
        );
        return (int) ($result['total'] ?? 0);
    }

    /**
     * Calcule le total des points par type
     */
    public function getSummary(int $userId): array
    {
        return $this->db->query(
            "SELECT type, SUM(amount) as total, COUNT(*) as count
             FROM points_history
             WHERE user_id = ?
             GROUP BY type",
            [$userId]
        );
    }
}
