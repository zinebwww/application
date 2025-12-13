<?php
/**
 * Proxy pour Nominatim (Géocodage)
 * Ajoute le User-Agent obligatoire pour éviter l'erreur 403 Forbidden.
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// Récupérer les paramètres
$lat = $_GET['lat'] ?? '';
$lon = $_GET['lon'] ?? '';
$query = $_GET['q'] ?? ''; // Pour la recherche textuelle si besoin

if ((empty($lat) || empty($lon)) && empty($query)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

// URL de base
$baseUrl = 'https://nominatim.openstreetmap.org/reverse';
$params = [
    'format' => 'json',
    'lat' => $lat,
    'lon' => $lon,
    'zoom' => 18,
    'addressdetails' => 1
];

// Si c'est une recherche textuelle (forward geocoding)
if (!empty($query)) {
    $baseUrl = 'https://nominatim.openstreetmap.org/search';
    $params = [
        'format' => 'json',
        'q' => $query,
        'limit' => 5,
        'addressdetails' => 1
    ];
}

$url = $baseUrl . '?' . http_build_query($params);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
// User-Agent OBLIGATOIRE pour Nominatim
curl_setopt($ch, CURLOPT_USERAGENT, 'ExpenseManagerLocal/1.0 (contact@localhost)');
curl_setopt($ch, CURLOPT_REFERER, 'http://localhost');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Curl Error: ' . $curlError]);
} else {
    http_response_code($httpCode);
    echo $response;
}
?>