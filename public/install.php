<?php
declare(strict_types=1);

// Forcer l'affichage des erreurs (au lieu d'un écran 500 vide)
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

// Capturer les erreurs fatales
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo '<div style="background:#fef2f2;border:2px solid #dc2626;padding:1.5rem;margin:2rem;font-family:monospace;border-radius:8px">';
        echo '<h2 style="color:#dc2626;margin:0 0 .5rem">Erreur fatale PHP</h2>';
        echo '<p style="margin:0"><strong>' . htmlspecialchars($error['message']) . '</strong></p>';
        echo '<p style="color:#6b7280;font-size:.85rem;margin:.5rem 0 0">Fichier : ' . htmlspecialchars($error['file']) . ' ligne ' . $error['line'] . '</p>';
        echo '<p style="color:#6b7280;font-size:.85rem;margin:.25rem 0 0">Vérifiez que votre hébergeur supporte PHP 8.2+</p>';
        echo '</div>';
    }
});

/**
 * Assistant d'installation Echange de Liens
 * Interface web multi-étapes — placé dans public/ pour être accessible
 */

session_start();

// ============================================================
// Protection : refuser l'accès si le site est déjà installé
// Sans ce verrou, n'importe qui pourrait relancer l'installateur
// et écraser le compte administrateur (ON DUPLICATE KEY UPDATE).
// ============================================================
$lockFile = dirname(__DIR__) . '/storage/installed.lock';

function siteAlreadyInstalled(string $lockFile): bool
{
    // 1. Fichier verrou présent
    if (file_exists($lockFile)) {
        return true;
    }
    // 2. Config DB présente + table users non vide
    //    (couvre les installations antérieures à l'ajout du verrou)
    $configPath = dirname(__DIR__) . '/config/database.php';
    if (file_exists($configPath)) {
        try {
            $cfg = require $configPath;
            $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']};charset=utf8mb4";
            $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            if ((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
                return true;
            }
        } catch (Throwable $e) {
            // Connexion impossible → considérer comme non installé
        }
    }
    return false;
}

$installed = siteAlreadyInstalled($lockFile);

if ($installed && !isset($_GET['update'])) {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Installation impossible</title>';
    echo '<style>body{font-family:system-ui,sans-serif;background:#f1f5f9;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:1rem}';
    echo '.card{background:#fff;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.1);max-width:520px;padding:2.5rem;text-align:center}';
    echo 'h1{font-size:1.25rem;color:#dc2626;margin-bottom:1rem}p{color:#475569;font-size:.9rem;line-height:1.6}';
    echo '.btn{display:inline-block;padding:.65rem 1.25rem;border-radius:8px;font-weight:600;font-size:.9rem;text-decoration:none;margin:.35rem}';
    echo '.btn-primary{background:#6366f1;color:#fff}.btn-primary:hover{background:#4f46e5}.btn-ghost{background:#f1f5f9;color:#475569}</style></head><body>';
    echo '<div class="card"><h1>&#128274; Ce site est déjà installé</h1>';
    echo '<p>L\'assistant d\'installation complet est désactivé pour protéger votre site.</p>';
    echo '<p style="margin-top:1.25rem"><a class="btn btn-primary" href="?update">Mettre à jour le site</a> <a class="btn btn-ghost" href="./">Retour au site</a></p>';
    echo '</div></body></html>';
    exit;
}

// ============================================================
// Mode debug : ajouter ?debug à l'URL pour voir les variables serveur
// ============================================================
if (!$installed && isset($_GET['debug'])) {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Debug Installation</title>';
    echo '<style>body{font-family:monospace;background:#1e293b;color:#e2e8f0;padding:2rem}';
    echo 'table{border-collapse:collapse;width:100%;margin:1rem 0}';
    echo 'td,th{border:1px solid #475569;padding:.5rem;text-align:left;font-size:.85rem}';
    echo 'th{background:#334155}.ok{color:#4ade80}.warn{color:#fbbf24}';
    echo '</style></head><body>';
    echo '<h1>&#128270; Diagnostic Installation</h1>';

    echo '<h2>Variables serveur</h2><table>';
    $vars = [
        'DOCUMENT_ROOT', 'SCRIPT_NAME', 'SCRIPT_FILENAME', 'PHP_SELF',
        'REQUEST_URI', 'HTTP_HOST', 'SERVER_NAME', 'SERVER_PORT',
        'HTTPS', 'REQUEST_METHOD', 'QUERY_STRING', 'PATH_INFO',
    ];
    foreach ($vars as $v) {
        $val = $_SERVER[$v] ?? '<em>non défini</em>';
        echo "<tr><th>{$v}</th><td>" . htmlspecialchars((string)$val) . "</td></tr>";
    }
    echo '</table>';

    echo '<h2>Chemins détectés</h2><table>';
    $rd = dirname(__DIR__);
    echo '<tr><th>__DIR__ (public/)</th><td>' . __DIR__ . '</td></tr>';
    echo '<tr><th>dirname(__DIR__) (racine projet)</th><td>' . $rd . '</td></tr>';
    echo '<tr><th>database/schema.sql</th><td>' . (file_exists($rd . '/database/schema.sql') ? '<span class="ok">Existe</span>' : '<span class="warn">INTROUVABLE</span>') . '</td></tr>';
    echo '<tr><th>config/database.php</th><td>' . (file_exists($rd . '/config/database.php') ? '<span class="ok">Existe</span>' : '<span class="warn">INTROUVABLE</span>') . '</td></tr>';
    echo '<tr><th>config/ writable</th><td>' . (is_writable($rd . '/config') ? '<span class="ok">Oui</span>' : '<span class="warn">Non</span>') . '</td></tr>';
    echo '</table>';

    echo '<h2>PHP</h2><table>';
    echo '<tr><th>Version</th><td>' . PHP_VERSION . '</td></tr>';
    echo '<tr><th>pdo_mysql</th><td>' . (extension_loaded('pdo_mysql') ? '<span class="ok">Oui</span>' : '<span class="warn">Non</span>') . '</td></tr>';
    echo '<tr><th>PASSWORD_ARGON2ID</th><td>' . (defined('PASSWORD_ARGON2ID') ? '<span class="ok">Oui</span>' : '<span class="warn">Non (fallback PASSWORD_DEFAULT)</span>') . '</td></tr>';
    echo '<tr><th>mod_rewrite</th><td>' . (function_exists('apache_get_modules') ? (in_array('mod_rewrite', apache_get_modules()) ? '<span class="ok">Oui</span>' : '<span class="warn">Non</span>') : 'Non vérifiable (CGI)') . '</td></tr>';
    echo '</table>';

    echo '<h2>URL détectée</h2>';
    echo '<p><strong>' . htmlspecialchars(detectBaseUrl()) . '</strong></p>';

    echo '<p style="margin-top:2rem"><a href="?step=1" style="color:#818cf8">&larr; Retour à l\'installation</a></p>';
    echo '</body></html>';
    exit;
}

$step = (int) ($_GET['step'] ?? $_POST['step'] ?? 1);
$error = '';

// Dossier racine du projet (parent de public/)
$rootDir = dirname(__DIR__);

// ============================================================
// Fonctions utilitaires
// ============================================================

function detectBaseUrl(): string
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    // Retirer /public si présent (compatible PHP 7+)
    if (substr($scriptDir, -7) === '/public') {
        $scriptDir = rtrim(dirname($scriptDir), '/');
    }
    return $protocol . '://' . $host . ($scriptDir && $scriptDir !== '/' ? $scriptDir : '');
}

function securePassword(string $password): string
{
    if (defined('PASSWORD_ARGON2ID')) {
        return password_hash($password, PASSWORD_ARGON2ID);
    }
    return password_hash($password, PASSWORD_DEFAULT);
}

/** Jeton anti-CSRF de session (protège tous les formulaires de l'assistant) */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function csrf_ok(): bool
{
    $t = $_POST['csrf_token'] ?? '';
    return is_string($t) && $t !== '' && hash_equals(csrf_token(), $t);
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    return (bool) $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE " . $pdo->quote($column))->fetchColumn();
}

function indexExists(PDO $pdo, string $table, string $indexName): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?');
    $stmt->execute([$table, $indexName]);
    return (int) $stmt->fetchColumn() > 0;
}

/**
 * Exécute un fichier SQL en tolérant les erreurs par requête
 * (mode mise à jour : une requête peut échouer si la
 * modification a déjà été appliquée lors d'un passage précédent)
 */
function executeSqlFileTolerant(PDO $pdo, string $sqlFile): array
{
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        throw new RuntimeException("Impossible de lire le fichier : {$sqlFile}");
    }
    $results = [];
    foreach (parseSqlStatements($sql) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '' || $stmt === ';') {
            continue;
        }
        $summary = mb_substr((string) preg_replace('/\s+/', ' ', $stmt), 0, 70);
        try {
            $pdo->exec($stmt);
            $results[] = ['ok', $summary];
        } catch (Throwable $e) {
            $results[] = ['warn', $summary . ' — ignorée (déjà appliquée ?)'];
        }
    }
    return $results;
}

/**
 * Migrations idempotentes appliquées par le mode mise à jour :
 * toutes les évolutions de schéma apparues après les premières
 * installations (parrainage, mot de passe oublié, FAQ bilingue,
 * cagnotte visiteur...).
 */
function runUpdateMigrations(PDO $pdo, string $rootDir): array
{
    $log = [];

    // users.created_ip
    if (!columnExists($pdo, 'users', 'created_ip')) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `created_ip` VARCHAR(45) NULL AFTER `last_login_ip`");
        $pdo->exec("ALTER TABLE `users` ADD INDEX `idx_created_ip` (`created_ip`)");
        $log[] = ['ok', 'Colonne users.created_ip ajoutée'];
    } else {
        $log[] = ['ok', 'Colonne users.created_ip déjà présente'];
    }

    // users.referral_code (parrainage par code hexadécimal)
    if (!columnExists($pdo, 'users', 'referral_code')) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `referral_code` VARCHAR(16) NULL AFTER `referrer_id`");
        $pdo->exec("ALTER TABLE `users` ADD UNIQUE KEY `uniq_referral_code` (`referral_code`)");
        $log[] = ['ok', 'Colonne users.referral_code ajoutée'];
    } else {
        $log[] = ['ok', 'Colonne users.referral_code déjà présente'];
    }

    // Table password_resets (mot de passe oublié) — forme actuelle,
    // identique à schema.sql : jetons rattachés à l'email, hash SHA-256,
    // usage unique. (L'ancienne forme user_id est réparée si rencontrée.)
    $pwResetExists = (bool) $pdo->query("SHOW TABLES LIKE 'password_resets'")->fetchColumn();
    if ($pwResetExists && !columnExists($pdo, 'password_resets', 'email')) {
        // Ancienne forme (user_id) : les jetons sont éphémères (1 h),
        // la recréation de la table est sans conséquence
        $pdo->exec("DROP TABLE `password_resets`");
        $pwResetExists = false;
        $log[] = ['ok', 'Ancienne table password_resets (user_id) remplacée par la forme email'];
    }
    if (!$pwResetExists) {
        $pdo->exec("CREATE TABLE `password_resets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `token_hash` VARCHAR(64) NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL DEFAULT '',
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_token_hash` (`token_hash`),
  INDEX `idx_email` (`email`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $log[] = ['ok', 'Table password_resets créée'];
    } else {
        $log[] = ['ok', 'Table password_resets déjà présente'];
    }

    // pages : un même slug peut exister dans plusieurs langues
    if (indexExists($pdo, 'pages', 'slug')) {
        $pdo->exec("ALTER TABLE `pages` DROP INDEX `slug`");
        $log[] = ['ok', 'Ancien index pages.slug supprimé'];
    }
    if (!indexExists($pdo, 'pages', 'uniq_slug_lang')) {
        $pdo->exec("ALTER TABLE `pages` ADD UNIQUE KEY `uniq_slug_lang` (`slug`, `language_code`)");
        $log[] = ['ok', 'Clé unique pages (slug, langue) ajoutée'];
    } else {
        $log[] = ['ok', 'Clé unique pages (slug, langue) déjà présente'];
    }

    // Ancien slug faq-en → faq (si la cible est libre)
    $stmt = $pdo->query("SELECT COUNT(*) FROM `pages` WHERE `slug` = 'faq' AND `language_code` = 'en'");
    if ((int) $stmt->fetchColumn() === 0) {
        $renamed = $pdo->exec("UPDATE `pages` SET `slug` = 'faq' WHERE `slug` = 'faq-en'");
        if ($renamed > 0) {
            $log[] = ['ok', "Slug 'faq-en' renommé en 'faq'"];
        }
    }

    // Paramètre thème (apparu après les premières installations)
    $pdo->exec("INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `type`, `description`) VALUES ('site_theme', 'default', 'string', 'Thème visuel du site (default ou fichier theme-*.css)')");
    $log[] = ['ok', 'Paramètre site_theme vérifié'];

    // Fichiers de migration officiels (FAQ bilingue...), exécutés en tolérant les doublons
    foreach (glob($rootDir . '/database/migration_*.sql') ?: [] as $migrationFile) {
        try {
            foreach (executeSqlFileTolerant($pdo, $migrationFile) as $result) {
                $log[] = [$result[0], basename($migrationFile) . ' : ' . $result[1]];
            }
        } catch (Throwable $e) {
            $log[] = ['warn', basename($migrationFile) . ' : ' . $e->getMessage()];
        }
    }

    return $log;
}

/**
 * Exécute un fichier SQL en séparant les requêtes une par une
 * (PDO::exec ne gère pas le multi-statement sur la plupart des hébergements)
 */
function executeSqlFile(PDO $pdo, string $sqlFile): int
{
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        throw new RuntimeException("Impossible de lire le fichier : {$sqlFile}");
    }

    $statements = parseSqlStatements($sql);
    $count = 0;

    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '' || $stmt === ';') {
            continue;
        }
        $pdo->exec($stmt);
        $count++;
    }

    return $count;
}

/**
 * Parse un fichier SQL en séparant les requêtes par point-virgule
 * Gère les commentaires et les chaînes entre guillemets
 */
function parseSqlStatements(string $sql): array
{
    // Supprimer les commentaires sur une ligne
    $sql = preg_replace('/--[^\n]*\n/', "\n", $sql);
    // Supprimer les blocs de commentaires multi-lignes
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
    // Supprimer les lignes DELIMITER (utilisées par MySQL CLI)
    $sql = preg_replace('/^DELIMITER.*$/mi', '', $sql);

    // Séparer par point-virgule en fin de ligne
    $parts = preg_split('/;\s*\n/', $sql);

    $statements = [];
    $buffer = '';

    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        $buffer .= ($buffer ? "\n" : '') . $part;

        // Vérifier si les guillemets sont équilibrés (chaîne SQL non terminée)
        $singleQuotes = substr_count($buffer, "'") - substr_count($buffer, "\\'");
        if ($singleQuotes % 2 === 0) {
            $statements[] = $buffer;
            $buffer = '';
        }
    }

    if (trim($buffer) !== '') {
        $statements[] = $buffer;
    }

    return $statements;
}

function renderPage(string $title, string $body, int $currentStep): void
{
    $steps = ['Prérequis', 'Base de données', 'Site & Admin', 'Installation'];
    echo "<!DOCTYPE html><html lang=\"fr\"><head><meta charset=\"UTF-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"><title>{$title}</title>";
    echo '<style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:system-ui,-apple-system,sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1rem}
    .card{background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.2);max-width:640px;width:100%;overflow:hidden}
    .card-header{background:linear-gradient(135deg,#6366f1,#8b5cf6);padding:2rem;color:#fff;text-align:center}
    .card-header h1{font-size:1.5rem;margin-bottom:.25rem}
    .card-header p{opacity:.85;font-size:.9rem}
    .progress{display:flex;background:#e2e8f0}
    .progress .step{flex:1;text-align:center;padding:.75rem .5rem;font-size:.75rem;font-weight:600;color:#94a3b8}
    .progress .step.active{color:#6366f1;background:#fff}
    .progress .step.done{color:#16a34a;background:#f0fdf4}
    .progress .step .num{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:50%;background:#cbd5e1;color:#fff;font-size:.7rem;margin-right:4px}
    .progress .step.active .num{background:#6366f1}
    .progress .step.done .num{background:#16a34a}
    .card-body{padding:2rem}
    .form-group{margin-bottom:1.25rem}
    .form-group label{display:block;font-weight:600;font-size:.875rem;color:#374151;margin-bottom:.375rem}
    .form-group small{color:#6b7280;font-size:.75rem;display:block;margin-top:.25rem}
    input[type="text"],input[type="email"],input[type="password"],input[type="number"],input[type="url"]{width:100%;padding:.625rem .875rem;border:2px solid #e2e8f0;border-radius:8px;font-size:.9rem;transition:border-color .2s;outline:none}
    input:focus{border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.15)}
    .row{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
    @media(max-width:500px){.row{grid-template-columns:1fr}}
    .btn{display:inline-flex;align-items:center;gap:.5rem;padding:.75rem 1.5rem;border-radius:8px;font-size:.9rem;font-weight:600;border:none;cursor:pointer;transition:all .2s;text-decoration:none}
    .btn-primary{background:#6366f1;color:#fff}
    .btn-primary:hover{background:#4f46e5;transform:translateY(-1px);box-shadow:0 4px 12px rgba(99,102,241,.4)}
    .btn-secondary{background:#f1f5f9;color:#475569}
    .btn-secondary:hover{background:#e2e8f0}
    .btn-success{background:#16a34a;color:#fff}
    .btn-success:hover{background:#15803d}
    .btn-lg{padding:1rem 2rem;font-size:1rem}
    .actions{display:flex;justify-content:space-between;align-items:center;margin-top:1.5rem;padding-top:1.5rem;border-top:1px solid #e2e8f0}
    .alert{padding:.875rem 1rem;border-radius:8px;margin-bottom:1rem;font-size:.875rem}
    .alert-error{background:#fef2f2;border:1px solid #fecaca;color:#dc2626}
    .alert-success{background:#f0fdf4;border:1px solid #bbf7d0;color:#16a34a}
    .alert-info{background:#eff6ff;border:1px solid #bfdbfe;color:#2563eb}
    .alert-warning{background:#fffbeb;border:1px solid #fde68a;color:#92400e}
    .check-list{list-style:none;padding:0}
    .check-list li{padding:.5rem 0;font-size:.875rem;display:flex;align-items:center;gap:.5rem}
    .log{background:#1e293b;color:#e2e8f0;padding:1rem;border-radius:8px;font-family:monospace;font-size:.8rem;max-height:300px;overflow-y:auto;margin:1rem 0}
    .log .line{padding:2px 0}.log .ok{color:#4ade80}.log .warn{color:#fbbf24}.log .err{color:#f87171}
    h2{font-size:1.25rem;margin-bottom:1rem;color:#1e293b}
    .credentials{background:#f0fdf4;border:2px solid #bbf7d0;border-radius:8px;padding:1.25rem;margin:1rem 0;text-align:center}
    .credentials code{background:#1e293b;color:#4ade80;padding:.25rem .5rem;border-radius:4px;font-size:.9rem}
    textarea{width:100%;padding:.75rem;border:2px solid #e2e8f0;border-radius:8px;font-family:monospace;font-size:.75rem;resize:vertical;min-height:150px}
    </style></head><body>';

    echo '<div class="card">';
    echo '<div class="card-header"><h1>&#128279; Echange de Liens</h1><p>Assistant d\'installation</p></div>';

    echo '<div class="progress">';
    foreach ($steps as $i => $s) {
        $n = $i + 1;
        $cls = $n < $currentStep ? 'done' : ($n === $currentStep ? 'active' : '');
        $icon = $n < $currentStep ? '&#10003;' : (string)$n;
        echo "<div class=\"step {$cls}\"><span class=\"num\">{$icon}</span>{$s}</div>";
    }
    echo '</div>';

    echo '<div class="card-body">';
    echo $body;
    echo '</div></div></body></html>';
}

// ============================================================
// MODE MISE À JOUR (?update) : site déjà installé
// Applique les migrations après authentification administrateur
// ============================================================
if ($installed) {
    // ---- Exécution des migrations ----
    if (isset($_GET['run']) && !empty($_SESSION['update_admin_id'])) {
        $log = [];
        $updateSuccess = true;
        try {
            $cfg = require $rootDir . '/config/database.php';
            $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']};charset=utf8mb4";
            $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $log[] = ['ok', 'Connexion à la base de données établie'];
            $log = array_merge($log, runUpdateMigrations($pdo, $rootDir));
        } catch (Throwable $e) {
            $log[] = ['err', $e->getMessage()];
            $updateSuccess = false;
        }
        unset($_SESSION['update_admin_id']);
        session_destroy();

        $body = '<h2>' . ($updateSuccess ? '&#9989; Mise à jour terminée' : '&#10060; Erreur pendant la mise à jour') . '</h2>';
        $body .= '<div class="log">';
        foreach ($log as [$type, $msg]) {
            $cls = $type === 'ok' ? 'ok' : ($type === 'warn' ? 'warn' : 'err');
            $icon = $type === 'ok' ? '&#9989;' : ($type === 'warn' ? '&#9888;' : '&#10060;');
            $body .= "<div class=\"line {$cls}\">{$icon} " . htmlspecialchars($msg) . "</div>";
        }
        $body .= '</div>';
        $body .= '<div class="alert alert-info">La mise à jour est idempotente : vous pouvez la relancer à tout moment sans risque.</div>';
        $body .= '<div style="display:flex;gap:1rem;justify-content:center;margin-top:1.5rem;flex-wrap:wrap">';
        $body .= '<a href="./" class="btn btn-success btn-lg">Retour au site &rarr;</a>';
        $body .= '<a href="admin" class="btn btn-secondary btn-lg">Panneau admin</a>';
        $body .= '</div>';
        renderPage('Mise à jour', $body, 4);
        exit;
    }

    // ---- Authentification administrateur (anti brute-force) ----
    $updError = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_ok()) {
            $updError = 'Session expirée, veuillez recharger la page.';
        } elseif (time() < (int) ($_SESSION['upd_blocked_until'] ?? 0)) {
            $updError = 'Trop de tentatives. Réessayez dans quelques minutes.';
        } else {
            $email = trim($_POST['admin_email'] ?? '');
            $password = $_POST['admin_pass'] ?? '';
            $ok = false;
            $admin = null;
            try {
                $cfg = require $rootDir . '/config/database.php';
                $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']};charset=utf8mb4";
                $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $stmt = $pdo->prepare("SELECT id, password FROM users WHERE email = ? AND role_id = 1 AND is_active = 1 LIMIT 1");
                $stmt->execute([$email]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                $ok = $admin && password_verify($password, (string) $admin['password']);
            } catch (Throwable $e) {
                $updError = 'Connexion à la base de données impossible.';
            }
            if ($ok && $admin) {
                session_regenerate_id(true);
                $_SESSION['update_admin_id'] = (int) $admin['id'];
                $_SESSION['upd_attempts'] = 0;
                header('Location: ?update&run');
                exit;
            }
            if (!$updError) {
                $_SESSION['upd_attempts'] = (int) ($_SESSION['upd_attempts'] ?? 0) + 1;
                if ($_SESSION['upd_attempts'] >= 5) {
                    $_SESSION['upd_blocked_until'] = time() + 300;
                    $_SESSION['upd_attempts'] = 0;
                    $updError = 'Trop de tentatives. Réessayez dans 5 minutes.';
                } else {
                    $updError = 'Identifiants administrateur incorrects.';
                }
            }
        }
    }

    $body = '<h2>Mise à jour du site</h2>';
    $body .= '<p style="color:#6b7280;font-size:.875rem;margin-bottom:1.5rem">Cette opération applique les dernières évolutions de la base de données (parrainage, mot de passe oublié, FAQ bilingue, cagnotte visiteur...). Elle est sans danger et peut être relancée à tout moment. Identifiez-vous avec le compte administrateur.</p>';
    if ($updError) {
        $body .= '<div class="alert alert-error">&#10060; ' . htmlspecialchars($updError) . '</div>';
    }
    $body .= '<form method="POST" action="?update">';
    $body .= csrf_field();
    $body .= '<div class="form-group"><label>Email administrateur</label><input type="email" name="admin_email" value="' . htmlspecialchars($_POST['admin_email'] ?? '') . '" required autofocus></div>';
    $body .= '<div class="form-group"><label>Mot de passe</label><input type="password" name="admin_pass" required></div>';
    $body .= '<div class="actions"><a href="./" class="btn btn-secondary">&larr; Retour au site</a><button type="submit" class="btn btn-primary">Vérifier &amp; mettre à jour &rarr;</button></div>';
    $body .= '</form>';
    renderPage('Mise à jour - Authentification', $body, 3);
    exit;
}

// ============================================================
// ÉTAPE 1 : VÉRIFICATION DES PRÉREQUIS
// ============================================================
if ($step === 1) {
    $checks = [];
    $allOk = true;

    $checks[] = ['PHP >= 8.2 (actuel : ' . PHP_VERSION . ')', version_compare(PHP_VERSION, '8.2.0', '>=')];
    $checks[] = ['Extension PDO MySQL', extension_loaded('pdo_mysql')];
    $checks[] = ['Extension mbstring', extension_loaded('mbstring')];
    $checks[] = ['Extension json', extension_loaded('json')];
    $checks[] = ['Extension openssl', extension_loaded('openssl')];
    $checks[] = ['Extension gd (optionnel)', extension_loaded('gd')];
    $checks[] = ['Extension intl (optionnel)', extension_loaded('intl')];
    $checks[] = ['Extension curl (optionnel)', extension_loaded('curl')];
    $checks[] = ['Fonction password_hash', function_exists('password_hash')];
    $checks[] = ['Fonction mail() (optionnel)', function_exists('mail')];

    // Permissions d'écriture
    $writableDirs = [
        'storage/logs' => $rootDir . '/storage/logs',
        'storage/cache' => $rootDir . '/storage/cache',
        'storage/sessions' => $rootDir . '/storage/sessions',
        'storage/uploads' => $rootDir . '/storage/uploads',
        'storage/uploads/banners' => $rootDir . '/storage/uploads/banners',
        'config/' => $rootDir . '/config',
    ];

    foreach ($writableDirs as $label => $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $writable = is_dir($dir) && is_writable($dir);
        $checks[] = ['Écriture : ' . $label, $writable];
        if (!$writable) $allOk = false;
    }

    foreach ($checks as [$label, $ok]) {
        if (!$ok && strpos($label, 'optionnel') === false) $allOk = false;
    }

    // Vérifier que le schéma SQL existe
    $schemaExists = file_exists($rootDir . '/database/schema.sql');
    $checks[] = ['Fichier database/schema.sql', $schemaExists];
    if (!$schemaExists) $allOk = false;

    $body = '<h2>Vérification de l\'environnement</h2>';
    $body .= '<p style="font-size:.8rem;color:#6b7280;margin-bottom:1rem">En cas de problème, consultez la <a href="?debug" style="color:#6366f1">page de diagnostic</a>.</p>';
    $body .= '<ul class="check-list">';
    foreach ($checks as [$label, $ok]) {
        $icon = $ok ? '&#9989;' : '&#10060;';
        $cls = $ok ? 'ok' : 'fail';
        $body .= '<li><span class="' . $cls . '">' . $icon . '</span> ' . htmlspecialchars($label) . '</li>';
    }
    $body .= '</ul>';

    if (!$allOk) {
        $body .= '<div class="alert alert-error">Certains prérequis obligatoires ne sont pas satisfaits.</div>';
        $body .= '<div class="actions"><span></span><button class="btn btn-primary" onclick="location.reload()">Revérifier</button></div>';
    } else {
        $body .= '<div class="alert alert-success">Tous les prérequis sont satisfaits !</div>';
        $body .= '<div class="actions"><span></span><a href="?step=2" class="btn btn-primary">Continuer &rarr;</a></div>';
    }

    renderPage('Étape 1 - Prérequis', $body, 1);
    exit;
}

// ============================================================
// ÉTAPE 2 : CONFIGURATION BASE DE DONNÉES
// ============================================================
if ($step === 2) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $host = trim($_POST['db_host'] ?? '127.0.0.1');
        $port = (int) ($_POST['db_port'] ?? 3306);
        $dbname = trim($_POST['db_name'] ?? 'echange_lien');
        $user = trim($_POST['db_user'] ?? 'root');
        $pass = $_POST['db_pass'] ?? '';

        if (!csrf_ok()) {
            $error = 'Session expirée, veuillez recharger la page.';
        } elseif (!preg_match('/^[A-Za-z0-9_]+$/', $dbname)) {
            $error = 'Le nom de la base ne peut contenir que des lettres, chiffres et underscores.';
        } else {
            try {
                $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
                $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

                // Test de création/accès à la base : sur beaucoup
                // d'hébergements mutualisés le compte MySQL n'a pas le
                // privilège CREATE → la base doit être pré-créée chez
                // l'hébergeur, on tente alors simplement d'y accéder.
                try {
                    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                } catch (PDOException $e) {
                    // Privilège CREATE absent : l'accès (USE) ci-dessous
                    // confirmera que la base pré-créée existe bien.
                }
                $pdo->exec("USE `{$dbname}`");

                // Test simple
                $pdo->query("SELECT 1");

                session_regenerate_id(true);
                $_SESSION['db'] = compact('host', 'port', 'dbname', 'user', 'pass');
                header('Location: ?step=3');
                exit;
            } catch (PDOException $e) {
                $error = $e->getMessage();
            }
        }
    }

    $body = '<h2>Configuration de la base de données</h2>';
    $body .= '<p style="color:#6b7280;font-size:.875rem;margin-bottom:1.5rem">Entrez les identifiants MySQL de votre hébergeur. La base sera créée automatiquement.</p>';

    if ($error) {
        $body .= '<div class="alert alert-error">&#10060; Connexion échouée : ' . htmlspecialchars($error) . '</div>';
    }

    $body .= '<form method="POST" action="?step=2">' . csrf_field();
    $body .= '<div class="row">';
    $body .= '<div class="form-group"><label>Hôte</label><input type="text" name="db_host" value="' . htmlspecialchars($_POST['db_host'] ?? '127.0.0.1') . '"><small>Adresse du serveur MySQL (souvent 127.0.0.1 ou localhost)</small></div>';
    $body .= '<div class="form-group"><label>Port</label><input type="number" name="db_port" value="' . htmlspecialchars((string)($_POST['db_port'] ?? '3306')) . '"><small>3306 par défaut</small></div>';
    $body .= '</div>';
    $body .= '<div class="form-group"><label>Nom de la base de données</label><input type="text" name="db_name" value="' . htmlspecialchars($_POST['db_name'] ?? 'echange_lien') . '"><small>Créée automatiquement si elle n\'existe pas</small></div>';
    $body .= '<div class="form-group"><label>Nom d\'utilisateur MySQL</label><input type="text" name="db_user" value="' . htmlspecialchars($_POST['db_user'] ?? 'root') . '"></div>';
    $body .= '<div class="form-group"><label>Mot de passe MySQL</label><input type="password" name="db_pass" value=""><small>Laissez vide si aucun mot de passe</small></div>';
    $body .= '<div class="actions"><a href="?step=1" class="btn btn-secondary">&larr; Retour</a><button type="submit" class="btn btn-primary">Tester &amp; Continuer &rarr;</button></div>';
    $body .= '</form>';

    renderPage('Étape 2 - Base de données', $body, 2);
    exit;
}

// ============================================================
// ÉTAPE 3 : CONFIGURATION DU SITE & ADMINISTRATEUR
// ============================================================
if ($step === 3) {
    if (!isset($_SESSION['db'])) {
        header('Location: ?step=2');
        exit;
    }

    $detectedUrl = detectBaseUrl();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_ok()) {
        $error = 'Session expirée, veuillez recharger la page.';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $siteName = trim($_POST['site_name'] ?? 'Echange de Liens');
        $siteUrl = rtrim(trim($_POST['site_url'] ?? $detectedUrl), '/');
        $adminUser = trim($_POST['admin_user'] ?? 'admin');
        $adminEmail = trim($_POST['admin_email'] ?? '');
        $adminPass = $_POST['admin_pass'] ?? '';
        $adminPassConfirm = $_POST['admin_pass_confirm'] ?? '';

        if (empty($adminEmail) || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Adresse email invalide.';
        } elseif (strlen($adminPass) < 8) {
            $error = 'Le mot de passe doit contenir au moins 8 caractères.';
        } elseif ($adminPass !== $adminPassConfirm) {
            $error = 'Les mots de passe ne correspondent pas.';
        } elseif (empty($adminUser) || strlen($adminUser) < 3) {
            $error = 'Le nom d\'utilisateur doit contenir au moins 3 caractères.';
        } else {
            session_regenerate_id(true);
            $_SESSION['site'] = compact('siteName', 'siteUrl', 'adminUser', 'adminEmail', 'adminPass');
            header('Location: ?step=4');
            exit;
        }
    }

    $body = '<h2>Configuration du site</h2>';
    $body .= '<p style="color:#6b7280;font-size:.875rem;margin-bottom:1.5rem">Personnalisez votre site et crée le compte administrateur.</p>';

    if ($error) {
        $body .= '<div class="alert alert-error">&#10060; ' . htmlspecialchars($error) . '</div>';
    }

    $body .= '<form method="POST" action="?step=3">' . csrf_field();
    $body .= '<div class="form-group"><label>Nom du site</label><input type="text" name="site_name" value="' . htmlspecialchars($_POST['site_name'] ?? 'Echange de Liens') . '"><small>Affiché dans le header et les balises title</small></div>';
    $body .= '<div class="form-group"><label>URL du site</label><input type="url" name="site_url" value="' . htmlspecialchars($_POST['site_url'] ?? $detectedUrl) . '"><small>Auto-détecté. Modifiez si nécessaire (sans / final)</small></div>';

    $body .= '<hr style="margin:1.5rem 0;border:none;border-top:1px solid #e2e8f0">';
    $body .= '<h2>Compte Administrateur</h2>';

    $body .= '<div class="form-group"><label>Nom d\'utilisateur</label><input type="text" name="admin_user" value="' . htmlspecialchars($_POST['admin_user'] ?? 'admin') . '"><small>Minimum 3 caractères</small></div>';
    $body .= '<div class="form-group"><label>Email (pour la connexion)</label><input type="email" name="admin_email" value="' . htmlspecialchars($_POST['admin_email'] ?? '') . '" required><small>Cet email sera utilisé pour vous connecter</small></div>';
    $body .= '<div class="row">';
    $body .= '<div class="form-group"><label>Mot de passe</label><input type="password" name="admin_pass" required><small>Minimum 8 caractères</small></div>';
    $body .= '<div class="form-group"><label>Confirmer</label><input type="password" name="admin_pass_confirm" required></div>';
    $body .= '</div>';

    $body .= '<div class="actions"><a href="?step=2" class="btn btn-secondary">&larr; Retour</a><button type="submit" class="btn btn-primary">Installer &rarr;</button></div>';
    $body .= '</form>';

    renderPage('Étape 3 - Site & Admin', $body, 3);
    exit;
}

// ============================================================
// ÉTAPE 4 : EXÉCUTION DE L'INSTALLATION
// ============================================================
if ($step === 4) {
    if (!isset($_SESSION['db']) || !isset($_SESSION['site'])) {
        header('Location: ?step=1');
        exit;
    }

    $db = $_SESSION['db'];
    $site = $_SESSION['site'];
    $log = [];
    $success = true;
    $configWritten = false;
    $configContent = '';

    try {
        // 1. Connexion MySQL
        $dsn = "mysql:host={$db['host']};port={$db['port']};charset=utf8mb4";
        $pdo = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $log[] = ['ok', 'Connexion au serveur MySQL établie'];

        try {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db['dbname']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            // Privilège CREATE absent (mutualisé) : base pré-créée attendue
        }
        $pdo->exec("USE `{$db['dbname']}`");
        $log[] = ['ok', "Base de données `{$db['dbname']}` prête"];

        // 2. Import du schéma (requête par requête, tolérant : une relance
        //    après un échec partiel ne bloque pas sur les doublons)
        $sqlFile = $rootDir . '/database/schema.sql';
        $results = executeSqlFileTolerant($pdo, $sqlFile);
        $ignored = 0;
        foreach ($results as [$type, $summary]) {
            if ($type !== 'ok') {
                $ignored++;
                $log[] = ['warn', 'Requête ignorée : ' . $summary];
            }
        }
        $log[] = ['ok', count($results) . ' requêtes SQL traitées (tables, données, index)' . ($ignored > 0 ? " dont {$ignored} ignorée(s)" : '')];

        // 2b. Vérifications post-import : détecte un schéma partiel
        //     (fichier tronqué, erreur MySQL...) avant d'aller plus loin
        $missing = [];
        foreach (['roles', 'users', 'languages', 'links', 'link_visits', 'points_history', 'settings', 'pages', 'ads', 'purchases', 'guest_wallets', 'wheel_spins', 'affiliate_commissions', 'affiliate_payouts', 'referral_visits', 'password_resets', 'cache'] as $t) {
            if (!$pdo->query("SHOW TABLES LIKE " . $pdo->quote($t))->fetchColumn()) {
                $missing[] = $t;
            }
        }
        if ($missing === []) {
            $settingsCount = (int) $pdo->query("SELECT COUNT(*) FROM settings")->fetchColumn();
            if ($settingsCount < 50) {
                $missing[] = "settings ({$settingsCount} paramètres, import incomplet ?)";
            }
        }
        if ($missing !== []) {
            throw new RuntimeException('Schéma incomplet, éléments manquants : ' . implode(', ', $missing));
        }
        $log[] = ['ok', 'Vérifications post-import réussies (tables et données)'];

        // 3. Configuration du site
        $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->execute([$site['siteName'], 'site_name']);
        $stmt->execute([$site['siteUrl'], 'site_url']);
        $stmt->execute([$site['siteName'] . ' - Gagnez des visiteurs pour votre site', 'seo_site_title']);
        $canonicalDomain = parse_url($site['siteUrl'], PHP_URL_HOST) ?: 'localhost';
        $stmt->execute([$canonicalDomain, 'seo_canonical_domain']);
        $log[] = ['ok', 'Paramètres du site enregistrés'];

        // 4. Création de l'admin (jamais d'écrasement d'un compte existant)
        $existingUser = $pdo->query("SELECT COUNT(*) FROM users WHERE role_id = 1")->fetchColumn();
        $adminPassHash = securePassword($site['adminPass']);
        if ((int) $existingUser === 0) {
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role_id, is_active, email_verified, created_at) VALUES (?, ?, ?, 1, 1, 1, NOW())");
            $stmt->execute([$site['adminUser'], $site['adminEmail'], $adminPassHash]);
            $log[] = ['ok', "Administrateur `{$site['adminUser']}` créé"];
        } else {
            $log[] = ['ok', 'Compte administrateur existant conservé'];
        }

        // 5. Création des dossiers de stockage
        $dirs = ['storage/logs', 'storage/cache', 'storage/sessions', 'storage/uploads', 'storage/uploads/banners'];
        foreach ($dirs as $d) {
            $fullPath = $rootDir . '/' . $d;
            if (!is_dir($fullPath)) {
                @mkdir($fullPath, 0755, true);
            }
        }
        $log[] = ['ok', 'Dossiers de stockage créés'];

        // 5b. Verrouillage de l'installateur (empêche toute réexécution)
        if (@file_put_contents($lockFile, "Installation effectuée le " . date('Y-m-d H:i:s') . "\n") !== false) {
            $log[] = ['ok', 'Installateur verrouillé (storage/installed.lock)'];
        } else {
            $log[] = ['err', 'Impossible de créer le verrou : SUPPRIMEZ install.php manuellement après installation'];
        }

        // 6. Écriture de config/database.php
        $dbPass = addslashes($db['pass']);
        $configContent = '<?php' . "\n";
        $configContent .= 'declare(strict_types=1);' . "\n\n";
        $configContent .= '/**' . "\n";
        $configContent .= ' * Configuration de la base de données' . "\n";
        $configContent .= ' * Généré automatiquement par l\'assistant d\'installation' . "\n";
        $configContent .= ' */' . "\n";
        $configContent .= 'return [' . "\n";
        $configContent .= "    'driver' => 'mysql',\n";
        $configContent .= "    'host' => '{$db['host']}',\n";
        $configContent .= "    'port' => {$db['port']},\n";
        $configContent .= "    'database' => '{$db['dbname']}',\n";
        $configContent .= "    'username' => '{$db['user']}',\n";
        $configContent .= "    'password' => '{$dbPass}',\n";
        $configContent .= "    'charset' => 'utf8mb4',\n";
        $configContent .= "    'collation' => 'utf8mb4_unicode_ci',\n";
        $configContent .= "    'prefix' => '',\n";
        $configContent .= "    'options' => [\n";
        $configContent .= "        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,\n";
        $configContent .= "        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n";
        $configContent .= "        PDO::ATTR_EMULATE_PREPARES => false,\n";
        $configContent .= "        PDO::MYSQL_ATTR_INIT_COMMAND => \"SET NAMES utf8mb4\",\n";
        $configContent .= "    ],\n";
        $configContent .= "];\n";

        $configPath = $rootDir . '/config/database.php';
        if (is_writable($rootDir . '/config') || (file_exists($configPath) && is_writable($configPath))) {
            file_put_contents($configPath, $configContent);
            $configWritten = true;
            $log[] = ['ok', 'Fichier config/database.php mis à jour automatiquement'];
        } else {
            $log[] = ['err', 'Impossible d\'écrire config/database.php automatiquement'];
            $log[] = ['err', 'Vous devrez le copier manuellement (voir ci-dessous)'];
        }

        // 7. Nettoyage session
        session_destroy();

    } catch (Throwable $e) {
        $log[] = ['err', $e->getMessage()];
        $success = false;
    }

    // ---- Rendu ----
    $body = '<h2>' . ($success ? '&#127881; Installation terminée !' : '&#10060; Erreur lors de l\'installation') . '</h2>';

    $body .= '<div class="log">';
    foreach ($log as [$type, $msg]) {
        $cls = $type === 'ok' ? 'ok' : ($type === 'warn' ? 'warn' : 'err');
        $icon = $type === 'ok' ? '&#9989;' : ($type === 'warn' ? '&#9888;' : '&#10060;');
        $body .= "<div class=\"line {$cls}\">{$icon} " . htmlspecialchars($msg) . "</div>";
    }
    $body .= '</div>';

    if ($success) {
        $body .= '<div class="credentials">';
        $body .= '<h3 style="margin-bottom:.75rem;color:#16a34a">Vos identifiants</h3>';
        $body .= '<p style="margin:.25rem 0">Utilisateur : <code>' . htmlspecialchars($site['adminUser']) . '</code></p>';
        $body .= '<p style="margin:.25rem 0">Email : <code>' . htmlspecialchars($site['adminEmail']) . '</code></p>';
        $body .= '<p style="font-size:.8rem;color:#6b7280;margin-top:.5rem">Connectez-vous avec votre <strong>email</strong> et votre mot de passe</p>';
        $body .= '</div>';

        // Si le fichier config n'a pas pu être écrit, afficher le contenu à copier
        if (!$configWritten) {
            $body .= '<div class="alert alert-warning">';
            $body .= '<strong>Important :</strong> Créez ou remplacez le fichier <code>config/database.php</code> avec ce contenu :';
            $body .= '</div>';
            $body .= '<textarea readonly onclick="this.select()">' . htmlspecialchars($configContent) . '</textarea>';
            $body .= '<p style="font-size:.75rem;color:#6b7280;margin-top:.5rem">Copiez ce contenu et créez le fichier <code>config/database.php</code> sur votre serveur.</p>';
        }

        $body .= '<div class="alert alert-info">';
        $body .= '<strong>Actions recommandées :</strong><ul style="margin:.5rem 0 0 1.5rem;font-size:.8rem">';
        $body .= '<li><strong>Supprimez ce fichier <code>install.php</code></strong> pour la sécurité</li>';
        $body .= '<li>Connectez-vous et configurez le SEO dans Admin &rarr; Configuration</li>';
        $body .= '<li>Ajoutez vos liens et bannières</li>';
        $body .= '</ul></div>';

        $body .= '<div style="display:flex;gap:1rem;justify-content:center;margin-top:1.5rem;flex-wrap:wrap">';
        $body .= '<a href="login" class="btn btn-success btn-lg">Se connecter maintenant &rarr;</a>';
        $body .= '</div>';
    } else {
        $body .= '<div class="alert alert-error">L\'installation a échoué. Vérifiez les erreurs ci-dessus et réessayez.</div>';
        $body .= '<div class="actions"><a href="?step=3" class="btn btn-secondary">&larr; Réessayer</a></div>';
    }

    renderPage('Étape 4 - Installation', $body, 4);
    exit;
}

// Fallback
header('Location: ?step=1');
