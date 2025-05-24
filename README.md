# Exchange Rates API

A lightweight PHP API for currency exchange rates with caching capabilities. Designed to work as a middleware between your application and exchange rate providers.

## Features

- Direct currency conversion
- Real-time and historical exchange rates
- Database caching for frequently requested pairs
- Bearer token authentication
- Standardized error responses
- Efficient API usage with minimal requests

## Provider

### CurrencyLayer
- Direct currency conversion with paid subscription
- Historical rates back to 1999
- Supports all major currencies and cryptocurrencies
- Real-time exchange rates via `/live` endpoint
- Historical rates via `/historical` endpoint
- Requires API key from [currencylayer.com](https://currencylayer.com)

## Setup

1. **Clone the repository**
```bash
git clone <repository-url>
cd exchangerates
```

2. **Set up environment variables**
```bash
cp .env.example .env
```
Edit `.env` and set your values:
- Database credentials
- External API key
- API secret for authentication
- Toggle hash validation if needed

3. **Create the database**
```bash
mysql -u root -p
CREATE DATABASE exchangerates;
exit;
```

4. **Import database schema**
```bash
mysql -u root -p exchangerates < database/schema.sql
```

## Installation

1. Clone the repository
2. Run `composer install`
3. Copy `.env.example` to `.env` and configure your settings:
   ```env
   # Database Configuration
   DB_HOST=localhost
   DB_NAME=exchangerates
   DB_USER=root
   DB_PASS=yourpassword

   # Caching Configuration
   CACHING_ENABLED=true        # Set to false to disable database caching

   # Authentication Configuration
   AUTH_ENABLED=true          # Set to false to disable authentication
   API_SECRET=your_secret     # Secret for bearer token authentication
   USE_HASH_VALIDATION=false  # Optional hash-based validation

   # API Configuration
   API_KEY=your_api_key       # Your CurrencyLayer API key
   ```
4. Set up a MySQL database and import the schema from `database/schema.sql`

## API Usage

### Authentication
Include your API token in the request header:

```
Authorization: Bearer your_secret_here
```

If using hash validation (USE_HASH_VALIDATION=true), the token should be:
```
Authorization: Bearer hash_of(api_secret + from_currency + to_currency)
```

### Endpoints

#### Get Current Exchange Rate
```
GET /index.php?from=EUR&to=USD
```
Response:
```json
{
    "success": true,
    "data": {
        "from": "EUR",
        "to": "USD",
        "rate": "1.0876",
        "date": "2025-05-24",
        "timestamp": 1716561600
    }
}
```

#### Get Historical Exchange Rate
```
GET /index.php?from=EUR&to=BTC&date=2024-01-01
```
Response:
```json
{
    "success": true,
    "data": {
        "from": "EUR",
        "to": "BTC",
        "rate": "0.000023876",
        "date": "2024-01-01",
        "timestamp": 1704067200
    }
}
```

### Error Responses

#### Rate Not Found
```json
{
    "error": "Rate not found",
    "message": "Exchange rate not available for the specified currencies and date"
}
```

#### Cache Warning
If caching is enabled but fails:
```json
{
    "success": true,
    "data": { ... },
    "warning": "Rate retrieved but caching failed: Unable to connect to database"
}
```

### Parameters

- `from`: Source currency code (e.g., EUR, USD, BTC)
- `to`: Target currency code
- `date`: Optional. Format: YYYY-MM-DD. Defaults to current date

### Response Format

Success response:
```json
{
    "success": true,
    "data": {
        "from": "EUR",
        "to": "USD",
        "rate": 1.0923,
        "date": "2024-01-01",
        "timestamp": 1705123456
    }
}
```

Error response:
```json
{
    "error": "Error type",
    "message": "Detailed error message"
}
```

### Error Codes

- 400: Bad Request (invalid parameters)
- 401: Unauthorized (invalid authentication)
- 404: Rate Not Found
- 500: Internal Server Error

## Caching

The API automatically caches exchange rates for common currency pairs:
- EUR
- USD
- BTC
- GBP

Cached rates are stored in the database and reused for the same date to minimize external API calls.

## Error Codes

The API returns standardized error codes that can be mapped to your application's exception system:

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `INVALID_PARAMETERS` | 400 | Missing or invalid request parameters |
| `UNAUTHORIZED` | 401 | Invalid or missing authentication token |
| `INVALID_DATE` | 400 | Date format invalid or in future |
| `DATE_OUT_OF_RANGE` | 400 | Date before provider's earliest available date |
| `RATE_LIMIT_EXCEEDED` | 429 | Provider's rate limit exceeded |
| `API_ERROR` | 500 | Provider API error |
| `NETWORK_ERROR` | 503 | Connection to provider failed |
| `RATE_NOT_FOUND` | 404 | Rate not available for requested pair |

## Symfony Integration

Here's an example of how to integrate this API with your Symfony application:

```php
<?php

namespace App\Service\ExchangeRates;

use Mikamatto\BasikSuite\MoneyBundle\Entity\External\Currency;
use Mikamatto\BasikSuite\MoneyBundle\Exception\ExchangeRatesException;
use Mikamatto\BasikSuite\MoneyBundle\Contracts\ExchangeRatesInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class ApiExchangeRates implements ExchangeRatesInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiUrl,
        private readonly string $apiToken,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function getExchangeRate(?Currency $sourceCurrency, ?Currency $targetCurrency, ?\DateTimeInterface $date = null): float
    {
        try {
            $response = $this->httpClient->request('GET', $this->apiUrl . '/rate', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiToken,
                ],
                'query' => [
                    'from' => $sourceCurrency?->getIsoCode(),
                    'to' => $targetCurrency?->getIsoCode(),
                    'date' => $date?->format('Y-m-d'),
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                throw match($data['error']) {
                    'RATE_LIMIT_EXCEEDED' => ExchangeRatesException::rateLimitExceeded(),
                    'DATE_OUT_OF_RANGE' => ExchangeRatesException::dateOutOfRange($date),
                    'INVALID_PARAMETERS' => ExchangeRatesException::unsupportedCurrency(
                        sprintf('%s to %s', $sourceCurrency?->getIsoCode(), $targetCurrency?->getIsoCode())
                    ),
                    default => ExchangeRatesException::apiError($data['message'] ?? 'Unknown error'),
                };
            }

            return $data['rate'];
        } catch (\Throwable $e) {
            $this->logger?->error('Currency conversion error', [
                'error' => $e->getMessage(),
                'source' => $sourceCurrency?->getIsoCode(),
                'target' => $targetCurrency?->getIsoCode(),
                'date' => $date?->format('Y-m-d'),
            ]);

            if ($e instanceof ExchangeRatesException) {
                throw $e;
            }

            throw ExchangeRatesException::networkError($e);
        }
    }

    public function convert(float $amount, ?Currency $sourceCurrency = null, ?Currency $targetCurrency = null, ?\DateTimeInterface $date = null): float
    {
        $rate = $this->getExchangeRate($sourceCurrency, $targetCurrency, $date);
        return $amount * $rate;
    }
}
```

And register it in your service configuration:

```yaml
# config/services.yaml
services:
    App\Service\ExchangeRates\ApiExchangeRates:
        arguments:
            $apiUrl: '%env(exchangerates_API_URL)%'
            $apiToken: '%env(exchangerates_API_TOKEN)%'
```

This implementation:
1. Uses Symfony's HttpClient for requests
2. Maps API error codes to your exceptions
3. Handles logging via PSR Logger
4. Implements conversion locally
5. Supports null currencies (defaults)
