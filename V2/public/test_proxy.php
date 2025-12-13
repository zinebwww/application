<?php
header("Content-Type: text/plain");

echo "Diagnostic OSRM Proxy\n";
echo "=====================\n\n";

$urls = [
    "Primary (HTTPS)" => "https://router.project-osrm.org/route/v1/driving/-0.359,48.040;4.567,45.003?overview=false",
    "Primary (HTTP)" => "http://router.project-osrm.org/route/v1/driving/-0.359,48.040;4.567,45.003?overview=false",
    "Backup (HTTPS)" => "https://routing.openstreetmap.de/routed-car/route/v1/driving/-0.359,48.040;4.567,45.003?overview=false"
];

foreach ($urls as $name => $url) {
    echo "Testing $name...\n";
    echo "URL: $url\n";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');

    $start = microtime(true);
    $response = curl_exec($ch);
    $end = microtime(true);

    $error = curl_error($ch);
    $errno = curl_errno($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    echo "Time: " . round($end - $start, 3) . "s\n";
    echo "HTTP Code: " . $info['http_code'] . "\n";

    if ($errno) {
        echo "cURL Error ($errno): $error\n";
    } else {
        echo "Response Length: " . strlen($response) . " bytes\n";
        echo "Response Preview: " . substr($response, 0, 100) . "...\n";
    }
    echo "----------------------------------------\n\n";
}
?>