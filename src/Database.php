<?php

namespace Mikamatto\CurrencyConverter;

use PDO;

class Database {
    private $pdo;

    public function __construct(array $config) {
        $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
        $this->pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }

    public function getConnection(): PDO {
        return $this->pdo;
    }
}