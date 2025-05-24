<?php

namespace Mikamatto\ExchangeRates;

use PDO;
use PDOException;
use RuntimeException;

class Database {
    private ?PDO $pdo = null;
    private bool $cachingEnabled;

    public function __construct(
        string $host,
        string $dbname,
        string $user,
        string $pass
    ) {
        $this->cachingEnabled = filter_var($_ENV['CACHING_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN);

        
        if (!$this->cachingEnabled) {

            return;
        }

        try {
            $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";

            
            $this->pdo = new PDO(
                $dsn,
                $user,
                $pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );


            // Create the rates table if it doesn't exist
            $this->createRatesTable();
        } catch (PDOException $e) {
            // If caching is enabled but DB fails, this is a critical error
            $error = 'Database connection failed: ' . $e->getMessage();
            error_log($error);
            throw new RuntimeException($error);
        }



        try {
            $this->pdo = new PDO($dsn, $user, $pass, [
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
            throw new RuntimeException('No database connection available');
        }

        try {

            $sql = "CREATE TABLE IF NOT EXISTS exchange_rate (
                `from` VARCHAR(10) NOT NULL,
                `to` VARCHAR(10) NOT NULL,
                rate DECIMAL(20, 10) NOT NULL,
                date DATE NOT NULL,
                PRIMARY KEY (`from`, `to`, date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $result = $this->pdo->exec($sql);
            if ($result === false) {
                $error = $this->pdo->errorInfo();
                throw new RuntimeException('Failed to create table: ' . implode(', ', $error));
            }
            
            // Verify the table exists by trying to select from it
            $this->pdo->query('SELECT 1 FROM exchange_rate LIMIT 1');

        } catch (PDOException $e) {
            $error = 'Failed to create/verify rates table: ' . $e->getMessage();
            error_log($error);
            throw new RuntimeException($error);
        }
    }

    public function getRate(string $from, string $to, ?string $date = null): ?float {
        if (!$this->cachingEnabled) {
            return null;
        }
        if (!$this->pdo) {
            throw new RuntimeException('No database connection available');
        }

        try {
            $date = $date ?: date('Y-m-d');
            
            // Try to get direct rate first
            $stmt = $this->pdo->prepare("
                SELECT rate FROM exchange_rate
                WHERE `from` = :from AND `to` = :to AND date = :date
            ");

            $stmt->execute([
                ':from' => $from,
                ':to' => $to,
                ':date' => $date
            ]);

            $result = $stmt->fetch(PDO::FETCH_COLUMN);
            if ($result !== false) {
                return (float)$result;
            }

            // Try reverse rate
            $stmt = $this->pdo->prepare("
                SELECT 1/rate FROM exchange_rate
                WHERE `from` = :to AND `to` = :from AND date = :date
            ");

            $stmt->execute([
                ':from' => $from,
                ':to' => $to,
                ':date' => $date
            ]);

            $result = $stmt->fetch(PDO::FETCH_COLUMN);
            return $result !== false ? (float)$result : null;

        } catch (PDOException $e) {
            $error = 'Failed to get cached rate: ' . $e->getMessage();
            error_log($error);
            throw new RuntimeException($error);
        }
    }

    public function saveRate(string $from, string $to, float $rate, ?string $date = null): void {

        if (!$this->cachingEnabled) {
            return;
        }
        if (!$this->pdo) {
            throw new RuntimeException('No database connection available');
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO exchange_rate (`from`, `to`, rate, date)
                VALUES (:from, :to, :rate, :date)
                ON DUPLICATE KEY UPDATE rate = :rate
            ");

            if (!$stmt->execute([
                ':from' => $from,
                ':to' => $to,
                ':rate' => $rate,
                ':date' => $date ?: date('Y-m-d')
            ])) {
                throw new RuntimeException('Failed to insert/update rate');
            }
        } catch (PDOException $e) {
            $error = 'Failed to cache rate: ' . $e->getMessage();
            error_log($error);
            throw new RuntimeException($error);
        }
    }
}