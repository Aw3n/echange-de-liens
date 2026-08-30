<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Moteur de templates
 * Rendu de vues avec layout et données partagées
 */
class View
{
    /** @var array Données partagées entre toutes les vues */
    private static array $shared = [];

    /**
     * Partage des données avec toutes les vues
     */
    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    /**
     * Rendu d'une vue dans un layout
     */
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/main'): string
    {
        $data = array_merge(self::$shared, $data);

        // Rendu du contenu
        $content = self::renderPartial($view, $data);

        // Rendu du layout
        if ($layout) {
            $data['content'] = $content;
            $content = self::renderPartial($layout, $data);
        }

        return $content;
    }

    /**
     * Rendu d'un partial (sans layout)
     */
    public static function renderPartial(string $view, array $data = []): string
    {
        $path = Config::get('app.paths.views') . '/' . str_replace('.', '/', $view) . '.php';

        if (!file_exists($path)) {
            throw new \RuntimeException("View not found: {$view} ({$path})");
        }

        extract($data);
        ob_start();
        include $path;
        return ob_get_clean() ?: '';
    }

    /**
     * Échappe une chaîne HTML
     */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Génère un champ CSRF caché
     */
    public static function csrfField(): string
    {
        return '<input type="hidden" name="_csrf_token" value="' . Session::csrfToken() . '">';
    }

    /**
     * Génère un URL pour un asset
     */
    public static function asset(string $path): string
    {
        return base_path('assets/' . ltrim($path, '/'));
    }

    /**
     * Inclut un partial
     */
    public static function include(string $view, array $data = []): void
    {
        echo self::renderPartial($view, $data);
    }
}
