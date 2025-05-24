<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Mikamatto\ExchangeRates\Database;
use Mikamatto\ExchangeRates\ExchangeRateService;
use Mikamatto\ExchangeRates\Authentication;
use Mikamatto\ExchangeRates\Providers\CurrencyLayerProvider;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
$config = require __DIR__ . '/../config/config.php';

// Initialize services
$database = new Database($config['db']);
$provider = new CurrencyLayerProvider($config['api_key']);
$service = new ExchangeRateService($database->getConnection(), $provider);
$auth = new Authentication($config['api_secret'], $config['use_hash_validation'] ?? false);

header('Content-Type: application/json');

// Get request parameters
$from = $_GET['from'] ?? null;
$to = $_GET['to'] ?? null;
$date = $_GET['date'] ?? 'latest';

// Validate required parameters
if (!$from || !$to) {
    http_response_code(400);
    echo json_encode([
        'error' => 'INVALID_PARAMETERS',
        'message' => 'Both "from" and "to" currencies are required'
    ]);
    exit;
}

// Validate authentication
if (!$auth->validateRequest(getallheaders(), $from, $to)) {
    http_response_code(401);
    echo json_encode([
        'error' => 'UNAUTHORIZED',
        'message' => 'Invalid or missing authentication token'
    ]);
    exit;
}

// Validate date format if provided
if ($date !== 'latest') {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) > time()) {
        http_response_code(400);
        echo json_encode([
            'error' => 'INVALID_DATE',
            'message' => 'Date must be in YYYY-MM-DD format and not in the future'
        ]);
        exit;
    }
}

try {
    $rate = $service->getRate($from, $to, $date);

    if ($rate === null) {
        http_response_code(404);
        echo json_encode([
            'error' => 'Rate not found',
            'message' => 'Exchange rate not available for the specified currencies and date'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'from' => $from,
            'to' => $to,
            'rate' => $rate,
            'date' => $date === 'latest' ? date('Y-m-d') : $date,
            'timestamp' => time()
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => 'An error occurred while processing your request'
    ]);
}