<?php

namespace Mikamatto\CurrencyConverter\Providers;

use Mikamatto\CurrencyConverter\Contracts\ExchangeRateProvider;
use Exception;

class ExchangeRatesApiProvider implements ExchangeRateProvider
{
    private string $apiKey;
    private string $baseUrl = 'https://api.exchangeratesapi.io';
    private array $supportedCurrencies = ['EUR', 'USD', 'GBP', 'JPY', 'BTC', 'ETH'];

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function fetchRate(string $from, string $to, string $date = 'latest'): ?float
    {
        if (!$this->isAvailable()) {
            throw new Exception('API key not configured');
        }

        $url = "{$this->baseUrl}/{$date}?base={$from}&symbols={$to}&access_key={$this->apiKey}";

        try {
            $response = file_get_contents($url);
            $data = json_decode($response, true);

            if (!isset($data['rates'][$to])) {
                return null;
            }

            return (float)$data['rates'][$to];
        } catch (Exception $e) {
            throw new Exception("Failed to fetch rate: {$e->getMessage()}");
        }
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    public function getSupportedCurrencies(): array
    {
        return $this->supportedCurrencies;
    }
}
