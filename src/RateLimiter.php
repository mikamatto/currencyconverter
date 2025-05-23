<?php

namespace App;

use PDO;

class RateLimiter {
    private $pdo;
    private $requestsPerHour;
    private $requestsPerSecond;

    public function __construct(PDO $pdo, int $requestsPerHour = 100, int $requestsPerSecond = 5) {
        $this->pdo = $pdo;
        $this->requestsPerHour = $requestsPerHour;
        $this->requestsPerSecond = $requestsPerSecond;
    }

    public function checkLimit(string $clientId): bool {
        $this->cleanup();
        
        // Check hourly limit
        if (!$this->checkHourlyLimit($clientId)) {
            return false;
        }

        // Check per-second limit
        if (!$this->checkSecondLimit($clientId)) {
            return false;
        }

        // Record this request
        $this->recordRequest($clientId);
        
        return true;
    }

    private function checkHourlyLimit(string $clientId): bool {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) as count FROM rate_limits 
             WHERE client_id = :client_id 
             AND request_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        $stmt->execute(['client_id' => $clientId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] < $this->requestsPerHour;
    }

    private function checkSecondLimit(string $clientId): bool {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) as count FROM rate_limits 
             WHERE client_id = :client_id 
             AND request_time > DATE_SUB(NOW(), INTERVAL 1 SECOND)"
        );
        $stmt->execute(['client_id' => $clientId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] < $this->requestsPerSecond;
    }

    private function recordRequest(string $clientId): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO rate_limits (client_id, request_time) 
             VALUES (:client_id, NOW())"
        );
        $stmt->execute(['client_id' => $clientId]);
    }

    private function cleanup(): void {
        // Remove old records (older than 1 hour)
        $stmt = $this->pdo->prepare(
            "DELETE FROM rate_limits 
             WHERE request_time < DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        $stmt->execute();
    }
}
