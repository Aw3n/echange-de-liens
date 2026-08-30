<?php
declare(strict_types=1);

/**
 * Point d'entrée principal de l'application
 * Echange de Liens CMS
 */

// Chargement de l'autoloader
require_once dirname(__DIR__) . '/app/autoload.php';

// Chargement des helpers
require_once dirname(__DIR__) . '/app/Helpers/helpers.php';

// Initialisation de l'application
use App\Core\App;
use App\Core\Config;

// Chargement de la configuration
Config::load('app');
Config::load('database');

// Gestion des erreurs en développement
if (Config::get('app.app.debug', false)) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Compression gzip si disponible
if (extension_loaded('zlib') && !ini_get('zlib.output_compression')) {
    ob_start('ob_gzhandler');
}

// Démarrage de l'application
$app = new App();
$app->run();
