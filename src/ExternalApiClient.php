<?php

namespace Mikamatto\CurrencyConverter;

class ExternalApiClient {
    private $apiKey;
    private $baseUrl = 'https://api.exchangeratesapi.io'; // Or your actual provider

    public function __construct(string $apiKey) {
        $this->apiKey = $apiKey;
    }

    public function fetchRate(string $from, string $to, string $date = 'latest'): ?float {
        $url = "{$this->baseUrl}/{$date}?base={$from}&symbols={$to}&access_key={$this->apiKey}";

        $response = file_get_contents($url);
        $data = json_decode($response, true);

        return $data['rates'][$to] ?? null;
    }
}