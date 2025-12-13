<?php
// osrm_proxy.php - Fixed Version for OSRM Public API

// 1. Define the OSRM Service URL (HTTPS is required)
// 1. Define the OSRM Service URL (Using German server for better reliability)
$base_service_url = "https://routing.openstreetmap.de/routed-car/route/v1/driving/";

// 2. Parse the incoming request to extract coordinates
// This logic handles different server configurations (PATH_INFO vs REQUEST_URI)
$request_uri = $_SERVER['REQUEST_URI'];
$script_name = $_SERVER['SCRIPT_NAME'];

// Extract the part after the script name
if (strpos($request_uri, $script_name) === 0) {
    $path_and_query = substr($request_uri, strlen($script_name));
} else {
    // Fallback if URL rewriting is active
    $path_and_query = str_replace('/V22/V2/public/osrm_proxy.php', '', $request_uri);
}

// Clean up the path: remove potential duplicate route prefixes and leading slashes
$path_and_query = str_replace('/route/v1/driving/', '', $path_and_query);
$path_and_query = ltrim($path_and_query, '/');

// 3. Construct the final target URL
$final_url = $base_service_url . $path_and_query;

// 4. Initialize cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $final_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// IMPORTANT: Set a User-Agent to avoid HTTP 403/502 blocks from the public API
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');

// Follow redirects (http -> https)
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

// Disable SSL verification (Use only for local dev environments to avoid certificate issues)
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

// 5. Execute request
$response = curl_exec($ch);
$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    // Handle cURL connection errors
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Proxy cURL Error: ' . curl_error($ch)]);
    exit;
}

curl_close($ch);

// 6. Return response to the frontend
http_response_code($http_status);
header('Content-Type: application/json');

// Check for empty response on error
if ($http_status >= 400 && empty($response)) {
    echo json_encode([
        'error' => "Remote API returned HTTP $http_status",
        'target_url' => $final_url
    ]);
} else {
    echo $response;
}
?>