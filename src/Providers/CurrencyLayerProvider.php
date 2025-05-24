<?php

namespace Mikamatto\ExchangeRates\Providers;

use Mikamatto\ExchangeRates\Contracts\ExchangeRateProvider;
use InvalidArgumentException;
use RuntimeException;

class CurrencyLayerProvider implements ExchangeRateProvider
{
    private const API_BASE_URL = 'api.currencylayer.com';
    private const EARLIEST_DATE = '1999-01-01';

    private string $apiKey;
    private bool $useHttps;

    public function __construct(string $apiKey, bool $useHttps = true)
    {
        $this->apiKey = $apiKey;
        $this->useHttps = $useHttps;
    }

    public function fetchRate(string $from, string $to, ?string $date = null): float
    {
        if ($from === $to) {
            return 1.0;
        }

        if ($date !== null && strtotime($date) < strtotime(self::EARLIEST_DATE)) {
            throw new InvalidArgumentException('DATE_OUT_OF_RANGE: Date is before earliest available date');
        }

        $endpoint = $date ? '/historical' : '/live';
        $url = $this->buildUrl($endpoint);
        
        $queryParams = http_build_query([
            'access_key' => $this->apiKey,
            'source' => $from,
            'currencies' => $to,
            'date' => $date,
        ]);

        $fullUrl = $url . '?' . $queryParams;
        error_log("CurrencyLayer API Request:");
        error_log("URL: " . $fullUrl);
        error_log("From: " . $from);
        error_log("To: " . $to);
        error_log("Date: " . ($date ?: 'latest'));

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
                'ignore_errors' => true, // This allows us to get the error response
            ],
        ]);

        $response = @file_get_contents($url . '?' . $queryParams, false, $context);
        
        if ($response === false) {
            $error = error_get_last();
            error_log("CurrencyLayer API Error: " . ($error['message'] ?? 'No error message'));
            throw new RuntimeException('NETWORK_ERROR: ' . ($error['message'] ?? 'Failed to fetch exchange rate'));
        }

        error_log("CurrencyLayer API Response: " . $response);
        
        $data = json_decode($response, true);
        if (!is_array($data)) {
            error_log("Invalid JSON response: " . $response);
            throw new RuntimeException('INVALID_RESPONSE: Invalid JSON response from API');
        }
        error_log("API Response: " . json_encode($data, JSON_PRETTY_PRINT));
        
        if (!($data['success'] ?? false)) {
            $errorInfo = $data['error']['info'] ?? 'Unknown API error';
            $errorCode = $data['error']['code'] ?? 0;
            
            error_log("CurrencyLayer API Error: Code=$errorCode, Info=$errorInfo");
            
            if ($errorCode === 104) {
                throw new RuntimeException('RATE_LIMIT_EXCEEDED: ' . $errorInfo);
            } else if ($errorCode === 202) {
                throw new InvalidArgumentException('INVALID_CURRENCY: ' . $errorInfo);
            }
            
            throw new RuntimeException('API_ERROR: ' . $errorInfo);
        }

        $quotes = $data['quotes'] ?? [];
        $quoteName = $from . $to;
        
        if (!isset($quotes[$quoteName])) {
            throw new RuntimeException('RATE_NOT_FOUND: Rate not found for ' . $from . ' to ' . $to);
        }
        
        return (float) $quotes[$quoteName];
    }

    public function isAvailable(): bool
    {
        try {
            $this->fetchRate('USD', 'EUR');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getSupportedCurrencies(): array
    {
        // Return commonly used currencies as examples
        return ['USD', 'EUR', 'GBP', 'JPY', 'CHF', 'AUD', 'CAD'];
    }

    private function isCurrencySupported(string $currency): bool
    {
        // CurrencyLayer supports most ISO currencies, so we'll just validate format
        return preg_match('/^[A-Z]{3}$/', strtoupper($currency)) === 1;
    }

    private function buildUrl(string $endpoint): string
    {
        $scheme = $this->useHttps ? 'https' : 'http';
        return sprintf('%s://%s%s', $scheme, trim(self::API_BASE_URL, 'http://'), $endpoint);
    }
}
