<?php

namespace Mikamatto\ExchangeRates;

use PDO;
use PDOException;

class Database {
    private ?PDO $pdo = null;
    private bool $cachingEnabled;

    public function __construct(array $config) {
        $this->cachingEnabled = filter_var($config['caching_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        error_log("Caching enabled: " . ($this->cachingEnabled ? 'true' : 'false'));
        
        if (!$this->cachingEnabled) {
            error_log("Caching is disabled, skipping database connection");
            return;
        }

        try {
            $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
            error_log("Attempting to connect to database with DSN: $dsn");
            
            $this->pdo = new PDO(
                $dsn,
                $config['user'],
                $config['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            error_log("Successfully connected to database");

            // Create the rates table if it doesn't exist
            $this->createRatesTable();
        } catch (PDOException $e) {
            error_log("Failed to connect to database: " . $e->getMessage());
            $this->pdo = null;
        }
        // Debug connection details
        error_log("Attempting database connection with:");
        error_log("Host: " . ($config['host'] ?? 'not set'));
        error_log("Database: " . ($config['dbname'] ?? 'not set'));
        error_log("User: " . ($config['user'] ?? 'not set'));

        // In Docker environment, always use the container name as host
        $dsn = "mysql:host=exchangerates_db;dbname={$config['dbname']};charset=utf8mb4";

        error_log("DSN: " . $dsn);

        try {
            $this->pdo = new PDO($dsn, $config['user'], $config['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (\PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw $e;
        }
    }

    public function getConnection(): ?PDO {
        return $this->pdo;
    }

    public function isCachingEnabled(): bool {
        return $this->cachingEnabled;
    }

    private function createRatesTable(): void {
        if (!$this->pdo) {
            return;
        }

        try {
            error_log('Attempting to create exchange_rates table...');
            $sql = "CREATE TABLE IF NOT EXISTS exchange_rates (
                id INT AUTO_INCREMENT PRIMARY KEY,
                from_currency VARCHAR(3) NOT NULL,
                to_currency VARCHAR(3) NOT NULL,
                rate DECIMAL(20, 10) NOT NULL,
                date DATE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_currency_pair_date (from_currency, to_currency, date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $result = $this->pdo->exec($sql);
            if ($result === false) {
                $error = $this->pdo->errorInfo();
                error_log('Failed to create table: ' . implode(', ', $error));
            } else {
                error_log('Successfully created/verified exchange_rates table');
            }
        } catch (PDOException $e) {
            error_log('Failed to create rates table: ' . $e->getMessage());
        }
    }

    public function getCachedRate(string $from, string $to, ?string $date = null): ?float {
        if (!$this->pdo || !$this->cachingEnabled) {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT rate FROM exchange_rates
                WHERE from_currency = :from
                AND to_currency = :to
                AND date = :date
                AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                ORDER BY created_at DESC
                LIMIT 1
            ");

            $stmt->execute([
                ':from' => $from,
                ':to' => $to,
                ':date' => $date ?: date('Y-m-d')
            ]);

            $result = $stmt->fetch(PDO::FETCH_COLUMN);
            return $result !== false ? (float)$result : null;
        } catch (PDOException $e) {
            error_log('Failed to get cached rate: ' . $e->getMessage());
            return null;
        }
    }

    public function cacheRate(string $from, string $to, float $rate, ?string $date = null): void {
        error_log("Attempting to cache rate: $from to $to = $rate");
        if (!$this->pdo || !$this->cachingEnabled) {
            error_log("Cannot cache rate: " . (!$this->pdo ? 'No PDO connection' : 'Caching disabled'));
            return;
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO exchange_rates (from_currency, to_currency, rate, date)
                VALUES (:from, :to, :rate, :date)
            ");

            $stmt->execute([
                ':from' => $from,
                ':to' => $to,
                ':rate' => $rate,
                ':date' => $date ?: date('Y-m-d')
            ]);
        } catch (PDOException $e) {
            error_log('Failed to cache rate: ' . $e->getMessage());
        }
    }
}