<?php

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once __DIR__ . '/../vendor/autoload.php';

use Mikamatto\ExchangeRates\Providers\CurrencyLayerProvider;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Set content type to JSON
header('Content-Type: application/json');

// Add CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed', 'message' => 'Only GET requests are allowed']);
    exit;
}

try {
    $from = $_GET['from'] ?? null;
    $to = $_GET['to'] ?? null;
    $date = $_GET['date'] ?? null;

    if (!$from || !$to) {
        http_response_code(400);
        echo json_encode(['error' => 'Bad Request', 'message' => 'Missing required parameters']);
        exit;
    }

    // Early return for same currency conversion
    if ($from === $to) {
        echo json_encode([
            'success' => true,
            'data' => [
                'from' => $from,
                'to' => $to,
                'rate' => '1.00',
                'date' => $date ?: date('Y-m-d'),
                'timestamp' => time()
            ]
        ]);
        exit;
    }

    $provider = new CurrencyLayerProvider($_ENV['API_KEY'], true);
    $rate = $provider->fetchRate($from, $to, $date);

    if ($rate === null) {
        http_response_code(404);
        echo json_encode([
            'error' => 'Rate not found',
            'message' => 'Exchange rate not available for the specified currencies and date'
        ]);
        exit;
    }

    // Format rate to ensure decimal format for small numbers
    $formattedRate = number_format($rate, 10, '.', '');
    // Remove trailing zeros while keeping at least 2 decimal places
    $formattedRate = rtrim(rtrim($formattedRate, '0'), '.');
    if (substr_count($formattedRate, '.') === 0) {
        $formattedRate .= '.00';
    } elseif (strlen($formattedRate) - strrpos($formattedRate, '.') - 1 < 2) {
        $formattedRate .= '0';
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'from' => $from,
            'to' => $to,
            'rate' => $formattedRate,
            'date' => $date ?: date('Y-m-d'),
            'timestamp' => time()
        ]
    ], JSON_PRESERVE_ZERO_FRACTION);
} catch (\Exception $e) {
    error_log('Error in API call: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'message' => $e->getMessage()]);
}