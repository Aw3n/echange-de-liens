<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Repository des utilisateurs
 */
class UserRepository
{
    private Database $db;

    /** Cache : la colonne created_ip existe-t-elle ? (sites installés avant l'ajout) */
    private static ?bool $hasCreatedIpColumn = null;

    /** Cache : la colonne referral_code existe-t-elle ? (sites installés avant l'ajout) */
    private static ?bool $hasReferralCodeColumn = null;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Détecte la présence de la colonne created_ip (compatibilité ascendante)
     */
    private function hasCreatedIpColumn(): bool
    {
        if (self::$hasCreatedIpColumn === null) {
            try {
                $result = $this->db->queryOne("SHOW COLUMNS FROM users LIKE 'created_ip'");
                self::$hasCreatedIpColumn = !empty($result);
            } catch (\Throwable $e) {
                self::$hasCreatedIpColumn = false;
            }
        }
        return self::$hasCreatedIpColumn;
    }

    /**
     * Trouve un utilisateur par ID
     */
    public function findById(int $id): array|false
    {
        return $this->db->queryOne(
            "SELECT u.*, r.slug as role_slug, r.name as role_name
             FROM users u
             LEFT JOIN roles r ON r.id = u.role_id
             WHERE u.id = ?",
            [$id]
        );
    }

    /**
     * Trouve un utilisateur par email
     */
    public function findByEmail(string $email): array|false
    {
        return $this->db->queryOne(
            "SELECT u.*, r.slug as role_slug, r.name as role_name
             FROM users u
             LEFT JOIN roles r ON r.id = u.role_id
             WHERE u.email = ?",
            [$email]
        );
    }

    /**
     * Détecte la présence de la colonne referral_code (compatibilité ascendante)
     */
    private function hasReferralCodeColumn(): bool
    {
        if (self::$hasReferralCodeColumn === null) {
            try {
                $result = $this->db->queryOne("SHOW COLUMNS FROM users LIKE 'referral_code'");
                self::$hasReferralCodeColumn = !empty($result);
            } catch (\Throwable $e) {
                self::$hasReferralCodeColumn = false;
            }
        }
        return self::$hasReferralCodeColumn;
    }

    /**
     * Trouve un utilisateur par code de parrainage
     */
    public function findByReferralCode(string $code): array|false
    {
        if (!$this->hasReferralCodeColumn()) {
            return false;
        }
        return $this->db->queryOne(
            "SELECT u.*, r.slug as role_slug FROM users u
             LEFT JOIN roles r ON r.id = u.role_id
             WHERE u.referral_code = ?",
            [$code]
        );
    }

    /**
     * Récupère (ou génère) le code de parrainage d'un utilisateur
     * Retourne une chaîne vide si la colonne n'existe pas encore
     */
    public function getReferralCode(int $userId): string
    {
        if (!$this->hasReferralCodeColumn()) {
            return '';
        }

        $user = $this->db->queryOne("SELECT referral_code FROM users WHERE id = ?", [$userId]);
        if (!$user) {
            return '';
        }

        if (!empty($user['referral_code'])) {
            return (string) $user['referral_code'];
        }

        // Génération avec retry en cas de collision (contrainte UNIQUE)
        for ($i = 0; $i < 3; $i++) {
            $code = bin2hex(random_bytes(5)); // 10 caractères hexadécimaux
            try {
                $updated = $this->db->execute(
                    "UPDATE users SET referral_code = ? WHERE id = ? AND referral_code IS NULL",
                    [$code, $userId]
                );
                if ($updated > 0) {
                    return $code;
                }
                // Généré par une requête concurrente : relire la valeur stockée
                $user = $this->db->queryOne("SELECT referral_code FROM users WHERE id = ?", [$userId]);
                return (string) ($user['referral_code'] ?? '');
            } catch (\Throwable $e) {
                continue;
            }
        }

        return '';
    }

    /**
     * Trouve un utilisateur par nom d'utilisateur
     */
    public function findByUsername(string $username): array|false
    {
        return $this->db->queryOne(
            "SELECT u.*, r.slug as role_slug FROM users u
             LEFT JOIN roles r ON r.id = u.role_id
             WHERE u.username = ?",
            [$username]
        );
    }

    /**
     * Trouve par token API
     */
    public function findByApiToken(string $token): array|false
    {
        return $this->db->queryOne(
            "SELECT u.*, r.slug as role_slug FROM users u
             LEFT JOIN roles r ON r.id = u.role_id
             WHERE u.api_token = ?",
            [$token]
        );
    }

    /**
     * Crée un utilisateur
     */
    public function create(array $data): int
    {
        $row = [
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role_id' => $data['role_id'] ?? 3,
            'referrer_id' => $data['referrer_id'] ?? null,
            'email_verification_token' => $data['email_verification_token'] ?? random_token(),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        if ($this->hasCreatedIpColumn()) {
            $row['created_ip'] = $data['created_ip'] ?? null;
        }

        if ($this->hasReferralCodeColumn()) {
            $row['referral_code'] = $data['referral_code'] ?? bin2hex(random_bytes(5));
        }

        return $this->db->insert('users', $row);
    }

    /**
     * Compte les inscriptions depuis une IP dans une fenêtre de temps
     * (anti-fraude : limite la création de comptes en masse)
     */
    public function countRegistrationsByIp(string $ip, int $windowSeconds = 86400): int
    {
        if (!$this->hasCreatedIpColumn()) {
            return 0;
        }
        $result = $this->db->queryOne(
            "SELECT COUNT(*) as total FROM users
             WHERE created_ip = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)",
            [$ip, $windowSeconds]
        );
        return (int) ($result['total'] ?? 0);
    }

    /**
     * Vérifie si un autre compte existe depuis la même IP
     * (anti-fraude : auto-parrainage avec comptes multiples)
     */
    public function hasOtherAccountFromIp(string $ip, int $excludeUserId): bool
    {
        if (!$this->hasCreatedIpColumn()) {
            return false;
        }
        $result = $this->db->queryOne(
            "SELECT COUNT(*) as total FROM users WHERE created_ip = ? AND id != ?",
            [$ip, $excludeUserId]
        );
        return (int) ($result['total'] ?? 0) > 0;
    }

    /**
     * Met à jour un utilisateur
     */
    public function update(int $id, array $data): int
    {
        return $this->db->update('users', $data, 'id = ?', [$id]);
    }

    /**
     * Met à jour les points
     */
    public function updatePoints(int $userId, int $amount): int
    {
        return $this->db->execute(
            "UPDATE users SET points = points + ? WHERE id = ?",
            [$amount, $userId]
        );
    }

    /**
     * Récupère tous les utilisateurs avec pagination
     */
    public function findAll(int $limit = 20, int $offset = 0): array
    {
        return $this->db->query(
            "SELECT u.*, r.slug as role_slug, r.name as role_name
             FROM users u
             LEFT JOIN roles r ON r.id = u.role_id
             ORDER BY u.created_at DESC
             LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
    }

    /**
     * Compte les utilisateurs
     */
    public function count(): int
    {
        return $this->db->count('users');
    }

    /**
     * Supprime un utilisateur
     */
    public function delete(int $id): int
    {
        return $this->db->delete('users', 'id = ?', [$id]);
    }

    /**
     * Récupère les filleuls d'un utilisateur
     */
    public function getReferrals(int $userId): array
    {
        return $this->db->query(
            "SELECT id, username, email, created_at FROM users WHERE referrer_id = ? ORDER BY created_at DESC",
            [$userId]
        );
    }

    /**
     * Compte les tentatives de connexion échouées
     */
    public function countFailedLogins(string $email, string $ip, int $window = 900): int
    {
        $result = $this->db->queryOne(
            "SELECT COUNT(*) as total FROM failed_logins
             WHERE (email = ? OR ip_address = ?)
             AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)",
            [$email, $ip, $window]
        );
        return (int) ($result['total'] ?? 0);
    }

    /**
     * Enregistre une tentative de connexion échouée
     */
    public function recordFailedLogin(string $email, string $ip, string $userAgent): void
    {
        $this->db->insert('failed_logins', [
            'email' => $email,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * Vérifie et enregistre une visite de lien de parrainage (1 par IP par 24h)
     * @return bool true si c'est une nouvelle visite unique
     */
    public function trackReferralVisit(int $referrerId, string $ip): bool
    {
        $today = date('Y-m-d');

        // Vérifier si cette IP a déjà visité aujourd'hui pour ce parrain
        $existing = $this->db->queryOne(
            "SELECT id FROM referral_visits WHERE referrer_id = ? AND visitor_ip = ? AND visit_date = ?",
            [$referrerId, $ip, $today]
        );

        if ($existing) {
            return false;
        }

        // Enregistrer la visite (la contrainte unique referrer_id/visitor_ip/visit_date
        // peut lever une exception si deux requêtes concurrentes arrivent en même temps)
        try {
            $this->db->insert('referral_visits', [
                'referrer_id' => $referrerId,
                'visitor_ip' => $ip,
                'visit_date' => $today,
            ]);
        } catch (\Throwable $e) {
            return false;
        }

        return true;
    }

    /**
     * Compte les visites de parrainage d'un utilisateur
     */
    public function countReferralVisits(int $userId): int
    {
        $result = $this->db->queryOne(
            "SELECT COUNT(*) as total FROM referral_visits WHERE referrer_id = ?",
            [$userId]
        );
        return (int) ($result['total'] ?? 0);
    }
}
