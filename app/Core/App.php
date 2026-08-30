<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Classe principale de l'application
 * Gère le cycle de vie : routing, middleware, dispatch
 */
class App
{
    private Router $router;
    private Request $request;
    private Response $response;

    public function __construct()
    {
        $this->router = new Router();
        $this->request = new Request();
        $this->response = new Response();

        // Configuration du fuseau horaire
        $timezone = Config::get('app.app.timezone', 'Europe/Paris');
        date_default_timezone_set($timezone);

        // Redirection HTTP → HTTPS si activée via le panneau admin
        // (avant la session : aucun cookie n'est émis en clair)
        $this->enforceHttps();

        // Politique d'encapsulation iframe (avant toute sortie)
        $this->applyFramePolicy();

        // Démarrage de la session
        Session::start();

        // Resynchronise l'utilisateur en session avec la BDD :
        // le compteur de points du header reste à jour sans reconnexion
        $this->refreshSessionUser();

        // Langue de l'interface : paramètre ?lang= (whitelist) persisté en session
        $langParam = $_GET['lang'] ?? null;
        if (is_string($langParam) && in_array($langParam, ['fr', 'en'], true)) {
            Session::set('language', $langParam);
        }

        // Tolérance liens de parrainage mal formés « faq?lang=en?ref=CODE »
        // (deux « ? » au lieu de « & ») : PHP range alors « en?ref=CODE »
        // dans $_GET['lang'] et le ref disparaît. On reconstitue les deux
        // paramètres pour que le suivi du parrainage fonctionne quand même.
        if (is_string($langParam) && ($pos = strpos($langParam, '?ref=')) !== false) {
            $_GET['lang'] = substr($langParam, 0, $pos);
            if (!isset($_GET['ref'])) {
                $_GET['ref'] = substr($langParam, $pos + 5);
            }
            if (in_array($_GET['lang'], ['fr', 'en'], true)) {
                Session::set('language', $_GET['lang']);
            }
        }
        View::share('current_language', Session::get('language', 'fr'));

        // Partage des données globales aux vues
        View::share('app_name', Config::get('app.app.name', 'Echange de Liens'));
        View::share('current_user', Session::user());
        View::share('csrf_token', Session::csrfToken());
        View::share('flash_success', Session::flash('success'));
        View::share('flash_error', Session::flash('error'));
        View::share('flash_info', Session::flash('info'));

        // Chargement des paramètres SEO depuis la DB
        $this->loadSeoSettings();
    }

    /**
     * Applique le réglage admin « force_https » :
     * redirection 301 HTTP → HTTPS et en-tête HSTS en HTTPS.
     */
    private function enforceHttps(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        try {
            $row = Database::getInstance()->queryOne(
                "SELECT setting_value FROM settings WHERE setting_key = 'force_https'"
            );
        } catch (\Throwable) {
            return; // Base non installée : rien à forcer
        }

        if (($row['setting_value'] ?? '0') !== '1') {
            return;
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
            || strcasecmp($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '', 'https') === 0;

        if ($isHttps) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
            return;
        }

        $host = $_SERVER['HTTP_HOST'] ?? '';
        // Uniquement un host valide : évite toute injection d'en-tête
        if (!preg_match('/^[a-zA-Z0-9.\-]+(:\d{1,5})?$/', $host)) {
            return;
        }

        header('Location: https://' . $host . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
        exit;
    }

    /**
     * Contrôle l'encapsulation du site dans une iframe.
     * Un site d'échange de liens a vocation à être visité depuis les
     * visionneuses d'autres sites : les pages publiques doivent être
     * intégrables. `frame-ancestors` (CSP) prime sur tout `X-Frame-Options`
     * qu'un hébergeur pourrait injecter par ailleurs.
     * Les pages sensibles (connexion, admin, API...) restent protégées
     * contre le clickjacking via SAMEORIGIN.
     */
    private function applyFramePolicy(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        $uri = $this->request->getUri();
        $sensitive = preg_match('#/(admin|login|register|dashboard|api)(/|$)#', $uri) === 1;

        if ($sensitive) {
            header('X-Frame-Options: SAMEORIGIN');
            header("Content-Security-Policy: frame-ancestors 'self'");
            return;
        }

        // Pages publiques : intégrables partout (visionneuses, partenaires)
        header('Content-Security-Policy: frame-ancestors *');
    }

    /**
     * Resynchronise l'utilisateur stocké en session avec la base de données
     * (points, pseudo, email, rôle) à chaque requête, afin que le compteur
     * du header reflète les gains/dépits sans nécessiter de reconnexion.
     * Un compte supprimé ou désactivé entraîne la déconnexion.
     */
    private function refreshSessionUser(): void
    {
        $user = Session::user();
        if (!$user) {
            return;
        }

        try {
            $row = Database::getInstance()->queryOne(
                "SELECT u.id, u.username, u.email, u.role_id, u.points, u.is_active, r.slug AS role_slug
                 FROM users u
                 LEFT JOIN roles r ON r.id = u.role_id
                 WHERE u.id = ?",
                [(int) $user['id']]
            );
        } catch (\Throwable) {
            return; // BDD indisponible : on conserve la session telle quelle
        }

        if (!$row || empty($row['is_active'])) {
            Session::logout();
            return;
        }

        Session::set('user', [
            'id' => (int) $row['id'],
            'username' => $row['username'],
            'email' => $row['email'],
            'role_id' => (int) $row['role_id'],
            'role_slug' => $row['role_slug'],
            'points' => (int) $row['points'],
        ]);
    }

    /**
     * Charge les paramètres SEO depuis la DB et les partage avec les vues
     */
    private function loadSeoSettings(): void
    {
        try {
            $db = Database::getInstance();
            $rows = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'seo_%' OR setting_key IN ('site_name', 'site_description')");

            $seo = [
                'site_name' => Config::get('app.app.name', 'Echange de Liens'),
                'site_description' => 'Système moderne d\'échange de liens',
            ];

            foreach ($rows as $row) {
                $seo[$row['setting_key']] = $row['setting_value'] ?? '';
            }

            // Titre SEO complet
            $seo['full_title'] = $seo['seo_site_title'] ?? $seo['site_name'];
            $seo['meta_description'] = $seo['seo_meta_description'] ?? $seo['site_description'];
            $seo['meta_keywords'] = $seo['seo_meta_keywords'] ?? '';

            View::share('seo', $seo);
            View::share('app_name', $seo['site_name']);
        } catch (\Throwable) {
            // Si la DB n'est pas encore installée, valeurs par défaut
            View::share('seo', [
                'site_name' => 'Echange de Liens',
                'full_title' => 'Echange de Liens',
                'meta_description' => 'Système moderne d\'échange de liens',
                'meta_keywords' => '',
            ]);
        }
    }

    /**
     * Récupère le routeur
     */
    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * Récupère la requête
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * Exécute l'application
     */
    public function run(): void
    {
        // Chargement des routes
        $routesFile = Config::get('app.paths.root') . '/routes/web.php';
        if (file_exists($routesFile)) {
            require $routesFile;
        }

        $apiRoutesFile = Config::get('app.paths.root') . '/routes/api.php';
        if (file_exists($apiRoutesFile)) {
            require $apiRoutesFile;
        }

        // Résolution de la route
        $route = $this->router->resolve($this->request);

        if (!$route) {
            $this->handle404();
            return;
        }

        // Exécution des middleware
        foreach ($route['middleware'] as $middlewareClass) {
            if (class_exists($middlewareClass)) {
                $middleware = new $middlewareClass();
                if (!$middleware->handle($this->request)) {
                    return;
                }
            }
        }

        // Dispatch du contrôleur
        $handler = $route['handler'];

        if (is_callable($handler)) {
            $result = call_user_func($handler, $this->request, $this->response);
        } elseif (is_array($handler) && count($handler) === 2) {
            [$controllerClass, $method] = $handler;
            $controller = new $controllerClass();
            $result = $controller->$method($this->request, $this->response);
        } else {
            $this->handle404();
            return;
        }
    }

    /**
     * Gère les erreurs 404
     */
    private function handle404(): void
    {
        if ($this->request->isAjax() || str_starts_with($this->request->getUri(), '/api/')) {
            $this->response->json(['error' => 'Not Found', 'status' => 404], 404);
        }

        http_response_code(404);
        try {
            echo View::render('pages.errors.404', [], 'layouts/main');
        } catch (\Throwable) {
            echo '<h1>404 - Page non trouvée</h1>';
        }
        exit(404);
    }
}
