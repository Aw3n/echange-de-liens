<?php
declare(strict_types=1);

/**
 * Configuration principale de l'application
 */
return [
    'app' => [
        'name' => 'Echange de Liens',
        'url' => 'auto', // 'auto' = détection automatique | ou définir explicitement : 'https://mon-domaine.com'
        'env' => 'development', // development | production
        'debug' => true,
        'timezone' => 'Europe/Paris',
        'default_language' => 'fr',
        'secret_key' => 'change-this-to-a-random-secret-key-in-production',
    ],

    'paths' => [
        'root' => dirname(__DIR__),
        'app' => dirname(__DIR__) . '/app',
        'public' => dirname(__DIR__) . '/public',
        'views' => dirname(__DIR__) . '/resources/views',
        'storage' => dirname(__DIR__) . '/storage',
        'uploads' => dirname(__DIR__) . '/public/uploads',
    ],

    'security' => [
        'csrf_enabled' => true,
        'captcha_enabled' => true,
        'rate_limit' => [
            'login' => 5,       // tentatives max
            'window' => 900,    // 15 minutes
        ],
        'password_min_length' => 8,
        'session_lifetime' => 7200, // 2 heures
        // Nombre maximum d'inscriptions par IP sur 24h (anti-fraude parrainage)
        'max_registrations_per_ip' => 3,
        // IPs de proxies/CDN de confiance autorisés à définir X-Forwarded-For
        // (ex: ['10.0.0.1'] si derrière un reverse proxy). Vide = REMOTE_ADDR uniquement.
        'trusted_proxies' => [],
    ],

    'points' => [
        'per_visit' => 1,
        'per_banner_click' => 5,
        'referral_bonus' => 1000,
        'referral_visit_points' => 1,       // points par visite IP unique sur lien parrainage (24h)
        'guest_max_per_day' => 50,          // plafond de points en cagnotte visiteur par IP / 24h
        'visit_duration' => 5,          // secondes
        'bonus_visit_duration' => 15,   // secondes
    ],

    'xelis' => [
        // Configuration XELIS - surchargeable via les settings admin (base de données)
        // Les valeurs ci-dessous sont les défauts, priorités basses
        'asset_id' => '0000000000000000000000000000000000000000000000000000000000000000',
        'atomic_units' => 100000000,    // 1 XEL = 100 000 000 atomic units
        'explorer_url' => 'https://explorer.xelis.io',
        'coingecko_id' => 'xelis',
        'default_confirmations' => 5,
        'default_payment_timeout' => 30, // minutes
    ],

    'kaspa' => [
        // Configuration Kaspa - surchargeable via les settings admin (base de données)
        // Kaspa est UTXO-based, 1 KAS = 100 000 000 SOMPI
        'sompi_per_kas' => 100000000,
        'api_url' => 'https://api.kaspa.org',
        'explorer_url' => 'https://explorer.kaspa.org',
        'coingecko_id' => 'kaspa',
        'default_confirmations' => 1,   // ~1 seconde par bloc
        'default_payment_timeout' => 30, // minutes
        'nonce_max' => 99999,           // Nonce max pour UTXO matching
    ],

    'firo' => [
        // Configuration Firo - surchargeable via les settings admin (base de données)
        // Firo est UTXO-based (fork Bitcoin), 1 FIRO = 100 000 000 satoshis
        'satoshi_per_firo' => 100000000,
        'api_url' => 'https://explorer.firo.org/insight-api-firo',
        'explorer_url' => 'https://explorer.firo.org',
        'coingecko_id' => 'firo',
        'default_confirmations' => 2,   // ~10 minutes par bloc
        'default_payment_timeout' => 45, // minutes (plus long car blocs ~10min)
        'nonce_max' => 99999,           // Nonce max pour UTXO matching
    ],

    'verge' => [
        // Configuration Verge (XVG) - surchargeable via les settings admin (base de données)
        // Verge est UTXO-based (fork Bitcoin), 1 XVG = 100 000 000 satoshis
        'satoshi_per_xvg' => 100000000,
        'api_url' => 'https://xvg-blockbook.nownodes.io/api/v2',
        'explorer_url' => 'https://xvg-blockbook.nownodes.io',
        'coingecko_id' => 'verge',
        'default_confirmations' => 6,   // ~30 secondes par bloc, 6 conf = ~3 min
        'default_payment_timeout' => 30, // minutes
        'nonce_max' => 99999,           // Nonce max pour UTXO matching
    ],

    'monero' => [
        // Configuration Monero (XMR) - surchargeable via les settings admin (base de données)
        // Monero est CryptoNote-based, 1 XMR = 10^12 atomic units (picos)
        // Privacy-focused : vérification manuelle par défaut, auto via wallet RPC optionnel
        'atomic_per_xmr' => '1000000000000',
        'explorer_url' => 'https://xmrchain.net',
        'coingecko_id' => 'monero',
        'default_confirmations' => 10,  // ~2 minutes par bloc, 10 conf = ~20 min
        'default_payment_timeout' => 60, // minutes (plus long car blocs ~2min)
        'payment_id_length' => 16,       // Short payment ID (hex)
    ],

    'pepecoin' => [
        // Configuration Pepecoin (PEPE) - surchargeable via les settings admin (base de données)
        // Pepecoin est UTXO-based (fork Dogecoin, Scrypt PoW), 1 PEPE = 100 000 000 satoshis
        // Blocs ~1 minute ; API Blockbook v2 via Pepeblocks
        'satoshi_per_pepe' => 100000000,
        'api_url' => 'https://www.pepeblocks.com/api/v2',
        'explorer_url' => 'https://www.pepeblocks.com',
        'coingecko_id' => 'pepecoin-network',
        'default_confirmations' => 6,   // ~1 minute par bloc, 6 conf = ~6 min
        'default_payment_timeout' => 30, // minutes
        'nonce_max' => 99999,           // Nonce max pour UTXO matching
    ],

    'vertcoin' => [
        // Configuration Vertcoin (VTC) - surchargeable via les settings admin (base de données)
        // Vertcoin est UTXO-based (fork Bitcoin, Lyra2REv3 PoW), 1 VTC = 100 000 000 satoshis
        // Blocs ~2,5 minutes ; API Blockbook v2 officielle
        'satoshi_per_vtc' => 100000000,
        'api_url' => 'https://blockbook.vertcoin.io/api/v2',
        'explorer_url' => 'https://blockbook.vertcoin.io',
        'coingecko_id' => 'vertcoin',
        'default_confirmations' => 6,   // ~2,5 minutes par bloc, 6 conf = ~15 min
        'default_payment_timeout' => 45, // minutes
        'nonce_max' => 99999,           // Nonce max pour UTXO matching
    ],

    'dragonx' => [
        // Configuration DragonX (DRGX) - surchargeable via les settings admin (base de données)
        // DragonX est protocole Zcash (zk-SNARKs, RandomX PoW CPU), 1 DRGX = 100 000 000 satoshis
        // Blocs ~34 secondes ; API REST de l'explorateur officiel (paiements transparents uniquement)
        'satoshi_per_drgx' => 100000000,
        'api_url' => 'https://explorer.dragonx.is/api',
        'explorer_url' => 'https://explorer.dragonx.is',
        'coingecko_id' => 'dragonx-2',
        'default_confirmations' => 10,  // ~34 secondes par bloc, 10 conf = ~6 min
        'default_payment_timeout' => 30, // minutes
        'nonce_max' => 99999,           // Nonce max pour UTXO matching
    ],
];
