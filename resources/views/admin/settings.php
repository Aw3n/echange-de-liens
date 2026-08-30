<h1><i class="fas fa-sliders-h"></i> Configuration</h1>

<?php
// Organiser les settings par catégorie
$categories = [
    'general' => ['label' => 'Général', 'icon' => 'fa-cog', 'keys' => ['site_name', 'site_url', 'site_description', 'admin_email', 'default_language', 'maintenance_mode', 'registration_enabled', 'force_https', 'partners_html']],
    'appearance' => ['label' => 'Apparence & Thème', 'icon' => 'fa-palette', 'keys' => ['site_theme']],
    'seo' => ['label' => 'SEO / Référencement', 'icon' => 'fa-search', 'keys' => ['seo_site_title', 'seo_meta_description', 'seo_meta_keywords', 'seo_og_image', 'seo_twitter_handle', 'seo_google_verification', 'seo_bing_verification', 'seo_ga_tracking_id', 'seo_canonical_domain', 'seo_index_links', 'seo_sitemap_enabled']],
    'points' => ['label' => 'Points & Visites', 'icon' => 'fa-star', 'keys' => ['points_per_visit', 'points_per_banner_click', 'visit_duration_seconds', 'bonus_visit_duration_seconds', 'referral_points', 'referral_visit_points']],
    'vip' => ['label' => 'VIP / Paiements', 'icon' => 'fa-crown', 'keys' => ['paypal_email', 'vip_points_1', 'vip_price_1', 'vip_points_2', 'vip_price_2', 'vip_points_3', 'vip_price_3']],
    'xelis' => ['label' => 'XELIS (cryptomonnaie)', 'icon' => 'fa-coins', 'keys' => ['xelis_address', 'xelis_daemon_url', 'xelis_index_url', 'xelis_wallet_url', 'xelis_wallet_user', 'xelis_wallet_password', 'xelis_confirmations', 'xelis_rate_eur', 'xelis_payment_timeout']],
    'kaspa' => ['label' => 'Kaspa (cryptomonnaie)', 'icon' => 'fa-gem', 'keys' => ['kaspa_address', 'kaspa_api_url', 'kaspa_rpc_url', 'kaspa_explorer_url', 'kaspa_rate_eur', 'kaspa_payment_timeout', 'kaspa_min_confirmations']],
    'firo' => ['label' => 'Firo (cryptomonnaie)', 'icon' => 'fa-shield-alt', 'keys' => ['firo_address', 'firo_api_url', 'firo_explorer_url', 'firo_rate_eur', 'firo_payment_timeout', 'firo_min_confirmations']],
    'verge' => ['label' => 'Verge (cryptomonnaie)', 'icon' => 'fa-lock', 'keys' => ['verge_address', 'verge_api_url', 'verge_api_key', 'verge_explorer_url', 'verge_rate_eur', 'verge_payment_timeout', 'verge_min_confirmations']],
    'pepecoin' => ['label' => 'Pepecoin (cryptomonnaie)', 'icon' => 'fa-frog', 'keys' => ['pepecoin_address', 'pepecoin_api_url', 'pepecoin_explorer_url', 'pepecoin_rate_eur', 'pepecoin_payment_timeout', 'pepecoin_min_confirmations']],
    'vertcoin' => ['label' => 'Vertcoin (cryptomonnaie)', 'icon' => 'fa-leaf', 'keys' => ['vertcoin_address', 'vertcoin_api_url', 'vertcoin_explorer_url', 'vertcoin_rate_eur', 'vertcoin_payment_timeout', 'vertcoin_min_confirmations']],
    'dragonx' => ['label' => 'DragonX (cryptomonnaie)', 'icon' => 'fa-dragon', 'keys' => ['dragonx_address', 'dragonx_api_url', 'dragonx_explorer_url', 'dragonx_rate_eur', 'dragonx_payment_timeout', 'dragonx_min_confirmations']],
    'monero' => ['label' => 'Monero (cryptomonnaie)', 'icon' => 'fa-user-secret', 'keys' => ['monero_address', 'monero_explorer_url', 'monero_rate_eur', 'monero_payment_timeout', 'monero_min_confirmations', 'monero_wallet_rpc_url', 'monero_wallet_rpc_user', 'monero_wallet_rpc_password']],
    'affiliate' => ['label' => 'Affiliation (module optionnel)', 'icon' => 'fa-hand-holding-usd', 'keys' => ['affiliate_enabled', 'affiliate_tier1_min', 'affiliate_tier1_pct', 'affiliate_tier2_min', 'affiliate_tier2_pct', 'affiliate_tier3_min', 'affiliate_tier3_pct', 'affiliate_min_payout']],
        'wheel' => ['label' => 'Roue de la fortune', 'icon' => 'fa-dharmachakra', 'keys' => array_merge(['wheel_enabled', 'wheel_interval_hours', 'wheel_url'], array_map(fn($i) => "wheel_seg{$i}", range(1, 9)), array_map(fn($i) => "wheel_weight{$i}", range(1, 9)))],
    'balloon' => ['label' => 'Balloon Pop-Up', 'icon' => 'fa-gift', 'keys' => ['balloon_enabled', 'balloon_every_visits', 'balloon_points_min', 'balloon_points_max']],
    'other' => ['label' => 'Autres', 'icon' => 'fa-ellipsis-h', 'keys' => ['captcha_enabled', 'ads_enabled', 'max_links_per_user']],
];

// Indexer les settings par clé
$settingsMap = [];
foreach ($settings as $s) {
    $settingsMap[$s['setting_key']] = $s;
}

// Sites installés avant l'ajout du paramètre : ligne virtuelle par défaut
// (créée en base à la première sauvegarde grâce à l'upsert du contrôleur)
if (!isset($settingsMap['site_theme'])) {
    $settingsMap['site_theme'] = [
        'setting_key' => 'site_theme',
        'setting_value' => 'default',
        'type' => 'string',
        'description' => 'Thème visuel du site public (déposé dans assets/css/theme-*.css)',
    ];
}

if (!isset($settingsMap['force_https'])) {
    $settingsMap['force_https'] = [
        'setting_key' => 'force_https',
        'setting_value' => '0',
        'type' => 'bool',
        'description' => 'Redirige tout le trafic HTTP vers HTTPS + HSTS (exige un certificat SSL actif chez l\'hébergeur)',
    ];
}

if (!isset($settingsMap['partners_html'])) {
    $settingsMap['partners_html'] = [
        'setting_key' => 'partners_html',
        'setting_value' => '',
        'type' => 'string',
        'description' => 'Code HTML des bannières/liens partenaires (réciprocité d\'échange, ex : Echange Gagnant) affiché dans le footer de toutes les pages publiques, tous thèmes. Vide = bloc masqué.',
    ];
}

if (!isset($settingsMap['xelis_index_url'])) {
    $settingsMap['xelis_index_url'] = [
        'setting_key' => 'xelis_index_url',
        'setting_value' => 'https://index.xelis.io',
        'type' => 'string',
        'description' => 'URL de l\'API Index XELIS (recherche de transactions, ex : https://index.xelis.io)',
    ];
}

if (!isset($settingsMap['kaspa_rpc_url'])) {
    $settingsMap['kaspa_rpc_url'] = [
        'setting_key' => 'kaspa_rpc_url',
        'setting_value' => '',
        'type' => 'string',
        'description' => 'Optionnel (mode pro) : URL d\'un nœud/indexer Kaspa privé testé en secours de l\'API REST (ex : http://127.0.0.1:16110). Vide = API publique seule.',
    ];
}

// Précision BlockDAG pour le seuil de confirmation (s'applique aussi aux sites
// installés dont la description en base est l'ancienne formulation)
if (isset($settingsMap['kaspa_min_confirmations'])) {
    $settingsMap['kaspa_min_confirmations']['description'] = 'Seuil de confirmation du BlockDAG : la TX doit d\'abord être ACCEPTÉE dans la chaîne virtuelle (POST /transactions/acceptance) puis atteindre ce seuil de score DAA (10 blocs/s, 1 = quasi-instantané)';
}

// L'API Insight Firo exige le préfixe /api (sinon l'explorateur renvoie sa page HTML)
if (isset($settingsMap['firo_api_url'])) {
    $settingsMap['firo_api_url']['description'] = 'URL de l\'API Insight Firo AVEC le préfixe /api (ex : https://explorer.firo.org/insight-api-firo/api)';
}

// Verge : clé API côté PHP uniquement + séparation API/explorateur
if (!isset($settingsMap['verge_api_key'])) {
    $settingsMap['verge_api_key'] = [
        'setting_key' => 'verge_api_key',
        'setting_value' => '',
        'type' => 'string',
        'description' => 'Clé API NOWNodes (optionnelle). Envoyée uniquement côté PHP via l\'en-tête « api-key », jamais exposée au navigateur.',
    ];
}
if (isset($settingsMap['verge_api_url'])) {
    $settingsMap['verge_api_url']['description'] = 'URL de l\'API Blockbook (interface machine). Le préfixe /api/v2 est ajouté automatiquement pour les hôtes nownodes.io s\'il manque.';
}
if (isset($settingsMap['verge_explorer_url'])) {
    $settingsMap['verge_explorer_url']['description'] = 'URL d\'un explorateur XVG PUBLIC destiné aux utilisateurs (interface humaine, distincte de l\'API). Ex : https://verge-blockchain.info';
}
if (isset($settingsMap['verge_min_confirmations'])) {
    $settingsMap['verge_min_confirmations']['description'] = 'Confirmations minimales pour Verge (6 recommandé ; blocs ~30s, soit ~3 min — estimation, pas une durée garantie)';
}

// Pepecoin : adresse mainnet validée + seuil de confirmations
if (isset($settingsMap['pepecoin_address'])) {
    $settingsMap['pepecoin_address']['description'] = 'Adresse Pepecoin MAINNET de réception (préfixe « P »). Validée avant enregistrement : une adresse de test ou invalide est refusée.';
}
if (isset($settingsMap['pepecoin_min_confirmations'])) {
    $settingsMap['pepecoin_min_confirmations']['description'] = 'Confirmations minimales pour Pepecoin (6 recommandé ; blocs ~1 min, soit ~6 min — estimation, pas une durée garantie). Paiement détecté ≠ confirmé : la commande n\'est validée qu\'au seuil atteint.';
}

// Vertcoin : adresse mainnet validée + seuil de confirmations
if (isset($settingsMap['vertcoin_address'])) {
    $settingsMap['vertcoin_address']['description'] = 'Adresse Vertcoin MAINNET de réception (préfixe « V »). Validée avant enregistrement : une adresse de test ou invalide est refusée.';
}
if (isset($settingsMap['vertcoin_min_confirmations'])) {
    $settingsMap['vertcoin_min_confirmations']['description'] = 'Confirmations minimales pour Vertcoin (6 recommandé ; blocs ~2,5 min, soit ~15 min — estimation, pas une durée garantie). Paiement détecté ≠ confirmé : la commande n\'est validée qu\'au seuil atteint.';
}

// DragonX : adresse transparente mainnet validée + seuil de confirmations
if (isset($settingsMap['dragonx_address'])) {
    $settingsMap['dragonx_address']['description'] = 'Adresse DragonX TRANSPARENTE MAINNET de réception (préfixe « R »). Validée avant enregistrement : une adresse shielded (« zs... ») ou invalide est refusée.';
}
if (isset($settingsMap['dragonx_api_url'])) {
    $settingsMap['dragonx_api_url']['description'] = 'URL de l\'API REST de l\'explorateur DragonX AVEC le préfixe /api (ex : https://explorer.dragonx.is/api)';
}
if (isset($settingsMap['dragonx_min_confirmations'])) {
    $settingsMap['dragonx_min_confirmations']['description'] = 'Confirmations minimales pour DragonX (10 recommandé ; blocs ~34 s, soit ~6 min — estimation, pas une durée garantie). Paiement détecté ≠ confirmé : la commande n\'est validée qu\'au seuil atteint.';
}

// Monero : secret RPC côté PHP uniquement + expiration + seuil
if (isset($settingsMap['monero_wallet_rpc_password'])) {
    $settingsMap['monero_wallet_rpc_password']['description'] = 'Mot de passe du wallet RPC — utilisé uniquement côté PHP (Basic Auth), champ masqué, jamais exposé au navigateur.';
}
if (isset($settingsMap['monero_payment_timeout'])) {
    $settingsMap['monero_payment_timeout']['description'] = 'Délai d\'expiration du paiement Monero en minutes (défaut 60). Une fois le délai atteint, la commande est annulée automatiquement.';
}
if (isset($settingsMap['monero_min_confirmations'])) {
    $settingsMap['monero_min_confirmations']['description'] = 'Confirmations minimales pour Monero (10 recommandé ; blocs ~2 min, soit ~20 min — estimation, pas une durée garantie). Une TX simplement détectée ne valide jamais la commande.';
}

// Affiliation : module optionnel, barème à paliers et minimum de paiement
if (isset($settingsMap['affiliate_enabled'])) {
    $settingsMap['affiliate_enabled']['description'] = 'Active ou désactive le module d\'affiliation. Désactivé : aucune commission n\'est générée. Attention : à la première activation, les commandes déjà validées de filleuls existants génèrent aussi leurs commissions.';
}
foreach ([1 => '1er', 2 => '2e', 3 => '3e'] as $n => $ord) {
    $minKey = 'affiliate_tier' . $n . '_min';
    $pctKey = 'affiliate_tier' . $n . '_pct';
    if (isset($settingsMap[$minKey])) {
        $settingsMap[$minKey]['description'] = $ord . ' palier : montant minimum de commande (en €) pour appliquer ce pourcentage. La commission retenue est celle du palier le plus élevé atteint.';
    }
    if (isset($settingsMap[$pctKey])) {
        $settingsMap[$pctKey]['description'] = $ord . ' palier : pourcentage de commission versé au parrain (ex : 1, 5, 6). Décimales acceptées avec un point.';
    }
}
if (isset($settingsMap['affiliate_min_payout'])) {
    $settingsMap['affiliate_min_payout']['description'] = 'Montant minimum (en €) du solde disponible d\'un parrain avant que l\'admin puisse effectuer un paiement (page Affiliation).';
}

// Collecter les clés non classées
$allKeys = array_keys($settingsMap);
$categorizedKeys = [];
foreach ($categories as $cat) {
    $categorizedKeys = array_merge($categorizedKeys, $cat['keys']);
}
$uncategorized = array_diff($allKeys, $categorizedKeys);
if (!empty($uncategorized)) {
    $categories['uncategorized'] = ['label' => 'Non classé', 'icon' => 'fa-folder', 'keys' => array_values($uncategorized)];
}
?>

<div class="card">
<div class="card-body">
<form method="POST" action="admin/settings"><?= \App\Core\View::csrfField() ?>

<?php foreach ($categories as $catId => $cat):
    $catSettings = array_filter($cat['keys'], fn($k) => isset($settingsMap[$k]));
    if (empty($catSettings)) continue;
?>
<fieldset style="margin-bottom:2rem;border:1px solid var(--border);border-radius:8px;padding:1rem;">
    <legend style="font-weight:600;font-size:1.1rem;padding:0 .5rem;color:var(--primary);">
        <i class="fas <?= $cat['icon'] ?>"></i> <?= $cat['label'] ?>
    </legend>
    <?php if ($catId === 'appearance'): ?>
        <?php
        $themeOptions = ['default' => ['label' => 'Classique (défaut)', 'color' => '#f8fafc', 'accent' => '#6366f1']] + available_themes();
        $currentThemeValue = (string) ($settingsMap['site_theme']['setting_value'] ?? 'default');
        ?>
        <style>
            .theme-option { display: flex; align-items: center; gap: .6rem; border: 1px solid var(--border); border-radius: 10px; padding: .6rem .9rem; cursor: pointer; background: var(--bg-card); transition: var(--transition); }
            .theme-option:hover { border-color: var(--primary); }
            .theme-option:has(input:checked) { border-color: var(--primary); box-shadow: 0 0 0 2px var(--primary); }
            .theme-swatch { width: 26px; height: 26px; border-radius: 50%; border: 1px solid var(--border); display: inline-block; flex-shrink: 0; }
        </style>
        <div style="display:flex;gap:.75rem;flex-wrap:wrap">
            <?php foreach ($themeOptions as $slug => $meta): ?>
            <label class="theme-option">
                <input type="radio" name="site_theme" value="<?= e($slug) ?>" <?= $currentThemeValue === $slug ? 'checked' : '' ?>>
                <span class="theme-swatch" style="background:linear-gradient(135deg, <?= e($meta['accent'] ?? '#6366f1') ?> 50%, <?= e($meta['color'] ?? '#f8fafc') ?> 50%)"></span>
                <span><?= e($meta['label']) ?></span>
            </label>
            <?php endforeach; ?>
        </div>
        <p class="form-text" style="margin-top:.75rem">
            <i class="fas fa-magic"></i> Le thème s'applique au site public après sauvegarde.
            Pour en ajouter un : déposez un fichier <code>theme-xxx.css</code> dans <code>assets/css/</code>, il apparaîtra automatiquement ici.
        </p>
    <?php else: ?>
    <div class="table-responsive"><table class="table">
    <thead><tr><th style="width:25%">Paramètre</th><th style="width:40%">Valeur</th><th>Description</th></tr></thead>
    <tbody>
    <?php foreach ($catSettings as $key):
        $s = $settingsMap[$key];
    ?>
    <tr>
        <td><code><?= e($s['setting_key']) ?></code></td>
        <td>
            <?php if ($s['type'] === 'bool'): ?>
                <select name="<?= e($s['setting_key']) ?>" class="form-control form-control-sm">
                    <option value="1" <?= $s['setting_value']==='1'?'selected':'' ?>>Oui</option>
                    <option value="0" <?= $s['setting_value']==='0'?'selected':'' ?>>Non</option>
                </select>
            <?php elseif ($s['type'] === 'int'): ?>
                <input type="number" name="<?= e($s['setting_key']) ?>" value="<?= e($s['setting_value'] ?? '') ?>" class="form-control form-control-sm" step="1">
            <?php elseif (strpos($key, 'password') !== false || strpos($key, 'api_key') !== false): ?>
                <input type="password" name="<?= e($s['setting_key']) ?>" value="<?= e($s['setting_value'] ?? '') ?>" class="form-control form-control-sm" autocomplete="new-password">
            <?php elseif (strlen((string)($s['setting_value'] ?? '')) > 100 || $key === 'partners_html'): ?>
                <textarea name="<?= e($s['setting_key']) ?>" class="form-control form-control-sm" rows="3"><?= e($s['setting_value'] ?? '') ?></textarea>
            <?php else: ?>
                <input type="text" name="<?= e($s['setting_key']) ?>" value="<?= e($s['setting_value'] ?? '') ?>" class="form-control form-control-sm">
            <?php endif; ?>
        </td>
        <td class="text-muted"><?= e($s['description'] ?? '') ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
</fieldset>
<?php endforeach; ?>

<div style="position:sticky;bottom:0;background:var(--bg-card);padding:1rem;border-top:1px solid var(--border);text-align:right;">
    <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Sauvegarder tous les paramètres</button>
</div>
</form>
</div>
</div>
