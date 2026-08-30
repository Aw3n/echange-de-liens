<!DOCTYPE html>
<?php
// Thème visuel actif (sélectionné par l'admin, validé côté serveur)
$siteTheme = current_theme();
$siteThemeMeta = available_themes()[$siteTheme] ?? null;
?>
<html lang="<?= e($current_language ?? 'fr') ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <script>/* Mode embarqué : affichage compact quand le site est affiché dans une iframe (visionneuse) */
    if (window.self !== window.top) { document.documentElement.classList.add('is-embedded'); }</script>

    <!-- SEO Meta Tags -->
    <title><?= e($meta_title ?? $title ?? $seo['full_title'] ?? 'Echange de Liens') ?></title>
    <meta name="description" content="<?= e($meta_description ?? $seo['meta_description'] ?? '') ?>">
    <?php if (!empty($seo['meta_keywords'])): ?>
    <meta name="keywords" content="<?= e($seo['meta_keywords']) ?>">
    <?php endif; ?>
    <meta name="author" content="<?= e($seo['site_name'] ?? 'Echange de Liens') ?>">
    <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large">
    <base href="<?= e(base_path()) ?>">
    <link rel="canonical" href="<?= e(site_url(ltrim($_SERVER['REQUEST_URI'] ?? '/', '/'))) ?>">
    <?php if (!empty($hreflang_alternates)): ?>
    <?php foreach ($hreflang_alternates as $hlCode => $hlUrl): ?>
    <link rel="alternate" hreflang="<?= e($hlCode) ?>" href="<?= e($hlUrl) ?>">
    <?php endforeach; ?>
    <link rel="alternate" hreflang="x-default" href="<?= e($hreflang_alternates['fr'] ?? reset($hreflang_alternates)) ?>">
    <?php endif; ?>

    <!-- OpenGraph / Facebook -->
    <meta property="og:type" content="<?= $og_type ?? 'website' ?>">
    <meta property="og:title" content="<?= e($meta_title ?? $title ?? $seo['full_title'] ?? '') ?>">
    <meta property="og:description" content="<?= e($meta_description ?? $seo['meta_description'] ?? '') ?>">
    <meta property="og:url" content="<?= e(site_url(ltrim($_SERVER['REQUEST_URI'] ?? '/', '/'))) ?>">
    <meta property="og:site_name" content="<?= e($seo['site_name'] ?? '') ?>">
    <meta property="og:locale" content="<?= e($seo['default_language'] ?? 'fr') ?>_<?= mb_strtoupper($seo['default_language'] ?? 'FR') ?>">
    <?php if (!empty($seo['seo_og_image'])): ?>
    <meta property="og:image" content="<?= e($seo['seo_og_image']) ?>">
    <?php endif; ?>

    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($meta_title ?? $title ?? $seo['full_title'] ?? '') ?>">
    <meta name="twitter:description" content="<?= e($meta_description ?? $seo['meta_description'] ?? '') ?>">
    <?php if (!empty($seo['seo_twitter_handle'])): ?>
    <meta name="twitter:site" content="<?= e($seo['seo_twitter_handle']) ?>">
    <?php endif; ?>
    <?php if (!empty($seo['seo_og_image'])): ?>
    <meta name="twitter:image" content="<?= e($seo['seo_og_image']) ?>">
    <?php endif; ?>

    <!-- Vérification moteurs de recherche -->
    <?php if (!empty($seo['seo_google_verification'])): ?>
    <meta name="google-site-verification" content="<?= e($seo['seo_google_verification']) ?>">
    <?php endif; ?>
    <?php if (!empty($seo['seo_bing_verification'])): ?>
    <meta name="msvalidate.01" content="<?= e($seo['seo_bing_verification']) ?>">
    <?php endif; ?>

    <!-- Google Analytics -->
    <?php if (!empty($seo['seo_ga_tracking_id'])): ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($seo['seo_ga_tracking_id']) ?>"></script>
    <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config','<?= e($seo['seo_ga_tracking_id']) ?>');</script>
    <?php endif; ?>

    <!-- Structured Data / JSON-LD -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebSite",
        "name": "<?= e($seo['site_name'] ?? 'Echange de Liens') ?>",
        "url": "<?= e(site_url()) ?>",
        "description": "<?= e($seo['meta_description'] ?? '') ?>",
        "potentialAction": {
            "@type": "SearchAction",
            "target": "<?= e(site_url('ranking?q={search_term_string}')) ?>",
            "query-input": "required name=search_term_string"
        }
    }
    </script>

    <!-- Favicon & Assets -->
    <meta name="theme-color" content="<?= e($siteThemeMeta['color'] ?? '#6366f1') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= e(base_path('assets/css/style.css')) ?>">
    <?php if ($siteTheme !== 'default'): ?>
    <link rel="stylesheet" href="<?= e(base_path('assets/css/theme-' . $siteTheme . '.css')) ?>">
    <?php endif; ?>
</head>
<body<?= $siteTheme !== 'default' ? ' class="theme-' . e($siteTheme) . '"' : '' ?>>
    <!-- Header -->
    <header class="site-header">
        <div class="container">
            <div class="header-inner">
                <a href="<?= e(base_path()) ?>" class="logo">
                    <i class="fas fa-link"></i>
                    <span><?= e($seo['site_name'] ?? 'Echange de Liens') ?></span>
                </a>

                <nav class="main-nav" id="mainNav">
                    <a href="<?= e(base_path()) ?>" class="nav-link <?= ($page ?? '') === 'home' ? 'active' : '' ?>">
                        <i class="fas fa-home"></i> <?= tr('Accueil') ?>
                    </a>
                    <a href="<?= e(base_path('ranking')) ?>" class="nav-link <?= ($page ?? '') === 'ranking' ? 'active' : '' ?>">
                        <i class="fas fa-trophy"></i> <?= tr('Classement') ?>
                    </a>
                    <a href="<?= e(base_path('bonus')) ?>" class="nav-link <?= ($page ?? '') === 'bonus' ? 'active' : '' ?>">
                        <i class="fas fa-gift"></i> <?= tr('Bonus') ?>
                    </a>
                    <?php if (is_logged_in()): ?>
                    <a href="<?= e(base_path('wheel')) ?>" class="nav-link <?= ($page ?? '') === 'wheel' ? 'active' : '' ?>">
                        <i class="fas fa-dharmachakra"></i> <?= tr('Roue de la fortune') ?>
                    </a>
                    <?php endif; ?>
                    <a href="<?= e(base_path('faq')) ?>" class="nav-link <?= ($page ?? '') === 'faq' ? 'active' : '' ?>">
                        <i class="fas fa-question-circle"></i> FAQ
                    </a>
                </nav>

                <div class="header-actions">
                    <?php
                    // Sélecteur de langue FR/EN (conserve la page courante)
                    $langNow = $current_language ?? 'fr';
                    $reqUriRaw = $_SERVER['REQUEST_URI'] ?? '/';
                    $langPath = strtok($reqUriRaw, '?');
                    $langQuery = [];
                    parse_str(substr((string) strstr($reqUriRaw, '?'), 1), $langQuery);
                    ?>
                    <div class="lang-switch" aria-label="Langue / Language">
                        <?php foreach (['fr' => 'FR', 'en' => 'EN'] as $lc => $ll): ?>
                            <?php $langQuery['lang'] = $lc; ?>
                            <a href="<?= e($langPath . '?' . http_build_query($langQuery)) ?>" class="<?= $langNow === $lc ? 'active' : '' ?>"><?= $ll ?></a>
                        <?php endforeach; ?>
                    </div>
                    <button class="theme-toggle" id="themeToggle" title="<?= tr('Changer le thème') ?>">
                        <i class="fas fa-moon"></i>
                    </button>
                    <?php if (is_logged_in()): ?>
                        <div class="user-menu">
                            <?php if (has_role('admin')): ?>
                                <a href="<?= e(base_path('admin')) ?>" class="btn btn-outline btn-sm btn-admin" title="Panneau d'administration">
                                    <i class="fas fa-shield-alt"></i> Admin
                                </a>
                            <?php endif; ?>
                            <a href="<?= e(base_path('dashboard')) ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-user"></i> <?= e(auth()['username']) ?>
                                <span class="badge"><?= format_number(auth()['points']) ?> pts</span>
                            </a>
                            <a href="<?= e(base_path('account')) ?>" class="btn btn-outline btn-sm" title="<?= tr('Mon compte — adresses de paiement') ?>">
                                <i class="fas fa-wallet"></i>
                            </a>
                            <a href="<?= e(base_path('logout')) ?>" class="btn btn-ghost btn-sm" title="<?= tr('Déconnexion') ?>">
                                <i class="fas fa-sign-out-alt"></i>
                            </a>
                        </div>
                    <?php else: ?>
                        <a href="<?= e(base_path('login')) ?>" class="btn btn-outline btn-sm">
                            <i class="fas fa-sign-in-alt"></i> <?= tr('Connexion') ?>
                        </a>
                        <a href="<?= e(base_path('register')) ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-user-plus"></i> <?= tr('Inscription') ?>
                        </a>
                    <?php endif; ?>
                </div>

                <button class="mobile-menu-btn" id="mobileMenuBtn">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
    </header>

    <!-- Flash Messages -->
    <?php if (!empty($flash_success)): ?>
        <div class="container">
            <div class="alert alert-success alert-dismissible">
                <i class="fas fa-check-circle"></i> <?= nl2br(e($flash_success)) ?>
                <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
        </div>
    <?php endif; ?>
    <?php if (!empty($flash_error)): ?>
        <div class="container">
            <div class="alert alert-danger alert-dismissible">
                <i class="fas fa-exclamation-circle"></i> <?= nl2br(e($flash_error)) ?>
                <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
        </div>
    <?php endif; ?>
    <?php if (!empty($flash_info)): ?>
        <div class="container">
            <div class="alert alert-info alert-dismissible">
                <i class="fas fa-info-circle"></i> <?= nl2br(e($flash_info)) ?>
                <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Content -->
    <?php $topSiteAds = site_ads('top'); if (!empty($topSiteAds)): ?>
    <div class="container ad-banner ad-top">
        <?php foreach ($topSiteAds as $ad): ?>
            <div class="ad-slot">
            <?php if ($ad['type'] === 'banner' && $ad['image_url']): ?>
                <a href="<?= e($ad['target_url']) ?>" target="_blank" rel="noopener">
                    <img src="<?= e($ad['image_url']) ?>" alt="<?= e($ad['title']) ?>" width="<?= $ad['width'] ?>" height="<?= $ad['height'] ?>" loading="lazy">
                </a>
            <?php elseif ($ad['html_code']): ?>
                <?= $ad['html_code'] ?>
            <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="main-layout">
        <!-- Sidebar gauche -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-section">
                <h3 class="sidebar-title"><i class="fas fa-compass"></i> <?= tr('Navigation') ?></h3>
                <nav class="sidebar-nav">
                    <a href="<?= e(base_path()) ?>" class="sidebar-link"><i class="fas fa-home"></i> <?= tr('Accueil') ?></a>
                    <a href="<?= e(base_path('ranking')) ?>" class="sidebar-link"><i class="fas fa-trophy"></i> <?= tr('Classement') ?></a>
                    <a href="<?= e(base_path('bonus')) ?>" class="sidebar-link"><i class="fas fa-gift"></i> <?= tr('Page Bonus') ?></a>
                    <?php if (is_logged_in()): ?>
                        <a href="<?= e(base_path('dashboard')) ?>" class="sidebar-link"><i class="fas fa-tachometer-alt"></i> <?= tr('Tableau de bord') ?></a>
                        <a href="<?= e(base_path('wheel')) ?>" class="sidebar-link"><i class="fas fa-dharmachakra"></i> <?= tr('Roue de la fortune') ?></a>
                        <a href="<?= e(base_path('links/add')) ?>" class="sidebar-link"><i class="fas fa-plus-circle"></i> <?= tr('Ajouter un lien') ?></a>
                        <a href="<?= e(base_path('links/my')) ?>" class="sidebar-link"><i class="fas fa-list"></i> <?= tr('Mes liens') ?></a>
                        <a href="<?= e(base_path('stats')) ?>" class="sidebar-link"><i class="fas fa-chart-bar"></i> <?= tr('Statistiques') ?></a>
                        <a href="<?= e(base_path('vip')) ?>" class="sidebar-link"><i class="fas fa-crown"></i> VIP</a>
                        <a href="<?= e(base_path('account')) ?>" class="sidebar-link"><i class="fas fa-wallet"></i> <?= tr('Mon compte') ?></a>
                    <?php endif; ?>
                    <a href="<?= e(base_path('faq')) ?>" class="sidebar-link"><i class="fas fa-question-circle"></i> FAQ</a>
                </nav>
            </div>

            <?php if (is_logged_in()): ?>
            <div class="sidebar-section">
                <h3 class="sidebar-title"><i class="fas fa-chart-line"></i> TOP Stats</h3>
                <div class="sidebar-stats">
                    <div class="stat-item">
                        <span class="stat-label">Points</span>
                        <span class="stat-value"><?= format_number(auth()['points']) ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (has_role('admin')): ?>
            <div class="sidebar-section">
                <h3 class="sidebar-title"><i class="fas fa-shield-alt"></i> Admin</h3>
                <nav class="sidebar-nav">
                    <a href="<?= e(base_path('admin')) ?>" class="sidebar-link"><i class="fas fa-cog"></i> Dashboard</a>
                    <a href="<?= e(base_path('admin/users')) ?>" class="sidebar-link"><i class="fas fa-users"></i> Utilisateurs</a>
                    <a href="<?= e(base_path('admin/links')) ?>" class="sidebar-link"><i class="fas fa-link"></i> Liens</a>
                    <a href="<?= e(base_path('admin/reports')) ?>" class="sidebar-link"><i class="fas fa-flag"></i> Signalements</a>
                    <a href="<?= e(base_path('admin/banners')) ?>" class="sidebar-link"><i class="fas fa-image"></i> Bannières</a>
                    <a href="<?= e(base_path('admin/purchases')) ?>" class="sidebar-link"><i class="fas fa-shopping-cart"></i> Achats</a>
                    <a href="<?= e(base_path('admin/affiliation')) ?>" class="sidebar-link"><i class="fas fa-hand-holding-usd"></i> Affiliation</a>
                    <a href="<?= e(base_path('admin/settings')) ?>" class="sidebar-link"><i class="fas fa-sliders-h"></i> Configuration</a>
                    <a href="<?= e(base_path('admin/blacklist')) ?>" class="sidebar-link"><i class="fas fa-ban"></i> Blacklist</a>
                </nav>
            </div>
            <?php endif; ?>
        </aside>

        <!-- Contenu principal -->
        <main class="main-content">
            <?= $content ?>
        </main>
    </div>

    <?php $bottomSiteAds = site_ads('bottom'); if (!empty($bottomSiteAds)): ?>
    <div class="container ad-banner ad-bottom">
        <?php foreach ($bottomSiteAds as $ad): ?>
            <div class="ad-slot">
            <?php if ($ad['type'] === 'banner' && $ad['image_url']): ?>
                <a href="<?= e($ad['target_url']) ?>" target="_blank" rel="noopener">
                    <img src="<?= e($ad['image_url']) ?>" alt="<?= e($ad['title']) ?>" width="<?= $ad['width'] ?>" height="<?= $ad['height'] ?>" loading="lazy">
                </a>
            <?php elseif ($ad['html_code']): ?>
                <?= $ad['html_code'] ?>
            <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="site-footer">
        <div class="container">
            <div class="footer-grid">
                <div>
                    <h4><?= e($seo['site_name'] ?? 'Echange de Liens') ?></h4>
                    <p><?= e($seo['site_description'] ?? 'Système moderne d\'échange de liens pour augmenter le trafic de votre site web.') ?></p>
                </div>
                <div>
                    <h4><?= tr('Liens utiles') ?></h4>
                    <a href="<?= e(base_path('faq')) ?>">FAQ</a>
                    <a href="<?= e(base_path('ranking')) ?>"><?= tr('Classement') ?></a>
                    <a href="<?= e(base_path('bonus')) ?>"><?= tr('Bonus') ?></a>
                </div>
                <div>
                    <h4><?= tr('Légal') ?></h4>
                    <a href="<?= e(base_path('page/terms')) ?>"><?= tr('Conditions d\'utilisation') ?></a>
                    <a href="<?= e(base_path('page/privacy')) ?>"><?= tr('Confidentialité') ?></a>
                </div>
            </div>
            <?php $partnersHtml = partners_html(); if ($partnersHtml !== ''): ?>
            <div class="site-partners">
                <h4><?= tr('Partenaires') ?></h4>
                <div class="site-partners-content"><?= $partnersHtml ?></div>
            </div>
            <?php endif; ?>
            <?php // Paiements crypto acceptés : affiché uniquement si l'admin a
            // renseigné au moins une adresse de réception (détection automatique). ?>
            <?php $cryptoPayments = enabled_payment_cryptos(); if ($cryptoPayments !== []): ?>
            <div class="crypto-payments">
                <span class="cp-text">
                    <span><i class="fas fa-coins"></i> Nous acceptons les paiements en cryptomonnaie</span>
                    <span lang="en">We accept cryptocurrency payments</span>
                </span>
                <span class="cp-logos">
                    <?php foreach ($cryptoPayments as $crypto): ?>
                    <span class="cp-coin" title="<?= e($crypto['name']) ?>">
                        <img src="<?= e($crypto['logo']) ?>" alt="<?= e($crypto['name']) ?>" loading="lazy" onerror="this.parentNode.style.display='none'">
                        <span><?= e($crypto['symbol']) ?></span>
                    </span>
                    <?php endforeach; ?>
                </span>
            </div>
            <?php endif; ?>
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> <?= e($seo['site_name'] ?? 'Echange de Liens') ?>. <?= tr('Tous droits réservés.') ?></p>
                <p class="footer-credit"><?= e(tr('CMS open-source développé par')) ?> <a href="https://x.com/Awen__crypto" target="_blank" rel="noopener">Awen Crypto</a></p>
            </div>
        </div>
    </footer>

    <script src="<?= e(base_path('assets/js/app.js')) ?>"></script>
    <?php // Module optionnel Balloon Pop-Up (déclenché par les visites validées) ?>
    <?php \App\Core\View::include('partials.balloon_popup'); ?>
</body>
</html>
