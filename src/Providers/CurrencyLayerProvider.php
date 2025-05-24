<?php

namespace Mikamatto\ExchangeRates\Providers;

use Mikamatto\ExchangeRates\Contracts\ExchangeRateProvider;
use InvalidArgumentException;
use RuntimeException;

class CurrencyLayerProvider implements ExchangeRateProvider
{
    private const API_BASE_URL = 'http://api.currencylayer.com';
    private const EARLIEST_DATE = '1999-01-01';

    private string $apiKey;
    private bool $useHttps;
    private array $supportedCurrencies = ['USD', 'EUR', 'GBP', 'JPY', 'CHF', 'AUD', 'CAD', 'BTC'];

    public function __construct(string $apiKey, bool $useHttps = true)
    {
        $this->apiKey = $apiKey;
        $this->useHttps = $useHttps;
    }

    public function fetchRate(string $from, string $to, ?string $date = null): float
    {
        if (!$this->isCurrencySupported($from) || !$this->isCurrencySupported($to)) {
            throw new InvalidArgumentException(
                sprintf('Unsupported currency pair: %s to %s', $from, $to)
            );
        }

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

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
            ],
        ]);

        $response = @file_get_contents($url . '?' . $queryParams, false, $context);
        
        if ($response === false) {
            throw new RuntimeException('NETWORK_ERROR: Failed to fetch exchange rate from CurrencyLayer API');
        }

        $data = json_decode($response, true);
        
        if (!$data['success']) {
            if (isset($data['error']['code']) && $data['error']['code'] === 104) {
                throw new RuntimeException('RATE_LIMIT_EXCEEDED: Rate limit exceeded');
            }
            throw new RuntimeException('API_ERROR: ' . ($data['error']['info'] ?? 'Unknown API error'));
        }

        $rate = $data['quotes'][$from . $to] ?? null;
        if ($rate === null) {
            throw new RuntimeException('RATE_NOT_FOUND: Rate not found in response');
        }

        return (float) $rate;
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
        return $this->supportedCurrencies;
    }

    private function isCurrencySupported(string $currency): bool
    {
        return in_array(strtoupper($currency), $this->supportedCurrencies);
    }

    private function buildUrl(string $endpoint): string
    {
        $scheme = $this->useHttps ? 'https' : 'http';
        return sprintf('%s://%s%s', $scheme, trim(self::API_BASE_URL, 'http://'), $endpoint);
    }
}
