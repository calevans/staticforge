---
name: database-schema-reference
description: Complete SQLite database schema reference including tables, indexes, and common query patterns. Use when working with database operations.
---

# Database Schema Reference

This skill documents the complete database schema for the stock-picking application.

## Database Configuration

```php
// config/database.php
return [
    'path' => __DIR__ . '/../data/stocks.db',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_PERSISTENT => true,
    ],
    'pragmas' => [
        'journal_mode' => 'WAL',
        'synchronous' => 'NORMAL',
        'cache_size' => -64000, // 64MB
        'temp_store' => 'MEMORY',
        'foreign_keys' => 'ON',
    ]
];
```

## Schema

### stocks
Core stock information
```sql
CREATE TABLE stocks (
    symbol TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    exchange TEXT,
    sector TEXT,
    industry TEXT,
    market_cap REAL,
    description TEXT,
    last_updated INTEGER NOT NULL,
    created_at INTEGER NOT NULL DEFAULT (strftime('%s', 'now'))
);

CREATE INDEX idx_stocks_sector ON stocks(sector);
CREATE INDEX idx_stocks_exchange ON stocks(exchange);
```

### stock_prices
Historical OHLCV price data
```sql
CREATE TABLE stock_prices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    symbol TEXT NOT NULL,
    date TEXT NOT NULL, -- YYYY-MM-DD format
    open REAL NOT NULL,
    high REAL NOT NULL,
    low REAL NOT NULL,
    close REAL NOT NULL,
    volume INTEGER NOT NULL,
    adjusted_close REAL,
    created_at INTEGER NOT NULL DEFAULT (strftime('%s', 'now')),
    FOREIGN KEY (symbol) REFERENCES stocks(symbol) ON DELETE CASCADE,
    UNIQUE(symbol, date)
);

CREATE INDEX idx_prices_symbol_date ON stock_prices(symbol, date DESC);
CREATE INDEX idx_prices_date ON stock_prices(date);
-- Covering index for common queries
CREATE INDEX idx_prices_cover ON stock_prices(symbol, date, close);
```

### technical_indicators
Calculated technical indicators (cached)
```sql
CREATE TABLE technical_indicators (
    symbol TEXT NOT NULL,
    date TEXT NOT NULL,
    sma_20 REAL,
    sma_50 REAL,
    sma_200 REAL,
    ema_12 REAL,
    ema_26 REAL,
    rsi REAL,
    macd REAL,
    macd_signal REAL,
    bollinger_upper REAL,
    bollinger_lower REAL,
    calculated_at INTEGER NOT NULL DEFAULT (strftime('%s', 'now')),
    PRIMARY KEY (symbol, date),
    FOREIGN KEY (symbol) REFERENCES stocks(symbol) ON DELETE CASCADE
);

CREATE INDEX idx_indicators_date ON technical_indicators(date);
```

### users
User accounts
```sql
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    email TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    created_at INTEGER NOT NULL DEFAULT (strftime('%s', 'now')),
    last_login INTEGER,
    is_active INTEGER NOT NULL DEFAULT 1
);

CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_username ON users(username);
```

### watchlists
User stock watchlists
```sql
CREATE TABLE watchlists (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    symbol TEXT NOT NULL,
    added_at INTEGER NOT NULL DEFAULT (strftime('%s', 'now')),
    notes TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (symbol) REFERENCES stocks(symbol) ON DELETE CASCADE,
    UNIQUE(user_id, symbol)
);

CREATE INDEX idx_watchlist_user ON watchlists(user_id);
CREATE INDEX idx_watchlist_symbol ON watchlists(symbol);
```

### portfolios
User stock portfolios
```sql
CREATE TABLE portfolios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    symbol TEXT NOT NULL,
    shares REAL NOT NULL CHECK(shares > 0),
    purchase_price REAL NOT NULL CHECK(purchase_price > 0),
    purchase_date TEXT NOT NULL,
    notes TEXT,
    created_at INTEGER NOT NULL DEFAULT (strftime('%s', 'now')),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (symbol) REFERENCES stocks(symbol) ON DELETE CASCADE
);

CREATE INDEX idx_portfolio_user ON portfolios(user_id);
CREATE INDEX idx_portfolio_symbol ON portfolios(symbol);
```

### analysis_results
Stored analysis results
```sql
CREATE TABLE analysis_results (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    symbol TEXT NOT NULL,
    analysis_type TEXT NOT NULL,
    score REAL,
    recommendation TEXT,
    details TEXT, -- JSON blob
    analyzed_at INTEGER NOT NULL DEFAULT (strftime('%s', 'now')),
    FOREIGN KEY (symbol) REFERENCES stocks(symbol) ON DELETE CASCADE
);

CREATE INDEX idx_analysis_symbol ON analysis_results(symbol);
CREATE INDEX idx_analysis_date ON analysis_results(analyzed_at);
```

### api_usage
Track external API calls
```sql
CREATE TABLE api_usage (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    endpoint TEXT NOT NULL,
    called_at INTEGER NOT NULL DEFAULT (strftime('%s', 'now')),
    response_time INTEGER, -- milliseconds
    success INTEGER NOT NULL DEFAULT 1
);

CREATE INDEX idx_api_usage_endpoint ON api_usage(endpoint, called_at);
CREATE INDEX idx_api_usage_date ON api_usage(called_at);
```

### sessions
User sessions (if not using file-based)
```sql
CREATE TABLE sessions (
    id TEXT PRIMARY KEY,
    user_id INTEGER,
    data TEXT,
    expires_at INTEGER NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_sessions_expires ON sessions(expires_at);
CREATE INDEX idx_sessions_user ON sessions(user_id);

-- Cleanup expired sessions
-- Run periodically: DELETE FROM sessions WHERE expires_at < strftime('%s', 'now');
```

## Common Query Patterns

### Get Stock with Latest Price
```php
$stmt = $db->prepare("
    SELECT
        s.*,
        p.close as current_price,
        p.date as price_date,
        p.volume
    FROM stocks s
    LEFT JOIN stock_prices p ON s.symbol = p.symbol
    WHERE s.symbol = ?
    ORDER BY p.date DESC
    LIMIT 1
");
$stmt->execute([$symbol]);
```

### Get Historical Prices
```php
$stmt = $db->prepare("
    SELECT date, open, high, low, close, volume
    FROM stock_prices
    WHERE symbol = ?
    AND date >= date('now', '-90 days')
    ORDER BY date ASC
");
$stmt->execute([$symbol]);
```

### Get Portfolio with Current Values
```php
$stmt = $db->prepare("
    SELECT
        p.*,
        s.name,
        sp.close as current_price,
        (p.shares * sp.close) as current_value,
        (p.shares * sp.close) - (p.shares * p.purchase_price) as gain_loss,
        ((sp.close - p.purchase_price) / p.purchase_price * 100) as gain_loss_pct
    FROM portfolios p
    INNER JOIN stocks s ON p.symbol = s.symbol
    LEFT JOIN (
        SELECT symbol, close
        FROM stock_prices
        WHERE (symbol, date) IN (
            SELECT symbol, MAX(date)
            FROM stock_prices
            GROUP BY symbol
        )
    ) sp ON p.symbol = sp.symbol
    WHERE p.user_id = ?
    ORDER BY current_value DESC
");
$stmt->execute([$userId]);
```

### Get Watchlist with Prices
```php
$stmt = $db->prepare("
    SELECT
        w.*,
        s.name,
        s.sector,
        sp.close as current_price,
        sp.date as price_date
    FROM watchlists w
    INNER JOIN stocks s ON w.symbol = s.symbol
    LEFT JOIN (
        SELECT symbol, close, date
        FROM stock_prices
        WHERE (symbol, date) IN (
            SELECT symbol, MAX(date)
            FROM stock_prices
            GROUP BY symbol
        )
    ) sp ON w.symbol = sp.symbol
    WHERE w.user_id = ?
    ORDER BY w.added_at DESC
");
$stmt->execute([$userId]);
```

### Get Top Performers
```php
$stmt = $db->prepare("
    SELECT
        symbol,
        close as current_price,
        LAG(close, 1) OVER (PARTITION BY symbol ORDER BY date) as prev_price,
        ((close - LAG(close, 1) OVER (PARTITION BY symbol ORDER BY date)) /
         LAG(close, 1) OVER (PARTITION BY symbol ORDER BY date) * 100) as change_pct
    FROM stock_prices
    WHERE date = date('now')
    ORDER BY change_pct DESC
    LIMIT 10
");
```

### Search Stocks
```php
$stmt = $db->prepare("
    SELECT symbol, name, sector, exchange
    FROM stocks
    WHERE symbol LIKE ? OR name LIKE ?
    ORDER BY
        CASE
            WHEN symbol LIKE ? THEN 1
            WHEN name LIKE ? THEN 2
            ELSE 3
        END,
        symbol ASC
    LIMIT 20
");
$search = "%{$query}%";
$exact = "{$query}%";
$stmt->execute([$search, $search, $exact, $exact]);
```

### Calculate Moving Averages (Window Functions)
```php
$stmt = $db->prepare("
    SELECT
        date,
        close,
        AVG(close) OVER (
            ORDER BY date
            ROWS BETWEEN 19 PRECEDING AND CURRENT ROW
        ) as sma_20,
        AVG(close) OVER (
            ORDER BY date
            ROWS BETWEEN 49 PRECEDING AND CURRENT ROW
        ) as sma_50
    FROM stock_prices
    WHERE symbol = ?
    ORDER BY date DESC
    LIMIT 100
");
$stmt->execute([$symbol]);
```

## Maintenance Queries

### Vacuum (run periodically)
```sql
VACUUM;
ANALYZE;
```

### Delete Old Price Data
```sql
DELETE FROM stock_prices
WHERE date < date('now', '-2 years');
```

### Clean Expired Sessions
```sql
DELETE FROM sessions
WHERE expires_at < strftime('%s', 'now');
```

### Update Stock Last Updated
```sql
UPDATE stocks
SET last_updated = strftime('%s', 'now')
WHERE symbol = ?;
```

## Data Integrity

### Constraints
- All foreign keys have `ON DELETE CASCADE`
- Prices have `CHECK` constraints for valid OHLCV
- Unique constraints prevent duplicates
- NOT NULL on critical fields

### Transactions
Always use transactions for multi-step operations:
```php
$db->beginTransaction();
try {
    // Multiple operations
    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    throw $e;
}
```

## Performance Tips

1. **Use covering indexes** for frequently joined queries
2. **Batch inserts** for bulk data
3. **EXPLAIN QUERY PLAN** to check query performance
