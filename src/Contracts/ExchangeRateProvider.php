<?php

namespace Mikamatto\ExchangeRates\Contracts;

interface ExchangeRateProvider
{
    /**
     * Fetch exchange rate for a currency pair
     *
     * @param string $from Source currency code
     * @param string $to Target currency code
     * @param string $date Date in YYYY-MM-DD format or 'latest'
     * @return float|null Exchange rate or null if not available
     * @throws \Exception If there's an error fetching the rate
     */
    public function fetchRate(string $from, string $to, string $date = 'latest'): ?float;

    /**
     * Check if the provider is available and configured correctly
     *
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * Get list of supported currencies
     *
     * @return array Array of currency codes
     */
    public function getSupportedCurrencies(): array;
}
