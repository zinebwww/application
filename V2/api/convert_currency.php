<?php

declare(strict_types=1);

use Services\CurrencyConverter;

require_once __DIR__ . '/../services/CurrencyConverter.php';

header('Content-Type: application/json; charset=utf-8');

// Désactiver l'affichage des erreurs pour éviter de corrompre le JSON
ini_set('display_errors', '0');
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Méthode HTTP non autorisée. Utilisez POST.',
    ]);
    exit;
}

$amount = $_POST['amount'] ?? null;
$currency = $_POST['currency'] ?? null;

if ($amount === null || $currency === null) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Les paramètres amount et currency sont requis.',
    ]);
    exit;
}

try {
    $converted = CurrencyConverter::convertToMAD($amount, (string) $currency);
    $rate = CurrencyConverter::getRate((string) $currency);

    echo json_encode([
        'success' => true,
        'converted' => $converted,
        'rate' => $rate,
    ]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur lors de la conversion.',
    ]);
}
