<?php

namespace Mikamatto\ExchangeRates;

class Authentication {
    private $secretKey;
    private $useHashValidation;
    private $enabled;

    public function __construct(string $secretKey, bool $useHashValidation = false, bool $enabled = true) {
        $this->secretKey = $secretKey;
        $this->useHashValidation = $useHashValidation;
        $this->enabled = $enabled;
    }

    public function validateRequest(array $headers, ?string $from = null, ?string $to = null): bool {
        if (!$this->enabled) {
            return true;
        }
        $authHeader = $headers['Authorization'] ?? $headers['HTTP_AUTHORIZATION'] ?? null;
        
        if (!$authHeader || !preg_match('/^Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return false;
        }

        $token = $matches[1];

        if ($this->useHashValidation) {
            // Hash-based validation: token should be hash of secret + currencies
            $expectedHash = hash('sha256', $this->secretKey . $from . $to);
            return hash_equals($expectedHash, $token);
        } else {
            // Simple bearer token validation
            return hash_equals($this->secretKey, $token);
        }
    }

    public function generateToken(string $from = '', string $to = ''): string {
        if ($this->useHashValidation) {
            return hash('sha256', $this->secretKey . $from . $to);
        }
        return $this->secretKey;
    }
}
