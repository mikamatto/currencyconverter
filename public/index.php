<?php

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once __DIR__ . '/../vendor/autoload.php';

use Mikamatto\ExchangeRates\Providers\CurrencyLayerProvider;
use Mikamatto\ExchangeRates\Database;
use Mikamatto\ExchangeRates\Authentication;
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
    // Get request parameters
    $from = $_GET['from'] ?? null;
    $to = $_GET['to'] ?? null;
    $date = $_GET['date'] ?? null;

    // Validate required parameters first
    if (!$from || !$to) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Bad Request',
            'message' => 'Missing required parameters: from and to currencies must be specified'
        ]);
        exit;
    }
    // Initialize authentication
    $auth = new Authentication(
        $_ENV['API_SECRET'],
        filter_var($_ENV['USE_HASH_VALIDATION'] ?? false, FILTER_VALIDATE_BOOLEAN),
        filter_var($_ENV['AUTH_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN)
    );

    // Check authentication
    if (!$auth->validateRequest(getallheaders(), $from, $to)) {
        http_response_code(401);
        echo json_encode([
            'error' => 'Unauthorized',
            'message' => 'Invalid or missing authentication token'
        ]);
        exit;
    }

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

    // Try to get rate from cache if caching is enabled and it's a historical query
    if (filter_var($_ENV['CACHING_ENABLED'], FILTER_VALIDATE_BOOLEAN) && $date !== null) {
        try {
            $db = new Database([
                'host' => $_ENV['DB_HOST'],
                'dbname' => $_ENV['DB_NAME'],
                'user' => $_ENV['DB_USER'],
                'pass' => $_ENV['DB_PASS']
            ]);
            
            $rate = $db->getRate($from, $to, $date);
            if ($rate !== null) {
                $response = [
                    'success' => true,
                    'data' => [
                        'from' => $from,
                        'to' => $to,
                        'rate' => $rate,
                        'date' => $date ?? date('Y-m-d'),
                        'timestamp' => $date ? strtotime($date) : time()
                    ]
                ];
                // Force JSON to keep numeric strings as strings
    echo json_encode($response, JSON_PRESERVE_ZERO_FRACTION);
                exit;
            }
        } catch (Exception $e) {
            // Log the error but continue with API request
            error_log('Database error: ' . $e->getMessage());
        }
    }

    $rate = null;
    $cacheError = null;
    $db = null;

    try {
        // Test database connection
        $db = new Database([
            'host' => $_ENV['DB_HOST'],
            'dbname' => $_ENV['DB_NAME'],
            'user' => $_ENV['DB_USER'],
            'pass' => $_ENV['DB_PASS']
        ]);

        // If not in cache or caching failed, fetch from API
        if ($db->isCachingEnabled() && $date !== null) {
            // Try to get rate from cache first
            $rate = $db->getRate($from, $to, $date);
        }
    } catch (\PDOException $e) {
        error_log('Database connection error: ' . $e->getMessage());
        http_response_code(503);
        echo json_encode([
            'error' => 'Service Unavailable',
            'message' => 'Database connection failed'
        ]);
        exit;
    } catch (RuntimeException $e) {
        $cacheError = $e->getMessage();
        error_log('Cache error: ' . $cacheError);
    }

    // If not in cache or caching failed, fetch from API
    if ($rate === null) {
        $provider = new CurrencyLayerProvider($_ENV['API_KEY'], true);
        $rawRate = $provider->fetchRate($from, $to, $date);
        
        // Format rate before saving
        if ($rawRate < 0.01 && $rawRate > 0) {
            $rate = number_format($rawRate, 10, '.', '');
        } else {
            $rate = number_format($rawRate, 8, '.', '');
        }

        // Only try to cache historical rates if we don't have a previous cache error
        if ($date !== null && $cacheError === null && isset($db) && $db->isCachingEnabled()) {
            try {
                $db->saveRate($from, $to, $rate, $date);
            } catch (RuntimeException $e) {
                $cacheError = $e->getMessage();
                error_log('Cache error while storing rates: ' . $cacheError);
            }
        }
    }

    if ($rate === null) {
        http_response_code(404);
        echo json_encode([
            'error' => 'Rate not found',
            'message' => 'Exchange rate not available for the specified currencies and date'
        ]);
        exit;
    }

    // Rate is already formatted as string from Database.php
    $formattedRate = $rate;

    $response = [
        'success' => true,
        'data' => [
            'from' => $from,
            'to' => $to,
            'rate' => (string)$formattedRate,
            'date' => $date ?: date('Y-m-d'),
            'timestamp' => time()
        ]
    ];

    // If caching was enabled but failed, include the error
    if ($cacheError !== null && filter_var($_ENV['CACHING_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
        $response['warning'] = 'Rate retrieved but caching failed: ' . $cacheError;
    }

    echo json_encode($response, JSON_PRESERVE_ZERO_FRACTION);
} catch (\Exception $e) {
    error_log('Error in API call: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'message' => $e->getMessage()]);
}