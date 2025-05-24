<?php

namespace Mikamatto\ExchangeRates;

use PDO;
use Mikamatto\ExchangeRates\Contracts\ExchangeRateProvider;
use Exception;

class ExchangeRateService {
    private PDO $pdo;
    private ExchangeRateProvider $provider;
    private array $cacheCurrencies = ['EUR', 'USD', 'BTC', 'GBP'];

    public function __construct(PDO $pdo, ExchangeRateProvider $provider) {
        $this->pdo = $pdo;
        $this->provider = $provider;
    }

    public function getRate(string $fromCurrency, string $toCurrency, string $date = 'latest'): ?float {
        if ($fromCurrency === $toCurrency) return 1.0;

        $currentDate = $date === 'latest' ? date('Y-m-d') : $date;

        // Try to get from DB first
        $rate = $this->getRateFromDb($fromCurrency, $toCurrency, $currentDate);
        if ($rate !== null) {
            return $rate;
        }

        // If not in DB, fetch from API
        try {
            $rate = $this->provider->fetchRate($fromCurrency, $toCurrency, $date);
            
            // Cache the rate if currencies are in the cache list
            if ($rate !== null && 
                in_array($fromCurrency, $this->cacheCurrencies) && 
                in_array($toCurrency, $this->cacheCurrencies)) {
                $this->saveRateToDb($fromCurrency, $toCurrency, $rate, $currentDate);
            }

            return $rate;
        } catch (Exception $e) {
            // Log error here if needed
            return null;
        }
    }

    private function getRateFromDb(string $from, string $to, string $date): ?float {
        $stmt = $this->pdo->prepare(
            "SELECT rate FROM rates WHERE base = :base AND target = :target AND rate_date = :date LIMIT 1"
        );
        $stmt->execute([
            'base' => $from,
            'target' => $to,
            'date' => $date,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (float)$row['rate'] : null;
    }

    private function saveRateToDb(string $from, string $to, float $rate, string $date): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO rates (base, target, rate, rate_date) 
             VALUES (:base, :target, :rate, :date) 
             ON DUPLICATE KEY UPDATE rate = :rate"
        );
        $stmt->execute([
            'base' => $from,
            'target' => $to,
            'rate' => $rate,
            'date' => $date,
        ]);
    }
}