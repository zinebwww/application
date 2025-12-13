<?php
/**
 * Proxy OSRM Ultra-Robuste (Version Finale)
 * Tente plusieurs serveurs (HTTPS et HTTP) pour garantir une réponse.
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';

if (empty($path)) {
    $uri = $_SERVER['REQUEST_URI'];
    $script = $_SERVER['SCRIPT_NAME'];
    if (strpos($uri, $script) === 0) {
        $path = substr($uri, strlen($script));
    }
}

if (($pos = strpos($path, '?')) !== false) {
    $path = substr($path, 0, $pos);
}

if (empty($path) || $path === '/') {
    http_response_code(400);
    echo json_encode(['error' => 'No path']);
    exit;
}

// Liste des serveurs à tenter par ordre de priorité
$servers = [
    // 1. OpenStreetMap Allemagne (Le plus fiable)
    'https://routing.openstreetmap.de/routed-car',
    // 2. OSRM Demo (Officiel)
    'https://router.project-osrm.org',
    // 3. OSRM Demo en HTTP (Si SSL local bloque)
    'http://router.project-osrm.org'
];

$success = false;
$lastError = '';

foreach ($servers as $baseUrl) {
    // Construction de l'URL cible
    // Attention : routing.openstreetmap.de attend /routed-car/route/v1/...
    // Notre path est /route/v1/driving/...
    // Si on utilise le serveur allemand, on doit adapter le préfixe si besoin.
    // Mais routing.openstreetmap.de gère souvent les alias.
    // Pour être sûr, on garde la logique simple : baseUrl + path

    $targetUrl = $baseUrl . $path;
    if (!empty($_SERVER['QUERY_STRING'])) {
        $targetUrl .= '?' . $_SERVER['QUERY_STRING'];
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $targetUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'ExpenseManager/1.0');

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response !== false && $httpCode == 200) {
        http_response_code(200);
        echo $response;
        $success = true;
        break; // On a réussi, on arrête
    } else {
        $lastError = "Server $baseUrl failed (Code: $httpCode, Error: $error)";
    }
}

if (!$success) {
    http_response_code(502);
    echo json_encode(['error' => 'All OSRM servers failed', 'details' => $lastError]);
}
?>