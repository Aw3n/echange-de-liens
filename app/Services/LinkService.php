<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Repositories\LinkRepository;
use App\Repositories\PointsHistoryRepository;

/**
 * Service de gestion des liens
 */
class LinkService
{
    private LinkRepository $linkRepo;
    private PointsHistoryRepository $pointsHistory;

    public function __construct()
    {
        $this->linkRepo = new LinkRepository();
        $this->pointsHistory = new PointsHistoryRepository();
    }

    /**
     * Ajoute un nouveau lien
     * @return array{success: bool, message: string, link_id?: int}
     */
    public function addLink(int $userId, string $url, ?string $title = null): array
    {
        // Validation URL
        if (!filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with($url, 'https://')) {
            return ['success' => false, 'message' => 'URL invalide. Seules les URL HTTPS sont acceptées.'];
        }

        // Protection SSRF : refuser les hôtes internes/réservés
        // (l'URL sera requêtée côté serveur par check_url_status)
        if (!url_points_to_public_host($url)) {
            return ['success' => false, 'message' => 'Cette URL n\'est pas autorisée.'];
        }

        // Vérification blacklist
        if ($this->linkRepo->isBlacklisted($url)) {
            return ['success' => false, 'message' => 'Cette URL est dans la liste noire.'];
        }

        // Vérification HTTP
        $httpStatus = check_url_status($url);
        if ($httpStatus === null || $httpStatus >= 400) {
            return ['success' => false, 'message' => "Le site n'est pas accessible (code: " . ($httpStatus ?? 'inconnu') . ")."];
        }

        // Création du lien
        $linkId = $this->linkRepo->create([
            'user_id' => $userId,
            'url' => $url,
            'title' => $title ?: $url,
            'http_status' => $httpStatus,
        ]);

        return ['success' => true, 'message' => 'Lien ajouté avec succès.', 'link_id' => $linkId];
    }

    /**
     * Supprime un lien (vérifie la propriété)
     */
    public function deleteLink(int $linkId, int $userId, bool $isAdmin = false): bool
    {
        $link = $this->linkRepo->findById($linkId);
        if (!$link) {
            return false;
        }

        if (!$isAdmin && (int) $link['user_id'] !== $userId) {
            return false;
        }

        // Suppression atomique conditionnelle : empêche un double remboursement
        // si deux requêtes concurrentes arrivent en même temps
        if ($isAdmin) {
            $deleted = $this->linkRepo->delete($linkId);
        } else {
            $db = Database::getInstance();
            $deleted = $db->execute("DELETE FROM links WHERE id = ? AND user_id = ?", [$linkId, $userId]);
        }

        if ($deleted <= 0) {
            return false;
        }

        // Rembourse les points restants à l'utilisateur
        if ((int) $link['points'] > 0) {
            $db = Database::getInstance();
            $db->execute("UPDATE users SET points = points + ? WHERE id = ?", [(int) $link['points'], (int) $link['user_id']]);
            $this->pointsHistory->record([
                'user_id' => (int) $link['user_id'],
                'amount' => (int) $link['points'],
                'type' => 'refund',
                'description' => "Remboursement points lien supprimé #{$linkId}",
                'reference_id' => $linkId,
                'reference_type' => 'link',
            ]);
        }

        return true;
    }

    /**
     * Attribue des points à un lien
     */
    public function assignPoints(int $linkId, int $userId, int $points): array
    {
        if ($points <= 0) {
            return ['success' => false, 'message' => 'Nombre de points invalide.'];
        }

        $link = $this->linkRepo->findById($linkId);
        if (!$link || (int) $link['user_id'] !== $userId) {
            return ['success' => false, 'message' => 'Lien non trouvé.'];
        }

        $db = Database::getInstance();

        // Débit atomique conditionnel : impossible de passer en négatif,
        // même avec des requêtes concurrentes (race condition)
        $debited = $db->execute(
            "UPDATE users SET points = points - ? WHERE id = ? AND points >= ?",
            [$points, $userId, $points]
        );

        if ($debited === 0) {
            return ['success' => false, 'message' => 'Points insuffisants.'];
        }

        // Crédite le lien
        $this->linkRepo->updatePoints($linkId, $points);

        $this->pointsHistory->record([
            'user_id' => $userId,
            'amount' => -$points,
            'type' => 'spend_visit',
            'description' => "Attribution de {$points} points au lien #{$linkId}",
            'reference_id' => $linkId,
            'reference_type' => 'link',
        ]);

        return ['success' => true, 'message' => "{$points} points attribués au lien."];
    }

    /**
     * Récupère le classement
     */
    public function getRanking(int $page = 1, int $perPage = 50, ?int $userId = null, string $ip = ''): array
    {
        $offset = ($page - 1) * $perPage;
        // Classement filtré par visiteur : les liens déjà votés dans les
        // 24h (visite validée) sont masqués pour ce visiteur
        $links = $this->linkRepo->getRankingFiltered($perPage, $offset, $userId, $ip);
        $total = $this->linkRepo->countRankingFiltered($userId, $ip);

        return [
            'links' => $links,
            'total' => $total,
            'pages' => (int) ceil($total / $perPage),
            'current_page' => $page,
            'per_page' => $perPage,
        ];
    }
}
