<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Gestionnaire de sessions sécurisées
 */
class Session
{
    private static bool $started = false;

    /**
     * Démarre la session avec des paramètres sécurisés
     */
    public static function start(): void
    {
        if (self::$started) {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', '1');
            // Cookie secure automatique si la connexion est HTTPS
            $isHttps = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
                || ($_SERVER['SERVER_PORT'] ?? 0) == 443;
            ini_set('session.cookie_secure', $isHttps ? '1' : '0');
            ini_set('session.cookie_samesite', 'Lax');
            ini_set('session.use_strict_mode', '1');
            ini_set('session.gc_maxlifetime', (string) Config::get('app.security.session_lifetime', 7200));

            session_name('ECHANGE_LIEN_SID');
            session_start();
        }

        self::$started = true;
    }

    /**
     * Récupère une valeur de session
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Définit une valeur de session
     */
    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    /**
     * Supprime une valeur de session
     */
    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    /**
     * Vérifie si une clé existe
     */
    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    /**
     * Récupère et supprime un message flash
     */
    public static function flash(string $key, mixed $default = null): mixed
    {
        $value = self::get("_flash.{$key}", $default);
        self::remove("_flash.{$key}");
        return $value;
    }

    /**
     * Définit un message flash
     */
    public static function setFlash(string $key, mixed $value): void
    {
        self::set("_flash.{$key}", $value);
    }

    /**
     * Récupère l'utilisateur connecté
     */
    public static function user(): ?array
    {
        return self::get('user');
    }

    /**
     * Vérifie si un utilisateur est connecté
     */
    public static function isLoggedIn(): bool
    {
        return self::get('user') !== null;
    }

    /**
     * Connecte un utilisateur
     */
    public static function login(array $user): void
    {
        self::regenerate();
        self::set('user', $user);
        self::set('login_time', time());
    }

    /**
     * Déconnecte l'utilisateur
     */
    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        session_destroy();
        self::$started = false;
    }

    /**
     * Régénère l'ID de session (anti-fixation)
     */
    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
    }

    /**
     * Génère un jeton CSRF
     */
    public static function csrfToken(): string
    {
        $token = self::get('_csrf_token');
        if (!$token) {
            $token = bin2hex(random_bytes(32));
            self::set('_csrf_token', $token);
        }
        return $token;
    }

    /**
     * Vérifie un jeton CSRF
     */
    public static function verifyCsrf(string $token): bool
    {
        return hash_equals(self::get('_csrf_token', ''), $token);
    }

    /**
     * Détruit la session
     */
    public static function destroy(): void
    {
        self::start();
        session_destroy();
        self::$started = false;
    }
}
