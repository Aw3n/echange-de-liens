<?php
declare(strict_types=1);

/**
 * Fonctions utilitaires globales
 */

/**
 * Échappe une valeur HTML
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Génère un URL vers un asset
 */
function asset(string $path): string
{
    return base_path('assets/' . ltrim($path, '/'));
}

/**
 * Récupère l'URL du site (auto-détecte si non configuré)
 * Supporte hébergement mutualisé, sous-domaines et sous-répertoires
 */
function site_url(string $path = ''): string
{
    static $base = null;

    if ($base === null) {
        // Essayer depuis la config d'abord
        $dbUrl = \App\Core\Config::get('app.app.url', '');
        if (!empty($dbUrl) && $dbUrl !== 'http://localhost' && $dbUrl !== 'auto') {
            $base = rtrim($dbUrl, '/');
        } else {
            // Auto-détection intelligente
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';

            // Détecter le sous-répertoire si installé dans un sous-dossier
            $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
            // Remonter de /public si on est dans public/index.php
            if (str_ends_with($scriptDir, '/public')) {
                $scriptDir = rtrim(dirname($scriptDir), '/');
            }
            $basePath = ($scriptDir && $scriptDir !== '/' && $scriptDir !== '\\') ? $scriptDir : '';

            $base = $protocol . '://' . $host . $basePath;
        }
    }

    return $base . '/' . ltrim($path, '/');
}

/**
 * Retourne le chemin de base (pour les URLs relatives dans les assets)
 * Supporte l'installation en sous-répertoire
 */
function base_path(string $path = ''): string
{
    static $basePath = null;

    if ($basePath === null) {
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        if (str_ends_with($scriptDir, '/public')) {
            $scriptDir = rtrim(dirname($scriptDir), '/');
        }
        $basePath = ($scriptDir && $scriptDir !== '/' && $scriptDir !== '\\') ? rtrim($scriptDir, '/') : '';
    }

    return $basePath . '/' . ltrim($path, '/');
}

/**
 * Affiche un message flash
 */
function flash(?string $key = null): mixed
{
    if ($key) {
        return \App\Core\Session::flash($key);
    }
    return null;
}

/**
 * Retourne l'utilisateur connecté
 */
function auth(): ?array
{
    return \App\Core\Session::user();
}

/**
 * Vérifie si l'utilisateur est connecté
 */
function is_logged_in(): bool
{
    return \App\Core\Session::isLoggedIn();
}

/**
 * Vérifie si l'utilisateur a un rôle
 */
function has_role(string $role): bool
{
    $user = auth();
    return $user !== null && ($user['role_slug'] ?? '') === $role;
}

/**
 * Génère un lien de parrainage
 * Utilise le code de parrainage si fourni (sinon l'ID, en compatibilité).
 * Le lien pointe sur l'accueil : le parrain est tracké dès l'arrivée
 * et mémorisé 30 jours (cookie) jusqu'à l'inscription du filleul.
 */
function referral_link(int $userId, string $referralCode = ''): string
{
    $ref = $referralCode !== '' ? $referralCode : (string) $userId;
    return site_url('?ref=' . $ref);
}

/**
 * Lien de parrainage vers la page d'inscription (format additionnel du lien
 * accueil, même principe : +1 point par visite IP unique/24h à l'arrivée,
 * parrain mémorisé 30 jours jusqu'à l'inscription du filleul).
 */
function referral_register_link(int $userId, string $referralCode = ''): string
{
    $ref = $referralCode !== '' ? $referralCode : (string) $userId;
    return site_url('register?ref=' . $ref);
}

/**
 * Lien de parrainage vers la FAQ dans une langue donnée (complément
 * des liens accueil/inscription, même principe : +1 point par visite
 * IP unique/24h à l'arrivée, parrain mémorisé 30 jours jusqu'à
 * l'inscription du filleul qui rapporte le bonus).
 * Format : faq?lang=fr&ref=CODE / faq?lang=en&ref=CODE
 */
function referral_faq_link(int $userId, string $referralCode = '', string $lang = 'fr'): string
{
    $ref = $referralCode !== '' ? $referralCode : (string) $userId;
    $lang = $lang === 'en' ? 'en' : 'fr';
    return site_url('faq?lang=' . $lang . '&ref=' . $ref);
}

/**
 * Liste les thèmes disponibles : tout fichier public/assets/css/theme-*.css
 * devient automatiquement sélectionnable dans l'admin (aucun code à modifier).
 * Métadonnées lues dans l'en-tête CSS : « @name Libellé » et « @color #hex ».
 * @return array<string, array{label: string, color: string}>
 */
function available_themes(): array
{
    static $themes = null;
    if ($themes !== null) {
        return $themes;
    }

    $themes = [];
    $dir = dirname(__DIR__, 2) . '/public/assets/css';
    foreach (glob($dir . '/theme-*.css') ?: [] as $file) {
        $slug = substr(basename($file, '.css'), strlen('theme-'));
        if ($slug === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
            continue;
        }
        $head = (string) file_get_contents($file, false, null, 0, 500);
        $themes[$slug] = [
            'label' => preg_match('/@name\s+(.+)/', $head, $m) ? trim($m[1]) : ucfirst($slug),
            'color' => preg_match('/@color\s+(#[0-9a-fA-F]{3,8})/', $head, $m) ? $m[1] : '#6366f1',
            'accent' => preg_match('/@accent\s+(#[0-9a-fA-F]{3,8})/', $head, $m) ? $m[1] : '#6366f1',
        ];
    }

    return $themes;
}

/**
 * Thème actif du site (paramètre admin site_theme, validé contre les fichiers existants)
 */
function current_theme(): string
{
    static $theme = null;
    if ($theme !== null) {
        return $theme;
    }

    $theme = 'default';
    try {
        $row = \App\Core\Database::getInstance()->queryOne(
            "SELECT setting_value FROM settings WHERE setting_key = 'site_theme'"
        );
        $value = trim((string) ($row['setting_value'] ?? ''));
        if ($value !== '' && $value !== 'default' && isset(available_themes()[$value])) {
            $theme = $value;
        }
    } catch (\Throwable $e) {
        // Base non installée ou paramètre absent : thème par défaut
    }

    return $theme;
}

/**
 * Bannières publiques actives et approuvées pour une position donnée
 * (utilisé par le layout pour les positions globales top/bottom).
 */
function site_ads(string $position): array
{
    static $cache = [];
    if (isset($cache[$position])) {
        return $cache[$position];
    }

    try {
        // Une seule bannière par emplacement, tirée aléatoirement à chaque
        // rafraîchissement : les bannières approuvées (ex : ajoutées via la
        // page Bonus) se relaient au même endroit sans jamais s'empiler.
        $cache[$position] = \App\Core\Database::getInstance()->query(
            "SELECT * FROM ads WHERE position = ? AND is_active = 1 AND is_approved = 1 ORDER BY RAND() LIMIT 1",
            [$position]
        );
    } catch (\Throwable $e) {
        $cache[$position] = [];
    }

    return $cache[$position];
}

/**
 * Bannières aléatoires pour le bas de la visionneuse (emplacements gauche
 * et droite autour du compteur). Retourne jusqu'à 2 bannières distinctes,
 * actives et approuvées, en position « viewer_bottom », tirées au sort à
 * chaque affichage. Tableau vide si aucune bannière configurée.
 */
function viewer_ads(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    try {
        $cache = \App\Core\Database::getInstance()->query(
            "SELECT * FROM ads WHERE position = 'viewer_bottom' AND is_active = 1 AND is_approved = 1 ORDER BY RAND() LIMIT 2"
        );
    } catch (\Throwable $e) {
        $cache = [];
    }

    return $cache;
}

/**
 * Code HTML des partenaires (réciprocité d'échange de liens) défini par
 * l'admin via le paramètre « partners_html ». Affiché dans le footer de
 * toutes les pages publiques. Chaîne vide si rien n'est configuré.
 */
function partners_html(): string
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    try {
        $row = \App\Core\Database::getInstance()->queryOne(
            "SELECT setting_value FROM settings WHERE setting_key = 'partners_html'"
        );
        $cache = trim((string) ($row['setting_value'] ?? ''));
    } catch (\Throwable $e) {
        $cache = '';
    }

    return $cache;
}

/**
 * Modules de paiement crypto actifs : un module est considéré actif
 * dès que son adresse de réception est renseignée par l'admin (même
 * règle que les services de paiement, qui refusent de créer une
 * commande sans adresse). Logos officiels CoinGecko.
 * @return array<int, array{name: string, symbol: string, logo: string}>
 */
function enabled_payment_cryptos(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $modules = [
        'xelis_address' => [
            'name' => 'Xelis',
            'symbol' => 'XEL',
            'logo' => 'https://assets.coingecko.com/coins/images/37615/small/green_background_black_logo.png',
        ],
        'kaspa_address' => [
            'name' => 'Kaspa',
            'symbol' => 'KAS',
            'logo' => 'https://assets.coingecko.com/coins/images/25751/small/kaspa-icon-exchanges.png',
        ],
        'firo_address' => [
            'name' => 'Firo',
            'symbol' => 'FIRO',
            'logo' => 'https://assets.coingecko.com/coins/images/479/small/firocoingecko.png',
        ],
        'verge_address' => [
            'name' => 'Verge',
            'symbol' => 'XVG',
            'logo' => 'https://assets.coingecko.com/coins/images/203/small/Verge_Coin_%28native%29_icon_200x200.jpg',
        ],
        'pepecoin_address' => [
            'name' => 'Pepecoin',
            'symbol' => 'PEP',
            'logo' => 'https://assets.coingecko.com/coins/images/36520/small/Pepecoin_onWhite_IconOnly-RGB__Converted_200x200.png',
        ],
        'vertcoin_address' => [
            'name' => 'Vertcoin',
            'symbol' => 'VTC',
            'logo' => 'https://assets.coingecko.com/coins/images/18/small/vertcoin-logo-2018.png',
        ],
        'dragonx_address' => [
            'name' => 'DragonX',
            'symbol' => 'DRGX',
            'logo' => 'https://assets.coingecko.com/coins/images/36254/small/Backup_of_dragon.jpg',
        ],
        'monero_address' => [
            'name' => 'Monero',
            'symbol' => 'XMR',
            'logo' => 'https://assets.coingecko.com/coins/images/69/small/monero_logo.png',
        ],
    ];

    try {
        $rows = \App\Core\Database::getInstance()->query(
            "SELECT setting_key, setting_value FROM settings
             WHERE setting_key IN ('xelis_address', 'kaspa_address', 'firo_address', 'verge_address', 'pepecoin_address', 'vertcoin_address', 'dragonx_address', 'monero_address')"
        );
    } catch (\Throwable $e) {
        $cache = [];
        return $cache;
    }

    $filled = [];
    foreach ($rows as $row) {
        if (trim((string) ($row['setting_value'] ?? '')) !== '') {
            $filled[$row['setting_key']] = true;
        }
    }

    $cache = [];
    foreach ($modules as $key => $info) {
        if (isset($filled[$key])) {
            $cache[] = $info;
        }
    }

    return $cache;
}

/**
 * Traduit un texte de l'interface selon la langue de session (?lang=fr|en).
 * Dictionnaire FR => EN ; en français (ou clé inconnue) le texte est retourné tel quel.
 */
function tr(string $text): string
{
    static $en = [
        // Navigation & layout
        'Accueil' => 'Home',
        'Classement' => 'Ranking',
        'Bonus' => 'Bonus',
        'Navigation' => 'Navigation',
        'Page Bonus' => 'Bonus Page',
        'Tableau de bord' => 'Dashboard',
        'Ajouter un lien' => 'Add a link',
        'Mes liens' => 'My links',
        'Statistiques' => 'Statistics',
        'Connexion' => 'Log in',
        'Inscription' => 'Sign up',
        'Déconnexion' => 'Log out',
        'Changer le thème' => 'Toggle theme',
        'Liens utiles' => 'Useful links',
        'Partenaires' => 'Partners',
        'Cagnotte visiteur détectée :' => 'Visitor savings detected:',
        'Ils seront automatiquement crédités sur votre compte à l\'inscription.' => 'They will be automatically credited to your account upon sign-up.',
        'Copier' => 'Copy',
        'Lien direct vers l\'inscription (même principe : 1 point par visite IP unique + bonus à l\'inscription)' => 'Direct sign-up link (same principle: 1 point per unique IP visit + bonus on sign-up)',
        'Liens de parrainage vers la FAQ (même principe : 1 point par visite IP unique + bonus à l\'inscription)' => 'FAQ referral links (same principle: 1 point per unique IP visit + bonus on sign-up)',
        'Charger plus' => 'Load more',
        'Chargement...' => 'Loading...',
        'Ouvrir le site' => 'Open the site',
        'CMS open-source développé par' => 'Open-source CMS developed by',
        'Légal' => 'Legal',
        'Conditions d\'utilisation' => 'Terms of use',
        'Confidentialité' => 'Privacy',
        'Tous droits réservés.' => 'All rights reserved.',
        // Accueil
        'Echange de Liens' => 'Web Link Exchange',
        'Gagnez des visiteurs en échange de visites sur les liens des autres membres' => 'Earn visitors by visiting other members\' links',
        'Inscription 100 % gratuite — sans carte bancaire' => '100% Free to Join — No Credit Card',
        // Roue de la fortune
        'Roue de la fortune' => 'Wheel of Fortune',
        'Module désactivé par l\'administrateur.' => 'Module disabled by the administrator.',
        'Un tour gratuit toutes les' => 'One free spin every',
        'heures' => 'hours',
        'Bonne chance !' => 'Good luck!',
        'Tourner la roue' => 'Spin the wheel',
        'Prochain tour dans' => 'Next spin in',
        'La roue est prête, tentez votre chance !' => 'The wheel is ready, try your luck!',
        'Vous avez gagné' => 'You won',
        'Une erreur est survenue, réessayez.' => 'An error occurred, please try again.',
        'Le lien partenaire s\'ouvre dans un nouvel onglet pendant la rotation.' => 'The partner link opens in a new tab while the wheel spins.',
        'Patience avant le prochain tour...' => 'Please wait before the next spin...',
        'Roue mal configurée (poids nuls).' => 'Wheel misconfigured (zero weights).',
        'Erreur pendant le tour, réessayez.' => 'Error during the spin, please try again.',
        'Jeton CSRF invalide.' => 'Invalid CSRF token.',
        'Inscription gratuite' => 'Free sign-up',
        'Liens inscrits' => 'Registered links',
        'Visites totales' => 'Total visits',
        'Visites aujourd\'hui' => 'Visits today',
        'Membres' => 'Members',
        'Classement des liens' => 'Link ranking',
        'Voir tout' => 'View all',
        'Aucun lien pour le moment. Soyez le premier à en ajouter !' => 'No links yet. Be the first to add one!',
        // Classement
        'Aucun lien classé pour le moment.' => 'No ranked links yet.',
        'visites' => 'visits',
        'points' => 'points',
        'Visiter' => 'Visit',
        // Authentification
        'Email' => 'Email',
        'Mot de passe' => 'Password',
        'Votre mot de passe' => 'Your password',
        'Se connecter' => 'Log in',
        'Mot de passe oublié ?' => 'Forgot password?',
        'Pas encore de compte ?' => 'No account yet?',
        'Inscrivez-vous' => 'Sign up',
        'Nom d\'utilisateur' => 'Username',
        'Votre pseudo' => 'Your username',
        'Confirmer le mot de passe' => 'Confirm password',
        'Minimum 8 caractères' => 'Minimum 8 characters',
        'Retapez le mot de passe' => 'Retype the password',
        'Anti-robot :' => 'Anti-robot:',
        'Votre réponse' => 'Your answer',
        'S\'inscrire' => 'Sign up',
        'Déjà un compte ?' => 'Already have an account?',
        'Connectez-vous' => 'Log in',
        'Vous avez été parrainé ! Votre parrain gagne 1 point par visite IP unique et 1000 points bonus à votre inscription.' => 'You have been referred! Your referrer earns 1 point per unique IP visit and 1000 bonus points when you sign up.',
        // Bonus
        'Page Bonus - Gagnez 5 points par clic !' => 'Bonus Page - Earn 5 points per click!',
        'Ajouter ma bannière' => 'Add my banner',
        'Cliquez sur une bannière et patientez 15 secondes pour gagner' => 'Click a banner and wait 15 seconds to earn',
        'C\'est l\'équivalent de 5 clics sur un lien classique.' => 'That\'s the equivalent of 5 clicks on a regular link.',
        'Aucune bannière bonus disponible pour le moment.' => 'No bonus banner available at the moment.',
        'clics' => 'clicks',
        // Mon compte & affiliation
        'Mon compte' => 'My account',
        'Mon compte — adresses de paiement' => 'My account — payment addresses',
        'Vos informations de paiement et votre activité d\'affiliation' => 'Your payment details and your affiliate activity',
        'Mes adresses de paiement' => 'My payment addresses',
        'Renseignez optionnellement vos adresses de réception. Elles servent au paiement de vos gains d\'affiliation. Ces adresses sont privées : visibles uniquement par vous et l\'administrateur. Chaque adresse est vérifiée (format strict de sa crypto) avant enregistrement.' => 'Optionally fill in your receiving addresses. They are used to pay out your affiliate earnings. These addresses are private: visible only to you and the administrator. Each address is verified (strict crypto format) before saving.',
        'Adresse email PayPal' => 'PayPal email address',
        'Moyen de paiement' => 'Payment method',
        'Adresse de réception' => 'Receiving address',
        'Optionnel' => 'Optional',
        'Enregistrer mes adresses' => 'Save my addresses',
        'Affiliation — mes gains' => 'Affiliate program — my earnings',
        'Partagez votre lien de parrainage : quand un filleul inscrit via votre lien passe une commande validée (PayPal ou cryptomonnaie), vous gagnez un pourcentage du montant.' => 'Share your referral link: when a member who signed up via your link places a validated order (PayPal or cryptocurrency), you earn a percentage of the amount.',
        'Chaque commande validée (PayPal ou crypto) passée par un filleul recruté via votre lien vous rapporte une commission.' => 'Every validated order (PayPal or crypto) placed by a member you referred earns you a commission.',
        'Filleuls' => 'Referrals',
        'Filleul' => 'Referral',
        'Total gagné' => 'Total earned',
        'Déjà payé' => 'Already paid',
        'Disponible' => 'Available',
        'Barème :' => 'Rate table:',
        'Barème' => 'Rate table',
        'dès' => 'from',
        '€ de commande →' => '€ order →',
        'commande ≥' => 'order ≥',
        'Paiement possible à partir de' => 'Payout available from',
        'Le paiement de vos gains est possible à partir de' => 'Your earnings can be paid out from',
        'de solde disponible' => 'of available balance',
        '(l\'administrateur vous règle via l\'adresse renseignée ci-dessus).' => '(the administrator pays you via the address provided above).',
        'Votre solde disponible atteint le minimum de paiement' => 'Your available balance has reached the minimum payout',
        'l\'administrateur peut procéder à votre règlement.' => 'the administrator can proceed with your payout.',
        'Votre solde atteint le minimum de paiement : renseignez une adresse de paiement sur la page' => 'Your balance has reached the minimum payout: fill in a payment address on the',
        ', l\'admin effectuera le versement.' => ' and the administrator will make the payment.',
        'Mes dernières commissions' => 'My latest commissions',
        'Date' => 'Date',
        'Commande' => 'Order',
        'Commission' => 'Commission',
        'Statut' => 'Status',
        'Payée' => 'Paid',
        'Acquise' => 'Earned',
        // FAQ affiliation (affichée uniquement si le module est actif)
        'Affiliation — Questions fréquentes' => 'Affiliate program — Frequently asked questions',
        'Comment fonctionne l\'affiliation ?' => 'How does the affiliate program work?',
        'Quel pourcentage puis-je gagner ?' => 'What percentage can I earn?',
        'La commission est calculée sur le montant de la commande une fois celle-ci validée.' => 'The commission is calculated on the order amount once the order is validated.',
        'Quand et comment suis-je payé ?' => 'When and how do I get paid?',
        'Dès que votre solde disponible atteint le minimum de paiement, l\'administrateur peut procéder à votre règlement.' => 'As soon as your available balance reaches the minimum payout, the administrator can proceed with your payment.',
        'Où renseigner mon adresse de paiement ?' => 'Where do I fill in my payment address?',
        'Sur la page « Mon compte » : PayPal et adresses crypto (Xelis, Kaspa, Firo, Verge, Pepecoin, Vertcoin, DragonX, Monero).' => 'On the "My account" page: PayPal and crypto addresses (Xelis, Kaspa, Firo, Verge, Pepecoin, Vertcoin, DragonX, Monero).',
        'Chaque adresse est vérifiée et reste privée : visible uniquement par vous et l\'administrateur.' => 'Each address is verified and stays private: visible only to you and the administrator.',
        'Qu\'est-ce qu\'une commande validée ?' => 'What is a validated order?',
        'Un paiement confirmé sur la blockchain (seuil de confirmations atteint) ou confirmé par l\'administrateur pour PayPal.' => 'A payment confirmed on the blockchain (confirmation threshold reached) or confirmed by the administrator for PayPal.',
        'Un paiement simplement détecté ne génère jamais de commission.' => 'A merely detected payment never generates a commission.',
        // Admin - Affiliation
        'Module optionnel : un parrain gagne un pourcentage de chaque commande validée (PayPal ou crypto) passée par ses filleuls.' => 'Optional module: a referrer earns a percentage of every validated order (PayPal or crypto) placed by their referrals.',
        'Module actif' => 'Module enabled',
        'Module inactif' => 'Module disabled',
        'Configurer (barème, activation)' => 'Configure (rates, activation)',
        'Barème actuel :' => 'Current rate table:',
        'Minimum de paiement :' => 'Minimum payout:',
        'Parrains' => 'Referrers',
        'Parrain' => 'Referrer',
        'Aucun parrain avec filleul ou commission pour le moment.' => 'No referrer with a referral or commission yet.',
        'Commandes' => 'Orders',
        'Gagné' => 'Earned',
        'Payé' => 'Paid',
        'Moyens renseignés' => 'Registered methods',
        'Paiement' => 'Payout',
        'Aucun' => 'None',
        'Payer' => 'Pay',
        '€ à' => '€ to',
        'Effectuez le transfert réel AVANT de valider.' => 'Make the actual transfer BEFORE confirming.',
        'Solde <' => 'Balance <',
        'Aucun moyen renseigné' => 'No method registered',
        'Paiements effectués' => 'Completed payouts',
        'Aucun paiement d\'affiliation enregistré.' => 'No affiliate payout recorded.',
        'Utilisateur' => 'User',
        'Montant' => 'Amount',
        'Moyen' => 'Method',
        'Adresse' => 'Address',
        'Note' => 'Note',
    ];

    $lang = \App\Core\Session::get('language', 'fr');
    return ($lang === 'en' && isset($en[$text])) ? $en[$text] : $text;
}

/**
 * Formate un nombre avec séparateur
 */
function format_number(int|float $number): string
{
    return number_format($number, 0, ',', ' ');
}

/**
 * Formate une date
 */
function format_date(string $date, string $format = 'd/m/Y H:i'): string
{
    return date($format, strtotime($date));
}

/**
 * Tronque une chaîne
 */
function truncate(string $text, int $length = 100, string $suffix = '...'): string
{
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length) . $suffix;
}

/**
 * Génère un token aléatoire
 */
function random_token(int $length = 32): string
{
    return bin2hex(random_bytes($length));
}

/**
 * Vérifie qu'une URL pointe vers un hôte public (protection SSRF)
 * Résout le domaine et refuse les plages privées/réservées
 * (localhost, 127.x, 10.x, 192.168.x, 169.254.x, etc.)
 */
function url_points_to_public_host(string $url): bool
{
    $host = parse_url($url, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        return false;
    }

    // Retirer les crochets des adresses IPv6 littérales
    $host = trim($host, '[]');

    $ips = [$host];
    if (!filter_var($host, FILTER_VALIDATE_IP)) {
        $resolved = gethostbynamel($host);
        if ($resolved === false || $resolved === []) {
            return false;
        }
        $ips = $resolved;
    }

    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
    }

    return true;
}

/**
 * Vérifie si une URL est valide et accessible
 */
function check_url_status(string $url, int $timeout = 10): ?int
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_NOBODY => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'EchangeLiens-Bot/1.0',
    ]);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode ?: null;
}

/**
 * Récupère l'IP du client
 * Ne fait confiance à X-Forwarded-For que derrière un proxy explicitement
 * configuré dans app.security.trusted_proxies (cf. Request::getIp())
 */
function client_ip(): string
{
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    $trustedProxies = \App\Core\Config::get('app.security.trusted_proxies', []);
    if (!empty($trustedProxies) && in_array($remoteAddr, $trustedProxies, true)) {
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($forwarded !== '') {
            foreach (array_map('trim', explode(',', $forwarded)) as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
    }

    return $remoteAddr;
}

/**
 * Prix EUR d'une cryptomonnaie via CoinGecko (temps réel).
 *
 * La page /vip affiche 8 cryptos : avec un appel HTTP par crypto, les
 * derniers appels heurtaient la limite de débit de l'API gratuite
 * (HTTP 429) et renvoyaient un prix à 0. On fait donc UN SEUL appel
 * groupé pour tous les ids du site, mis en cache dans la table partagée
 * `cache` (clé coingecko_prices_eur, 5 min).
 *
 * Priorités gérées côté services : taux manuel admin > cache du service
 * > cet appel > prix « stale » du service.
 */
function coingecko_fetch_eur(string $id): float
{
    $allIds = ['xelis', 'kaspa', 'firo', 'zcoin', 'verge', 'pepecoin-network', 'vertcoin', 'dragonx-2', 'monero'];
    $cacheKey = 'coingecko_prices_eur';
    $ttl = 300;

    try {
        $db = \App\Core\Database::getInstance();
        $row = $db->queryOne("SELECT cache_value, cache_expires FROM cache WHERE cache_key = ?", [$cacheKey]);
    } catch (\Throwable $e) {
        $row = null;
    }

    $fresh = false;
    $map = [];
    if ($row) {
        $decoded = json_decode((string) $row['cache_value'], true);
        if (is_array($decoded)) {
            $map = $decoded;
            $fresh = strtotime((string) $row['cache_expires']) > time();
        }
    }

    // Cache partagé encore frais : aucun appel HTTP nécessaire
    if ($fresh && array_key_exists($id, $map)) {
        return (float) $map[$id];
    }

    if (!function_exists('curl_init')) {
        return (float) ($map[$id] ?? 0.0);
    }

    // Un seul appel groupé pour toutes les cryptos du site
    $ch = curl_init('https://api.coingecko.com/api/v3/simple/price?ids=' . implode(',', $allIds) . '&vs_currencies=eur');
    if ($ch === false) {
        return (float) ($map[$id] ?? 0.0);
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'EchangeLien-CoinGecko/1.0',
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response !== false && $httpCode === 200) {
        $data = json_decode((string) $response, true);
        if (is_array($data)) {
            foreach ($allIds as $cid) {
                $map[$cid] = (float) ($data[$cid]['eur'] ?? ($map[$cid] ?? 0.0));
            }
            try {
                $expires = date('Y-m-d H:i:s', time() + $ttl);
                $payload = json_encode($map);
                $existing = $db->queryOne("SELECT cache_key FROM cache WHERE cache_key = ?", [$cacheKey]);
                if ($existing) {
                    $db->execute("UPDATE cache SET cache_value = ?, cache_expires = ? WHERE cache_key = ?", [$payload, $expires, $cacheKey]);
                } else {
                    $db->insert('cache', ['cache_key' => $cacheKey, 'cache_value' => $payload, 'cache_expires' => $expires]);
                }
            } catch (\Throwable $e) {
            }
            return (float) ($map[$id] ?? 0.0);
        }
    }

    // CoinGecko indisponible : dernier prix connu (même expiré) plutôt que 0
    return (float) ($map[$id] ?? 0.0);
}

/**
 * Lit un paramètre de la table settings (valeur brute chaîne).
 * Toute erreur base de données replie sur la valeur par défaut :
 * un module optionnel ne doit jamais casser une page.
 */
function setting_value(string $key, string $default = ''): string
{
    try {
        $row = \App\Core\Database::getInstance()->queryOne(
            "SELECT setting_value FROM settings WHERE setting_key = ?",
            [$key]
        );
        if ($row && trim((string) $row['setting_value']) !== '') {
            return trim((string) $row['setting_value']);
        }
    } catch (\Throwable $e) {
    }
    return $default;
}

/**
 * Module « Balloon Pop-Up » — lecture/écriture du petit compteur
 * stocké dans la table partagée `cache` (TTL 30 jours).
 */
function balloon_cache_get(string $key): ?string
{
    $row = \App\Core\Database::getInstance()->queryOne(
        "SELECT cache_value, cache_expires FROM cache WHERE cache_key = ?",
        [$key]
    );
    if ($row && strtotime((string) $row['cache_expires']) > time()) {
        return (string) $row['cache_value'];
    }
    return null;
}

function balloon_cache_set(string $key, string $value): void
{
    $db = \App\Core\Database::getInstance();
    $expires = date('Y-m-d H:i:s', time() + 30 * 86400);
    $existing = $db->queryOne("SELECT cache_key FROM cache WHERE cache_key = ?", [$key]);
    if ($existing) {
        $db->execute("UPDATE cache SET cache_value = ?, cache_expires = ? WHERE cache_key = ?", [$value, $expires, $key]);
    } else {
        $db->insert('cache', ['cache_key' => $key, 'cache_value' => $value, 'cache_expires' => $expires]);
    }
}

/**
 * Balloon Pop-Up : appelé après chaque visite VALIDÉE d'un utilisateur
 * connecté. Incrémente le compteur ; au seuil (balloon_every_visits,
 * défaut 15) pose le drapeau d'affichage et remet le compteur à zéro.
 */
function balloon_register_validated_visit(int $userId): void
{
    try {
        if (setting_value('balloon_enabled', '1') !== '1') {
            return;
        }
        $every = max(1, (int) setting_value('balloon_every_visits', '15'));
        $countKey = 'balloon_counter_' . $userId;
        $count = (int) (balloon_cache_get($countKey) ?? '0') + 1;
        if ($count >= $every) {
            balloon_cache_set('balloon_pending_' . $userId, '1');
            balloon_cache_set($countKey, '0');
        } else {
            balloon_cache_set($countKey, (string) $count);
        }
    } catch (\Throwable $e) {
        // Le module ne doit jamais faire échouer une validation de visite
    }
}

/**
 * Balloon Pop-Up : vrai si des ballons attendent cet utilisateur,
 * et consomme le drapeau (affichage unique). Utilisé par le layout.
 */
function balloon_take_pending(int $userId): bool
{
    try {
        if (setting_value('balloon_enabled', '1') !== '1') {
            return false;
        }
        $key = 'balloon_pending_' . $userId;
        if (balloon_cache_get($key) === null) {
            return false;
        }
        \App\Core\Database::getInstance()->execute("DELETE FROM cache WHERE cache_key = ?", [$key]);
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}
