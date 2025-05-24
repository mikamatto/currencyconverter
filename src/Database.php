<?php

namespace Mikamatto\ExchangeRates;

use PDO;
use PDOException;
use RuntimeException;

class Database {
    private $pdo;
    private $cachingEnabled;

    public function __construct(array $config) {
        $this->cachingEnabled = filter_var($_ENV['CACHING_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN);
        // Debug connection details
        error_log("Attempting database connection with:");
        error_log("Host: " . ($config['host'] ?? 'not set'));
        error_log("Database: " . ($config['dbname'] ?? 'not set'));
        error_log("User: " . ($config['user'] ?? 'not set'));

        // In Docker, we need to use the container name as host
        $host = getenv('DOCKER_ENV') ? 'exchangerates_db' : $config['host'];
        $dsn = "mysql:host={$host};dbname={$config['dbname']};charset=utf8mb4";

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

    public function getConnection(): PDO {
        return $this->pdo;
    }

    public function isCachingEnabled(): bool {
        return $this->cachingEnabled;
    }

    public function getRate(string $from, string $to, ?string $date = null): ?string {
        if (!$this->pdo) {
            throw new RuntimeException('No database connection available');
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT rate FROM exchange_rate
                WHERE `from` = :from AND `to` = :to AND date = :date
            ");

            $stmt->execute([
                ':from' => $from,
                ':to' => $to,
                ':date' => $date ?: date('Y-m-d')
            ]);

            $result = $stmt->fetch(PDO::FETCH_COLUMN);
            if ($result !== false) {
                $rate = (float)$result;
                // Format small numbers with more precision
                if ($rate < 0.01 && $rate > 0) {
                    return number_format($rate, 10, '.', '');
                }
                return number_format($rate, 8, '.', '');
            }

            // Try reverse rate
            $stmt = $this->pdo->prepare("
                SELECT rate FROM exchange_rate
                WHERE `from` = :to AND `to` = :from AND date = :date
            ");

            $stmt->execute([
                ':from' => $from,
                ':to' => $to,
                ':date' => $date ?: date('Y-m-d')
            ]);

            $result = $stmt->fetch(PDO::FETCH_COLUMN);
            if ($result !== false) {
                // Calculate inverse rate
                $inverseRate = 1 / (float)$result;
                // Format small numbers with more precision
                if ($inverseRate < 0.01 && $inverseRate > 0) {
                    return number_format($inverseRate, 10, '.', '');
                }
                return number_format($inverseRate, 8, '.', '');
            }
            return null;
        } catch (PDOException $e) {
            error_log('Failed to get rate: ' . $e->getMessage());
            throw new RuntimeException('Failed to get rate');
        }
    }
}