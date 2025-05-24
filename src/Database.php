<?php

namespace Mikamatto\ExchangeRates;

use PDO;

class Database {
    private $pdo;

    public function __construct(array $config) {
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

    public function getConnection(): PDO {
        return $this->pdo;
    }
}