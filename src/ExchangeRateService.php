<?php

namespace App;

use PDO;
use App\ExternalApiClient;

class ExchangeRateService {
    private $pdo;
    private $api;
    private $baseCurrency;

    public function __construct(PDO $pdo, ExternalApiClient $api, string $baseCurrency) {
        $this->pdo = $pdo;
        $this->api = $api;
        $this->baseCurrency = $baseCurrency;
    }

    public function getRate(string $toCurrency, string $date = 'latest'): ?float {
        if ($toCurrency === $this->baseCurrency) return 1.0;

        // Try to get from DB
        $stmt = $this->pdo->prepare("SELECT rate FROM rates WHERE base = :base AND target = :target AND rate_date = :date LIMIT 1");
        $stmt->execute([
            'base' => $this->baseCurrency,
            'target' => $toCurrency,
            'date' => $date === 'latest' ? date('Y-m-d') : $date,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) return (float)$row['rate'];

        // Fetch from API
        $rate = $this->api->fetchRate($this->baseCurrency, $toCurrency, $date);
        if ($rate !== null && in_array($toCurrency, ['EUR', 'USD', 'BTC'])) {
            // Save only if in allowed list
            $insert = $this->pdo->prepare("INSERT INTO rates (base, target, rate, rate_date) VALUES (:base, :target, :rate, :date)");
            $insert->execute([
                'base' => $this->baseCurrency,
                'target' => $toCurrency,
                'rate' => $rate,
                'date' => $date === 'latest' ? date('Y-m-d') : $date,
            ]);
        }

        return $rate;
    }
}