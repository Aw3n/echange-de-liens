<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Database;
use App\Core\View;
use App\Repositories\LinkRepository;
use App\Repositories\VisitRepository;
use App\Services\AuthService;
use App\Services\AffiliateService;

/**
 * Contrôleur de la page d'accueil
 */
class HomeController extends Controller
{
    /**
     * Page d'accueil
     */
    public function index(Request $request, Response $response): never
    {
        $linkRepo = new LinkRepository();
        $visitRepo = new VisitRepository();
        $db = Database::getInstance();

        // Lien de parrainage sur l'accueil (?ref=ID ou ?ref=code) :
        // +1 point par IP unique/24h pour le parrain + mémorisation
        // du parrain (session + cookie 30 jours) pour l'inscription future
        $ref = (string) $request->get('ref', '');
        if ($ref !== '') {
            (new AuthService())->handleReferral($ref, $request->getIp());
        }

        // Classement des liens (10 premiers, filtré par visiteur :
        // un lien déjà voté dans les 24h disparaît de la liste)
        $user = Session::user();
        $viewerId = $user ? (int) $user['id'] : null;
        $viewerIp = $request->getIp();
        $ranking = $linkRepo->getRankingFiltered(10, 0, $viewerId, $viewerIp);
        $rankingTotal = $linkRepo->countRankingFiltered($viewerId, $viewerIp);

        // Statistiques globales
        $stats = [
            'total_links' => $linkRepo->count(),
            'total_visits' => $visitRepo->countTotal(),
            'visits_today' => $visitRepo->countToday(),
            'total_members' => $db->count('users'),
        ];

        // Publicités actives (haut et bas) : une seule par emplacement,
        // aléatoire à chaque rafraîchissement (rotation sans empilement)
        $topAds = $db->query("SELECT * FROM ads WHERE position = 'home_top' AND is_active = 1 AND is_approved = 1 ORDER BY RAND() LIMIT 1");
        $bottomAds = $db->query("SELECT * FROM ads WHERE position = 'home_bottom' AND is_active = 1 AND is_approved = 1 ORDER BY RAND() LIMIT 1");

        // Titre de l'onglet navigateur : le nom de site configuré par
        // l'admin (setting site_name), pas le libellé générique « Accueil ».
        $siteName = (string) \App\Core\Config::get('app.app.name', 'Echange de Liens');
        try {
            $nameRow = $db->queryOne("SELECT setting_value FROM settings WHERE setting_key = 'site_name'");
            if ($nameRow && trim((string) $nameRow['setting_value']) !== '') {
                $siteName = trim((string) $nameRow['setting_value']);
            }
        } catch (\Throwable $e) {
            // Base indisponible : repli sur le nom par défaut de la config
        }

        $this->view('pages.home', [
            'title' => $siteName,
            'ranking' => $ranking,
            'ranking_total' => $rankingTotal,
            'stats' => $stats,
            'topAds' => $topAds,
            'bottomAds' => $bottomAds,
            'page' => 'home',
        ]);
    }

    /**
     * Pagination du classement de l'accueil (bouton « Charger plus ») :
     * renvoie le lot de liens suivant déjà rendu en HTML (partial),
     * avec le même filtre « liens votés dans les 24h masqués ».
     */
    public function rankingMore(Request $request, Response $response): never
    {
        $limit = 10;
        $offset = max(0, (int) $request->get('offset', 0));

        $user = Session::user();
        $viewerId = $user ? (int) $user['id'] : null;
        $viewerIp = $request->getIp();

        $linkRepo = new LinkRepository();
        $rows = $linkRepo->getRankingFiltered($limit, $offset, $viewerId, $viewerIp);
        $loaded = $offset + count($rows);

        $response->json([
            'success' => true,
            'html' => View::renderPartial('partials.ranking_rows', [
                'ranking' => $rows,
                'rank_offset' => $offset,
            ]),
            'next_offset' => $loaded,
            'has_more' => $loaded < $linkRepo->countRankingFiltered($viewerId, $viewerIp),
        ]);
    }

    /**
     * Page FAQ
     */
    public function faq(Request $request, Response $response): never
    {
        $db = Database::getInstance();

        // Lien de parrainage sur la FAQ (faq?lang=xx&ref=CODE) :
        // même fonctionnement que l'accueil — +1 point par IP unique/24h
        // pour le parrain + mémorisation (session + cookie 30 jours)
        // pour une inscription future du filleul (+1000 points bonus)
        $ref = (string) $request->get('ref', '');
        if ($ref !== '') {
            (new AuthService())->handleReferral($ref, $request->getIp());
        }

        $lang = Session::get('language', 'fr');
        $page = $db->queryOne("SELECT * FROM pages WHERE slug = 'faq' AND language_code = ? AND is_active = 1", [$lang]);

        // Repli sur la langue par défaut si la traduction manque
        if (!$page && $lang !== 'fr') {
            $page = $db->queryOne("SELECT * FROM pages WHERE slug = 'faq' AND language_code = 'fr' AND is_active = 1");
        }

        // FAQ affiliation : bloc affiché uniquement si le module est activé
        $affiliateService = new AffiliateService();
        $affiliateEnabled = $affiliateService->isEnabled();

        $this->view('pages.faq', [
            'title' => $page['title'] ?? 'FAQ',
            'meta_title' => $page['meta_title'] ?? ($page['title'] ?? 'FAQ'),
            'meta_description' => $page['meta_description'] ?? 'Questions fréquentes : fonctionnement des points, du parrainage, du VIP et de la sécurité.',
            'page_content' => $page['content'] ?? '',
            'affiliate_enabled' => $affiliateEnabled,
            'affiliate_tiers' => $affiliateEnabled ? $affiliateService->getTiers() : [],
            'affiliate_min_payout' => $affiliateEnabled ? $affiliateService->getMinPayout() : 0.0,
            // URLs canoniques par langue pour le SEO (hreflang)
            'hreflang_alternates' => [
                'fr' => site_url('faq'),
                'en' => site_url('faq?lang=en'),
            ],
            'page' => 'faq',
        ]);
    }

    /**
     * Page CMS générique (conditions, confidentialité...) bilingue
     */
    public function page(Request $request, Response $response): never
    {
        $slug = (string) $request->getParam('slug', '');
        if (!preg_match('/^[a-z0-9\-_]+$/i', $slug)) {
            $this->redirectWithError('/', 'Page introuvable.');
        }

        $db = Database::getInstance();
        $lang = Session::get('language', 'fr');
        $page = $db->queryOne("SELECT * FROM pages WHERE slug = ? AND language_code = ? AND is_active = 1", [$slug, $lang]);

        // Repli sur la langue par défaut si la traduction manque
        if (!$page && $lang !== 'fr') {
            $page = $db->queryOne("SELECT * FROM pages WHERE slug = ? AND language_code = 'fr' AND is_active = 1", [$slug]);
        }

        if (!$page) {
            $this->redirectWithError('/', 'Page introuvable.');
        }

        $this->view('pages.cms_page', [
            'title' => $page['title'],
            'meta_title' => $page['meta_title'] ?? $page['title'],
            'meta_description' => $page['meta_description'] ?? '',
            'page_title' => $page['title'],
            'page_content' => $page['content'],
            // URLs canoniques par langue pour le SEO (hreflang)
            'hreflang_alternates' => [
                'fr' => site_url('page/' . $slug),
                'en' => site_url('page/' . $slug . '?lang=en'),
            ],
            'page' => 'cms-' . $slug,
        ]);
    }

    /**
     * Sitemap XML dynamique pour le SEO
     */
    public function sitemap(Request $request, Response $response): never
    {
        $db = Database::getInstance();
        $baseUrl = rtrim(site_url(), '/');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // Pages statiques principales
        $staticPages = [
            ['loc' => '/', 'priority' => '1.0', 'changefreq' => 'daily'],
            ['loc' => '/ranking', 'priority' => '0.8', 'changefreq' => 'hourly'],
            ['loc' => '/bonus', 'priority' => '0.7', 'changefreq' => 'daily'],
            ['loc' => '/faq', 'priority' => '0.6', 'changefreq' => 'weekly'],
            ['loc' => '/register', 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['loc' => '/login', 'priority' => '0.5', 'changefreq' => 'monthly'],
        ];

        foreach ($staticPages as $p) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>{$baseUrl}{$p['loc']}</loc>\n";
            $xml .= "    <changefreq>{$p['changefreq']}</changefreq>\n";
            $xml .= "    <priority>{$p['priority']}</priority>\n";
            $xml .= "  </url>\n";
        }

        // Pages CMS actives
        $pages = $db->query("SELECT slug, updated_at FROM pages WHERE is_active = 1");
        foreach ($pages as $pg) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>{$baseUrl}/page/{$pg['slug']}</loc>\n";
            $xml .= "    <lastmod>" . date('Y-m-d', strtotime($pg['updated_at'])) . "</lastmod>\n";
            $xml .= "    <changefreq>monthly</changefreq>\n";
            $xml .= "    <priority>0.4</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        $response->setHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->setHeader('Cache-Control', 'public, max-age=3600');
        header('Content-Type: application/xml; charset=UTF-8');
        header('Cache-Control: public, max-age=3600');
        echo $xml;
        exit;
    }

    /**
     * Robots.txt dynamique
     */
    public function robots(Request $request, Response $response): never
    {
        $baseUrl = rtrim(site_url(), '/');

        $txt = "User-agent: *\n";
        $txt .= "Allow: /\n";
        $txt .= "Disallow: /admin/\n";
        $txt .= "Disallow: /dashboard\n";
        $txt .= "Disallow: /links/add\n";
        $txt .= "Disallow: /links/delete/\n";
        $txt .= "Disallow: /visit/\n";
        $txt .= "Disallow: /api/\n";
        $txt .= "Disallow: /go\n";
        $txt .= "Disallow: /logout\n";
        $txt .= "Disallow: /stats\n";
        $txt .= "Disallow: /vip\n";
        $txt .= "\n";
        $txt .= "Sitemap: {$baseUrl}/sitemap.xml\n";

        $response->setHeader('Content-Type', 'text/plain; charset=UTF-8');
        $response->setHeader('Cache-Control', 'public, max-age=86400');
        header('Content-Type: text/plain; charset=UTF-8');
        header('Cache-Control: public, max-age=86400');
        echo $txt;
        exit;
    }
}
