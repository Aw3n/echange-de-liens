<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Gestionnaire de configuration
 * Charge et accède aux fichiers de configuration
 */
class Config
{
    /** @var array<string, mixed> */
    private static array $config = [];

    /**
     * Charge un fichier de configuration
     */
    public static function load(string $file): void
    {
        $path = dirname(__DIR__, 2) . '/config/' . $file . '.php';
        if (file_exists($path)) {
            self::$config[$file] = require $path;
        }
    }

    /**
     * Récupère une valeur de configuration
     * @param string $key Format: "file.section.key" ou "file.key"
     * @param mixed $default Valeur par défaut
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $file = array_shift($parts);

        if (!isset(self::$config[$file])) {
            self::load($file);
        }

        $value = self::$config[$file] ?? null;

        foreach ($parts as $part) {
            if (is_array($value) && array_key_exists($part, $value)) {
                $value = $value[$part];
            } else {
                return $default;
            }
        }

        return $value;
    }

    /**
     * Définit une valeur de configuration
     */
    public static function set(string $key, mixed $value): void
    {
        $parts = explode('.', $key);
        $file = array_shift($parts);

        if (!isset(self::$config[$file])) {
            self::load($file);
        }

        $current = &self::$config[$file];
        foreach ($parts as $i => $part) {
            if ($i === count($parts) - 1) {
                $current[$part] = $value;
            } else {
                if (!isset($current[$part]) || !is_array($current[$part])) {
                    $current[$part] = [];
                }
                $current = &$current[$part];
            }
        }
    }
}
