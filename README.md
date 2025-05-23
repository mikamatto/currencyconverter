# Currency Exchange Rate API

A lightweight PHP API for currency conversion rates with caching capabilities. The API fetches real-time exchange rates from an external provider and caches frequently used currency pairs.

## Features

- Real-time currency conversion rates
- Historical rates with date-based queries
- Local caching for common currency pairs (EUR, USD, BTC, GBP)
- API authentication with bearer token
- Optional hash-based validation for enhanced security
- Pluggable exchange rate providers
  - ExchangeRatesAPI provider included
  - Fixer.io provider included
  - Easy to add new providers

## Setup

1. **Clone the repository**
```bash
git clone <repository-url>
cd currencies
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
