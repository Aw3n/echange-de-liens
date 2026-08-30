<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Repositories\UserRepository;
use App\Repositories\PointsHistoryRepository;

/**
 * Service de cagnotte visiteur (guest wallet)
 *
 * Les visiteurs non connectés qui valident des visites de liens
 * accumulent des points dans un portefeuille identifié par un cookie
 * (le jeton brut vit dans le cookie, seul son hash SHA-256 est stocké
 * en base — une fuite de la base ne permet pas d'usurper une cagnotte).
 * À l'inscription, la cagnotte est transférée au nouveau compte puis
 * le portefeuille est définitivement consommé.
 */
class GuestWalletService
{
    /** Nom du cookie portant le jeton du portefeuille */
    private const COOKIE_NAME = 'guest_wallet';

    /** Durée de vie du cookie (90 jours) */
    private const COOKIE_TTL = 90 * 86400;

    private UserRepository $userRepo;
    private PointsHistoryRepository $pointsHistory;

    public function __construct()
    {
        $this->userRepo = new UserRepository();
        $this->pointsHistory = new PointsHistoryRepository();
    }

    /**
     * Solde de la cagnotte du visiteur courant (0 si aucune)
     */
    public function balanceFromCookie(): int
    {
        $wallet = $this->findWalletFromCookie();
        if (!$wallet || $wallet['claimed_user_id'] !== null) {
            return 0;
        }
        return (int) $wallet['points'];
    }

    /**
     * Crédite la cagnotte du visiteur courant (créée à la volée).
     * Anti-fraude : plafond de points invités par IP et par 24h —
     * au-delà, la visite reste validée mais la cagnotte n'est plus créditée.
     * @return array ['credited' => bool, 'total' => int]
     */
    public function creditForGuest(int $points, string $ip): array
    {
        $db = Database::getInstance();

        $maxPerDay = (int) Config::get('app.points.guest_max_per_day', 50);
        if ($maxPerDay > 0) {
            $today = $db->queryOne(
                "SELECT COALESCE(SUM(points_earned), 0) AS total FROM link_visits
                 WHERE visitor_id IS NULL AND visitor_ip = ? AND is_validated = 1
                   AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)",
                [$ip]
            );
            if ((int) ($today['total'] ?? 0) >= $maxPerDay) {
                return ['credited' => false, 'total' => $this->balanceFromCookie()];
            }
        }

        $tokenHash = $this->tokenHashFromCookie();

        if ($tokenHash === '') {
            // Première visite validée de ce navigateur : création du portefeuille
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $db->insert('guest_wallets', [
                'token_hash' => $tokenHash,
                'points' => $points,
                'last_ip' => $ip,
            ]);
            $this->setCookie($token);
            return ['credited' => true, 'total' => $points];
        }

        // Mise à jour atomique : jamais de crédit sur une cagnotte déjà réclamée
        $updated = $db->execute(
            "UPDATE guest_wallets SET points = points + ?, last_ip = ?
             WHERE token_hash = ? AND claimed_user_id IS NULL",
            [$points, $ip, $tokenHash]
        );

        if ($updated === 0) {
            // Jeton orphelin ou déjà consommé : nouveau portefeuille
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $db->insert('guest_wallets', [
                'token_hash' => $tokenHash,
                'points' => $points,
                'last_ip' => $ip,
            ]);
            $this->setCookie($token);
            return ['credited' => true, 'total' => $points];
        }

        $row = $db->queryOne("SELECT points FROM guest_wallets WHERE token_hash = ?", [$tokenHash]);
        return ['credited' => true, 'total' => (int) ($row['points'] ?? $points)];
    }

    /**
     * Transfère la cagnotte du visiteur vers son nouveau compte.
     * Anti-fraude : si un autre compte existe déjà depuis la même IP
     * (multi-comptes), la cagnotte est consommée sans être transférée —
     * même règle que le bonus de parrainage.
     * @return int Points effectivement transférés
     */
    public function claimForUser(int $userId, string $ip): int
    {
        $tokenHash = $this->tokenHashFromCookie();
        if ($tokenHash === '') {
            return 0;
        }

        $db = Database::getInstance();

        // Consommation atomique : un seul compte peut réclamer une cagnotte
        $updated = $db->execute(
            "UPDATE guest_wallets SET claimed_user_id = ?, claimed_at = NOW(), last_ip = ?
             WHERE token_hash = ? AND claimed_user_id IS NULL AND points > 0",
            [$userId, $ip, $tokenHash]
        );

        // Cookie consommé dans tous les cas (réclamée ou non)
        $this->forgetCookie();

        if ($updated === 0) {
            return 0;
        }

        $wallet = $db->queryOne("SELECT points FROM guest_wallets WHERE token_hash = ?", [$tokenHash]);
        $points = (int) ($wallet['points'] ?? 0);
        if ($points <= 0) {
            return 0;
        }

        // Multi-comptes depuis la même IP : cagnotte consommée mais non transférée
        if ($this->userRepo->hasOtherAccountFromIp($ip, $userId)) {
            return 0;
        }

        $db->beginTransaction();
        try {
            $this->userRepo->updatePoints($userId, $points);
            $this->pointsHistory->record([
                'user_id' => $userId,
                'amount' => $points,
                'type' => 'guest_wallet',
                'description' => 'Cagnotte de vos visites en tant que visiteur',
                'reference_id' => null,
                'reference_type' => 'guest_wallet',
            ]);
            $db->commit();
            return $points;
        } catch (\Throwable $e) {
            $db->rollBack();
            return 0;
        }
    }

    /**
     * Supprime le cookie de cagnotte (après réclamation)
     */
    public function forgetCookie(): void
    {
        if (isset($_COOKIE[self::COOKIE_NAME])) {
            setcookie(self::COOKIE_NAME, '', [
                'expires' => time() - 3600,
                'path' => '/',
                'samesite' => 'Lax',
                'httponly' => true,
            ]);
            unset($_COOKIE[self::COOKIE_NAME]);
        }
    }

    /**
     * Hash SHA-256 du jeton du cookie ('' si absent ou mal formé)
     */
    private function tokenHashFromCookie(): string
    {
        $token = trim((string) ($_COOKIE[self::COOKIE_NAME] ?? ''));
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return '';
        }
        return hash('sha256', $token);
    }

    /**
     * Portefeuille du visiteur courant (null si aucun)
     */
    private function findWalletFromCookie(): ?array
    {
        $tokenHash = $this->tokenHashFromCookie();
        if ($tokenHash === '') {
            return null;
        }

        try {
            $wallet = Database::getInstance()->queryOne(
                "SELECT * FROM guest_wallets WHERE token_hash = ?",
                [$tokenHash]
            );
        } catch (\Throwable $e) {
            return null; // Table absente (site non migré) : silencieux
        }

        return $wallet ?: null;
    }

    /**
     * Pose le cookie du portefeuille (90 jours, httponly)
     */
    private function setCookie(string $token): void
    {
        setcookie(self::COOKIE_NAME, $token, [
            'expires' => time() + self::COOKIE_TTL,
            'path' => '/',
            'samesite' => 'Lax',
            'httponly' => true,
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        ]);
        $_COOKIE[self::COOKIE_NAME] = $token;
    }
}
