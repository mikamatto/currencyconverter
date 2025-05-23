<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;
use App\ExternalApiClient;
use App\ExchangeRateService;

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
$config = require __DIR__ . '/../config/config.php';
$database = new Database($config['db']);
$api = new ExternalApiClient($config['api_key']);
$service = new ExchangeRateService($database->getConnection(), $api, $config['base_currency']);

header('Content-Type: application/json');

$from = $_GET['from'] ?? 'GBP';
$to = $_GET['to'] ?? null;
$date = $_GET['date'] ?? 'latest';

if (!$to || $from !== $config['base_currency']) {
    http_response_code(400);
    echo json_encode(['error' => 'Only base currency GBP supported or "to" parameter missing.']);
    exit;
}

$rate = $service->getRate($to, $date);

if ($rate === null) {
    http_response_code(404);
    echo json_encode(['error' => 'Rate not found.']);
    exit;
}

echo json_encode(['rate' => $rate]);