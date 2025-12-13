<?php
/**
 * Helper pour les chemins relatifs
 * Facilite l'accès aux dossiers depuis n'importe où dans l'application
 */

// Racine du projet
define('ROOT_PATH', dirname(__DIR__));

// Chemins vers les dossiers principaux
define('CONFIG_PATH', ROOT_PATH . '/config');
define('APP_PATH', ROOT_PATH . '/app');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('SERVICES_PATH', ROOT_PATH . '/services');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('API_PATH', ROOT_PATH . '/api');
define('VENDOR_PATH', ROOT_PATH . '/vendor');
define('DATABASE_PATH', ROOT_PATH . '/database');
define('DATA_PATH', ROOT_PATH . '/data');

// Chemins vers les vues
define('VIEWS_EMPLOYEE_PATH', APP_PATH . '/Views/employee');
define('VIEWS_MANAGER_PATH', APP_PATH . '/Views/manager');
define('VIEWS_ADMIN_PATH', APP_PATH . '/Views/admin');

// Chemins vers les assets publics
define('CSS_PATH', PUBLIC_PATH . '/css');
define('JS_PATH', PUBLIC_PATH . '/js');
define('UPLOADS_PATH', PUBLIC_PATH . '/uploads');
?>

