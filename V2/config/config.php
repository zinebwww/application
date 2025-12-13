<?php
/**
 * Configuration de la base de données
 */

// Paramètres de connexion
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'frais_deplacement');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Dossier d'upload des justificatifs
define('UPLOAD_DIR', __DIR__ . '/../public/uploads/');
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5 Mo
define('ALLOWED_EXTENSIONS', ['pdf', 'jpg', 'jpeg', 'png']);

// Tarif par kilomètre pour le calcul des frais de transport (en DH/km)
define('TARIF_PAR_KM', 1.2); // 1.2 DH par kilomètre

// Connexion à la base de données
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}

// Créer le dossier uploads s'il n'existe pas
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
?>

