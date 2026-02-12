---
name: stock-api-integration
description: Guidelines for integrating with stock market data APIs including rate limiting, caching, and data validation. Use when working with external stock data sources.
---

# Stock API Integration

## When to use this
Use this skill when you need to:
- Fetch stock market data from external APIs.
- Implement caching for API responses.
- Handle API rate limits and errors.
- Validate data received from external sources.

## Recommended Free APIs

### Alpha Vantage
- **Limit**: 25 requests/day (free tier)
- **Best for**: Historical data, technical indicators
- **Endpoint**: `https://www.alphavantage.co/query`

```php
$url = sprintf(
    'https://www.alphavantage.co/query?function=TIME_SERIES_DAILY&symbol=%s&apikey=%s',
    urlencode($symbol),
    API_KEY
);
```

### Yahoo Finance (Unofficial)
- **Limit**: Rate limited, no official quotas
- **Best for**: Real-time quotes, historical data
- **Note**: No official API, use cautiously

### Twelve Data
- **Limit**: 800 requests/day (free tier)
- **Best for**: Real-time quotes, forex, crypto
- **Endpoint**: `https://api.twelvedata.com`

## API Client Pattern

```php
class StockApiClient
{
    private const RATE_LIMIT_DELAY = 15; // seconds between calls
    private float $lastCallTime = 0;
    private StockDataCache $cache;

    public function __construct(StockDataCache $cache)
    {
        $this->cache = $cache;
    }

    public function getStockQuote(string $symbol): ?array
    {
        // Check cache first
        $cacheKey = "quote:{$symbol}";
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Enforce rate limit
        $this->enforceRateLimit();

        // Make API call
        try {
            $data = $this->fetchFromApi($symbol);

            // Validate response
            if ($this->validateQuoteData($data)) {
                $this->cache->set($cacheKey, $data, 300); // 5 min cache
                return $data;
            }

            return null;
        } catch (Exception $e) {
            error_log("API error for {$symbol}: " . $e->getMessage());
            return null;
        }
    }

    private function enforceRateLimit(): void
    {
        $elapsed = microtime(true) - $this->lastCallTime;
        if ($elapsed < self::RATE_LIMIT_DELAY) {
            $sleepTime = (int)(( self::RATE_LIMIT_DELAY - $elapsed) * 1000000);
            usleep($sleepTime);
        }
        $this->lastCallTime = microtime(true);
    }

    private function fetchFromApi(string $symbol): array
    {
        $url = $this->buildApiUrl($symbol);

        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'Stock Picker App/1.0'
            ]
        ]);

        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            throw new RuntimeException("Failed to fetch data");
        }

        return json_decode($response, true);
    }

    private function validateQuoteData(array $data): bool
    {
        return isset($data['symbol'])
            && isset($data['price'])
            && is_numeric($data['price'])
            && $data['price'] > 0;
    }
}
```

## Rate Limiting Strategy

### Track API Usage
```php
class ApiUsageTracker
{
    private PDO $db;

    public function recordApiCall(string $endpoint): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO api_usage (endpoint, called_at) VALUES (?, ?)'
        );
        $stmt->execute([$endpoint, time()]);
    }

    public function getCallsToday(string $endpoint): int
    {
        $todayStart = strtotime('today');
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM api_usage
             WHERE endpoint = ? AND called_at >= ?'
        );
        $stmt->execute([$endpoint, $todayStart]);
        return (int)$stmt->fetchColumn();
    }

    public function canMakeCall(string $endpoint, int $dailyLimit): bool
    {
        return $this->getCallsToday($endpoint) < $dailyLimit;
    }
}
```

## Caching Strategy

### Multi-tier Caching
```php
class StockDataCache
{
    private string $cacheDir;

    // Hot data: Current/recent prices (5 min TTL)
    private const HOT_TTL = 300;

    // Warm data: Today's data (30 min TTL)
    private const WARM_TTL = 1800;

    // Cold data: Historical data (24 hour TTL)
    private const COLD_TTL = 86400;

    public function get(string $key): mixed
    {
        $file = $this->getCacheFile($key);

        if (!file_exists($file)) {
            return null;
        }

        $data = unserialize(file_get_contents($file));

        if ($data['expires'] < time()) {
            unlink($file);
            return null;
        }

        return $data['value'];
    }

    public function set(string $key, mixed $value, int $ttl = self::WARM_TTL): void
    {
        $data = [
            'value' => $value,
            'expires' => time() + $ttl
        ];

        $file = $this->getCacheFile($key);
        file_put_contents($file, serialize($data));
    }

    private function getCacheFile(string $key): string
    {
        $hash = md5($key);
        return $this->cacheDir . '/' . $hash . '.cache';
    }
}
```

## Data Normalization

```php
class StockDataNormalizer
{
    public function normalizeQuote(array $apiData): array
    {
        return [
            'symbol' => $this->normalizeSymbol($apiData['symbol'] ?? ''),
            'price' => $this->normalizePrice($apiData['price'] ?? 0),
            'volume' => $this->normalizeVolume($apiData['volume'] ?? 0),
            'change' => $this->normalizePrice($apiData['change'] ?? 0),
            'change_percent' => round((float)($apiData['change_percent'] ?? 0), 2),
            'timestamp' => $this->normalizeTimestamp($apiData['timestamp'] ?? null)
        ];
    }

    private function normalizeSymbol(string $symbol): string
    {
        return strtoupper(trim($symbol));
    }

    private function normalizePrice(mixed $price): float
    {
        return round((float)$price, 2);
    }

    private function normalizeVolume(mixed $volume): int
    {
        // Remove commas and convert to int
        $cleaned = str_replace(',', '', (string)$volume);
        return (int)$cleaned;
    }

    private function normalizeTimestamp(?string $timestamp): int
    {
        if ($timestamp === null) {
            return time();
        }

        try {
            return (new DateTime($timestamp))->getTimestamp();
        } catch (Exception $e) {
            return time();
        }
    }
}
```

## Error Handling

### API Error Codes
```php
class ApiException extends Exception
{
    public const RATE_LIMIT_EXCEEDED = 1001;
    public const INVALID_SYMBOL = 1002;
    public const API_KEY_INVALID = 1003;
    public const SERVICE_UNAVAILABLE = 1004;

    public function __construct(string $message, int $code = 0)
    {
        parent::__construct($message, $code);
        error_log("API Error [{$code}]: {$message}");
    }
}
```

### Graceful Degradation
```php
class StockService
{
    private array $apiClients; // Multiple API sources

    public function getQuote(string $symbol): ?array
    {
        foreach ($this->apiClients as $client) {
            try {
                $quote = $client->getStockQuote($symbol);
                if ($quote !== null) {
                    return $quote;
                }
            } catch (ApiException $e) {
                // Try next source
                continue;
            }
        }

        // All sources failed
        return null;
    }
}
```

## Data Validation

```php
class StockDataValidator
{
    public function validateSymbol(string $symbol): bool
    {
        // 1-5 uppercase letters, optionally followed by a dot and 1-2 letters
        return preg_match('/^[A-Z]{1,5}(\.[A-Z]{1,2})?$/', $symbol) === 1;
    }

    public function validatePrice(float $price): bool
    {
        return $price > 0 && $price < 1000000;
    }

    public function validateOhlcv(array $data): bool
    {
        if (!isset($data['open'], $data['high'], $data['low'], $data['close'], $data['volume'])) {
            return false;
        }

        $o = (float)$data['open'];
        $h = (float)$data['high'];
        $l = (float)$data['low'];
        $c = (float)$data['close'];
        $v = (int)$data['volume'];

        return $o > 0
            && $h >= max($o, $c)
            && $l <= min($o, $c)
            && $c > 0
            && $v >= 0;
    }

    public function detectAnomaly(float $price, array $recentPrices): bool
    {
        if (count($recentPrices) < 5) {
            return false;
        }

        $avg = array_sum($recentPrices) / count($recentPrices);
        $deviation = abs($price - $avg) / $avg;

        // Flag if price deviates more than 30%
        return $deviation > 0.30;
    }
}
```

## Usage Example

```php
// Initialize components
$cache = new StockDataCache(__DIR__ . '/../cache');
$apiClient = new StockApiClient($cache);
$normalizer = new StockDataNormalizer();
$validator = new StockDataValidator();
$tracker = new ApiUsageTracker($db);

// Check rate limit
if (!$tracker->canMakeCall('alpha_vantage', 25)) {
    throw new ApiException('Daily API limit reached', ApiException::RATE_LIMIT_EXCEEDED);
}

// Fetch and validate
$rawData = $apiClient->getStockQuote('AAPL');
if ($rawData === null) {
    throw new ApiException('Failed to fetch quote', ApiException::SERVICE_UNAVAILABLE);
}

$normalized = $normalizer->normalizeQuote($rawData);

if (!$validator->validatePrice($normalized['price'])) {
    throw new ApiException('Invalid price data', ApiException::INVALID_SYMBOL);
}

// Track usage
$tracker->recordApiCall('alpha_vantage');

// Use the data
saveToDatabase($normalized);
```

## Best Practices

1. **Always cache aggressively** - External APIs are the bottleneck
2. **Validate everything** - Don't trust API responses blindly
3. **Implement fallbacks** - Use multiple data sources
4. **Track usage** - Monitor API quotas closely
5. **Handle market hours** - Markets are closed on weekends/holidays
6. **Log failures** - Track API errors for debugging
7. **Retry with backoff** - For transient failures
8. **Normalize early** - Convert to standard format ASAP
