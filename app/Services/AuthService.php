<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Session;
use App\Repositories\UserRepository;
use App\Repositories\PointsHistoryRepository;

/**
 * Service d'authentification
 * Gère l'inscription, la connexion et la déconnexion
 */
class AuthService
{
    private UserRepository $userRepo;
    private PointsHistoryRepository $pointsHistory;

    public function __construct()
    {
        $this->userRepo = new UserRepository();
        $this->pointsHistory = new PointsHistoryRepository();
    }

    /**
     * Authentifie un utilisateur
     * @return array|false Utilisateur ou false si échec
     */
    public function login(string $email, string $password, string $ip, string $userAgent): array|false
    {
        // Vérification rate limit
        $maxAttempts = Config::get('app.security.rate_limit.login', 5);
        $window = Config::get('app.security.rate_limit.window', 900);

        if ($this->userRepo->countFailedLogins($email, $ip, $window) >= $maxAttempts) {
            return false;
        }

        $user = $this->userRepo->findByEmail($email);

        if (!$user || !password_verify($password, $user['password'])) {
            $this->userRepo->recordFailedLogin($email, $ip, $userAgent);
            return false;
        }

        if (!$user['is_active']) {
            return false;
        }

        // Mise à jour dernière connexion
        $this->userRepo->update((int) $user['id'], [
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => $ip,
        ]);

        // Connexion en session
        Session::login([
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role_id' => (int) $user['role_id'],
            'role_slug' => $user['role_slug'],
            'points' => (int) $user['points'],
        ]);

        return $user;
    }

    /**
     * Inscrit un nouvel utilisateur
     * @return int ID de l'utilisateur créé
     */
    public function register(array $data, ?int $referrerId = null, string $ip = ''): int
    {
        // Hash du mot de passe (Argon2id)
        $data['password'] = password_hash($data['password'], PASSWORD_ARGON2ID);
        $data['role_id'] = 3; // Membre
        $data['referrer_id'] = $referrerId;
        // Jeton de vérification d'email : le jeton brut part dans l'email,
        // seul son hash SHA-256 est stocké (même principe que password_resets) —
        // une fuite de la base ne révèle aucun jeton utilisable
        $verifyToken = random_token();
        $data['email_verification_token'] = hash('sha256', $verifyToken);
        if ($ip !== '') {
            $data['created_ip'] = $ip;
        }

        $userId = $this->userRepo->create($data);

        // Email de vérification d'adresse (non bloquant si l'envoi échoue)
        $verifyUrl = site_url('verify-email?token=' . $verifyToken);
        (new MailService())->sendEmailVerification($data['email'], $verifyUrl, $this->getSiteName());

        // Bonus de parrainage
        // Anti-fraude : refusé si un autre compte existe déjà depuis la même IP
        // (empêche l'auto-parrainage avec des comptes multiples)
        $bonusAllowed = $ip === '' || !$this->userRepo->hasOtherAccountFromIp($ip, $userId);

        if ($referrerId && $bonusAllowed) {
            $referralPoints = Config::get('app.points.referral_bonus', 1000);
            $this->userRepo->updatePoints($referrerId, $referralPoints);
            $this->pointsHistory->record([
                'user_id' => $referrerId,
                'amount' => $referralPoints,
                'type' => 'referral',
                'description' => "Bonus parrainage - Nouveau filleul #{$userId}",
                'reference_id' => $userId,
                'reference_type' => 'user',
            ]);
        }

        return $userId;
    }

    /**
     * Déconnecte l'utilisateur
     */
    public function logout(): void
    {
        Session::logout();
    }

    /**
     * Vérifie si l'email existe déjà
     */
    public function emailExists(string $email): bool
    {
        return $this->userRepo->findByEmail($email) !== false;
    }

    /**
     * Vérifie si le username existe déjà
     */
    public function usernameExists(string $username): bool
    {
        return $this->userRepo->findByUsername($username) !== false;
    }

    /**
     * Change le mot de passe
     */
    public function changePassword(int $userId, string $newPassword): int
    {
        return $this->userRepo->update($userId, [
            'password' => password_hash($newPassword, PASSWORD_ARGON2ID),
        ]);
    }

    /**
     * Génère un token API pour l'utilisateur
     */
    public function generateApiToken(int $userId, string $name = 'default'): string
    {
        $token = bin2hex(random_bytes(64));
        $this->userRepo->update($userId, ['api_token' => $token]);

        $db = Database::getInstance();
        $db->insert('api_tokens', [
            'user_id' => $userId,
            'token' => $token,
            'name' => $name,
        ]);

        return $token;
    }

    /**
     * Traite une visite sur un lien de parrainage
     * Accorde +1 point au parrain par IP unique par 24h
     */
    public function trackReferralLinkVisit(int $referrerId, string $ip): bool
    {
        // Vérifier que le parrain existe
        $referrer = $this->userRepo->findById($referrerId);
        if (!$referrer || !$referrer['is_active']) {
            return false;
        }

        // Tracker la visite (retourne true si nouvelle IP/24h)
        $isNew = $this->userRepo->trackReferralVisit($referrerId, $ip);

        if ($isNew) {
            $points = (int) Config::get('app.points.referral_visit_points', 1);

            // Ajouter les points au parrain
            $this->userRepo->updatePoints($referrerId, $points);

            // Enregistrer dans l'historique
            $this->pointsHistory->record([
                'user_id' => $referrerId,
                'amount' => $points,
                'type' => 'referral_visit',
                'description' => "Visite lien parrainage depuis IP " . substr(md5($ip), 0, 8),
                'reference_id' => null,
                'reference_type' => 'referral_visit',
            ]);

            return true;
        }

        return false;
    }

    /**
     * Résout un parrain à partir d'un ID numérique ou d'un code de parrainage
     * @return int|null ID du parrain actif, ou null si introuvable/inactif
     */
    public function resolveReferrer(string $ref): ?int
    {
        $ref = trim($ref);
        if ($ref === '') {
            return null;
        }

        if (ctype_digit($ref)) {
            $user = $this->userRepo->findById((int) $ref);
        } elseif (preg_match('/^[a-f0-9]{4,16}$/', $ref)) {
            $user = $this->userRepo->findByReferralCode($ref);
        } else {
            return null;
        }

        if (!$user || !$user['is_active']) {
            return null;
        }

        return (int) $user['id'];
    }

    /**
     * Traite une arrivée via un lien de parrainage (?ref=...) sur n'importe quelle page :
     * - résout le parrain (ID numérique ou code hexadécimal)
     * - accorde +1 point par IP unique / 24h au parrain
     * - mémorise le parrain en session ET dans un cookie 30 jours,
     *   pour que l'inscription soit affiliée même si elle a lieu plus tard
     * @return int|null ID du parrain résolu
     */
    public function handleReferral(string $ref, string $ip): ?int
    {
        $referrerId = $this->resolveReferrer($ref);
        if ($referrerId === null) {
            return null;
        }

        // +1 point par IP unique / 24h (idempotent via contrainte unique)
        $this->trackReferralLinkVisit($referrerId, $ip);

        // Mémorisation en session (utilisée à l'inscription)
        Session::set('referral_ref', $referrerId);

        // Cookie 30 jours : l'attribution survit à la fermeture du navigateur.
        // Stocke le code de parrainage si disponible (plus stable qu'un ID brut).
        $cookieValue = $this->userRepo->getReferralCode($referrerId);
        if ($cookieValue === '') {
            $cookieValue = (string) $referrerId;
        }

        setcookie('ref_code', $cookieValue, [
            'expires' => time() + 30 * 86400,
            'path' => '/',
            'samesite' => 'Lax',
            'httponly' => true,
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        ]);

        return $referrerId;
    }

    /**
     * Résout le parrain mémorisé dans le cookie ref_code (si présent)
     */
    public function referrerFromCookie(): ?int
    {
        $value = trim((string) ($_COOKIE['ref_code'] ?? ''));
        if ($value === '') {
            return null;
        }
        return $this->resolveReferrer($value);
    }

    /**
     * Demande de réinitialisation de mot de passe
     * Anti-énumération : ne révèle jamais si l'email existe ou non
     */
    public function requestPasswordReset(string $email, string $ip): void
    {
        $db = Database::getInstance();

        // Limitation : max 3 demandes par email sur 15 minutes (anti-spam)
        try {
            $recent = $db->queryOne(
                "SELECT COUNT(*) as total FROM password_resets
                 WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)",
                [$email]
            );
            if ((int) ($recent['total'] ?? 0) >= 3) {
                return;
            }
        } catch (\Throwable $e) {
            // Table password_resets absente (site non migré) : abandon silencieux
            return;
        }

        $user = $this->userRepo->findByEmail($email);
        if (!$user || !$user['is_active']) {
            return; // Email inconnu : aucune action, aucun indice renvoyé
        }

        $token = bin2hex(random_bytes(32));
        $db->insert('password_resets', [
            'email' => $email,
            'token_hash' => hash('sha256', $token),
            'ip_address' => $ip,
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);

        $resetUrl = site_url('reset-password?token=' . $token);
        (new MailService())->sendPasswordReset($email, $resetUrl, $this->getSiteName());
    }

    /**
     * Recherche un jeton de réinitialisation valide (non utilisé, non expiré)
     */
    public function findValidPasswordReset(string $token): array|false
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return false;
        }
        $db = Database::getInstance();
        return $db->queryOne(
            "SELECT * FROM password_resets
             WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()",
            [hash('sha256', $token)]
        );
    }

    /**
     * Réinitialise le mot de passe avec un jeton valide
     */
    public function resetPassword(string $token, string $newPassword): bool
    {
        $reset = $this->findValidPasswordReset($token);
        if (!$reset) {
            return false;
        }

        $user = $this->userRepo->findByEmail((string) $reset['email']);
        if (!$user) {
            return false;
        }

        $this->changePassword((int) $user['id'], $newPassword);

        // Marque le jeton comme utilisé (réutilisation impossible)
        $db = Database::getInstance();
        $db->execute("UPDATE password_resets SET used_at = NOW() WHERE id = ?", [(int) $reset['id']]);

        return true;
    }

    /**
     * Vérifie l'adresse email d'un utilisateur via son jeton
     */
    public function verifyEmail(string $token): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return false;
        }
        $db = Database::getInstance();
        $updated = $db->execute(
            "UPDATE users SET email_verified = 1, email_verification_token = NULL
             WHERE email_verification_token = ? AND email_verified = 0",
            [hash('sha256', $token)]
        );
        return $updated > 0;
    }

    /**
     * Nom du site depuis les settings (pour les emails)
     */
    private function getSiteName(): string
    {
        try {
            $db = Database::getInstance();
            $result = $db->queryOne(
                "SELECT setting_value FROM settings WHERE setting_key = 'site_name'"
            );
            return $result['setting_value'] ?: 'Echange de Liens';
        } catch (\Throwable $e) {
            return 'Echange de Liens';
        }
    }
}
