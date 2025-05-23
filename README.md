# Currency Converter API

A lightweight PHP API for currency exchange rates with caching capabilities. Designed to work as a middleware between your application and exchange rate providers.

## Features

- Real-time exchange rates from multiple providers
- Historical exchange rates with date support
- Caching of frequently used currency pairs
- Bearer token authentication
- Standardized error responses
- Provider-agnostic interface

## Supported Providers

### CurrencyLayer
- Supports historical rates back to 1999
- Supports major currencies (USD, EUR, GBP, JPY, CHF, AUD, CAD, BTC)
- Requires API key from [currencylayer.com](https://currencylayer.com)

### ExchangeRates API
- Supports major currencies and cryptocurrencies
- Requires API key from [exchangeratesapi.io](https://exchangeratesapi.io)

## Setup

1. **Clone the repository**
```bash
git clone <repository-url>
cd currency_converter
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
CREATE DATABASE currency;
exit;
```

4. **Import database schema**
```bash
mysql -u root -p currency < database/schema.sql
```

## Installation

1. Clone the repository
2. Run `composer install`
3. Copy `.env.example` to `.env` and configure your settings:
   ```env
   # Database Configuration
   DB_HOST=localhost
   DB_NAME=currency
   DB_USER=root
   DB_PASS=yourpassword

   # API Authentication
   API_SECRET=your_secret_here
   USE_HASH_VALIDATION=false

   # Provider Configuration
   API_KEY=your_api_key
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

#### Get Historical Exchange Rate
```
GET /index.php?from=EUR&to=BTC&date=2024-01-01
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

namespace App\Service\CurrencyConverter;

use Mikamatto\BasikSuite\MoneyBundle\Entity\External\Currency;
use Mikamatto\BasikSuite\MoneyBundle\Exception\CurrencyConverterException;
use Mikamatto\BasikSuite\MoneyBundle\Contracts\CurrencyConverterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class ApiCurrencyConverter implements CurrencyConverterInterface
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
                    'RATE_LIMIT_EXCEEDED' => CurrencyConverterException::rateLimitExceeded(),
                    'DATE_OUT_OF_RANGE' => CurrencyConverterException::dateOutOfRange($date),
                    'INVALID_PARAMETERS' => CurrencyConverterException::unsupportedCurrency(
                        sprintf('%s to %s', $sourceCurrency?->getIsoCode(), $targetCurrency?->getIsoCode())
                    ),
                    default => CurrencyConverterException::apiError($data['message'] ?? 'Unknown error'),
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

            if ($e instanceof CurrencyConverterException) {
                throw $e;
            }

            throw CurrencyConverterException::networkError($e);
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
    App\Service\CurrencyConverter\ApiCurrencyConverter:
        arguments:
            $apiUrl: '%env(CURRENCY_API_URL)%'
            $apiToken: '%env(CURRENCY_API_TOKEN)%'
```

This implementation:
1. Uses Symfony's HttpClient for requests
2. Maps API error codes to your exceptions
3. Handles logging via PSR Logger
4. Implements conversion locally
5. Supports null currencies (defaults)
