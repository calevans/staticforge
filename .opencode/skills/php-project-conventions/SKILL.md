---
name: php-project-conventions
description: Project-specific coding standards, file structure, and conventions for the stock-picking PHP application. Use when creating new files or organizing code.
---

# PHP Project Conventions

This skill contains the coding standards and organizational conventions for this stock-picking web application.

## Project Structure

```
/
├── api/                    # API endpoints
│   ├── stocks.php
│   ├── watchlist.php
│   └── portfolio.php
├── classes/                # PHP classes
│   ├── Database.php
│   ├── StockAnalyzer.php
│   └── Portfolio.php
├── public/                 # Web root
│   ├── index.php
│   ├── css/
│   ├── js/
│   └── assets/
├── config/                 # Configuration
│   ├── config.php
│   └── database.php
├── migrations/             # Database migrations
├── tests/                  # PHPUnit tests
│   ├── Unit/
│   └── Integration/
├── cache/                  # Cache files
├── logs/                   # Application logs
└── data/                   # SQLite database
    └── stocks.db
```

## Coding Standards

### PSR Compliance
- **PSR-1**: Basic coding standard
- **PSR-4**: Autoloading (namespace follows directory structure)
- **PSR-12**: Extended coding style

### Naming Conventions
```php
// Classes: PascalCase
class StockAnalyzer {}

// Methods: camelCase
public function calculateMovingAverage() {}

// Properties: camelCase
private string $apiKey;

// Constants: UPPER_SNAKE_CASE
const MAX_API_CALLS = 100;

// Database tables: snake_case
CREATE TABLE stock_prices (...);
```

### File Naming
- PHP classes: `ClassName.php`
- API endpoints: `resource-name.php` (kebab-case)
- Config files: `descriptive-name.php`
- Migrations: `YYYYMMDD_HHMMSS_description.sql`

### Type Declarations
Always use type hints:
```php
function getStockPrice(string $symbol): ?float
{
    // Implementation
}
```

### Error Handling Pattern
```php
try {
    $result = $operation->execute();
    return $result;
} catch (InvalidArgumentException $e) {
    error_log("Validation error: " . $e->getMessage());
    return null;
} catch (DatabaseException $e) {
    error_log("Database error: " . $e->getMessage());
    throw $e;
}
```

### Database Access Pattern
Always use prepared statements:
```php
$stmt = $db->prepare('SELECT * FROM stocks WHERE symbol = ?');
$stmt->execute([$symbol]);
$stock = $stmt->fetch(PDO::FETCH_ASSOC);
```

## API Response Format

### Success Response
```json
{
    "success": true,
    "data": { ... },
    "timestamp": "2026-01-28T12:00:00Z"
}
```

### Error Response
```json
{
    "success": false,
    "error": {
        "code": "VALIDATION_ERROR",
        "message": "Stock symbol is required",
        "field": "symbol"
    },
    "timestamp": "2026-01-28T12:00:00Z"
}
```

## Frontend Standards

### JavaScript
- Use `const`/`let`, never `var`
- Cache jQuery selectors
- Use event delegation for dynamic content
- Prefix jQuery objects with `$`: `const $table = $('#stock-table')`

### CSS
- Use BEM naming: `.block__element--modifier`
- Mobile-first approach
- Group related properties
- Use CSS custom properties for theming

### HTML
- Semantic HTML5 elements
- Use `data-*` attributes for JS hooks
- Accessibility: proper ARIA labels

## Documentation
Every public method should have a docblock:
```php
/**
 * Calculate the simple moving average
 *
 * @param array $prices Array of prices
 * @param int $period Number of periods
 * @return float The moving average
 * @throws InvalidArgumentException If insufficient data
 */
```

## Git Commit Messages
Format: `type(scope): message`

Types:
- `feat`: New feature
- `fix`: Bug fix
- `refactor`: Code refactoring
- `test`: Adding tests
- `docs`: Documentation
- `style`: Formatting changes
- `perf`: Performance improvement

Example: `feat(api): add stock search endpoint`

## Configuration Management

### Environment-specific configs
```php
// config/config.php
return [
    'environment' => getenv('APP_ENV') ?: 'development',
    'debug' => getenv('APP_DEBUG') === 'true',
    'api_key' => getenv('STOCK_API_KEY'),
];
```

### Never commit sensitive data
- Use `.env` files (git-ignored)
- Provide `.env.example` template

## Security Checklist
Before committing code:
- [ ] All database queries use prepared statements
- [ ] All output is escaped (htmlspecialchars)
- [ ] CSRF tokens on state-changing operations
- [ ] Input validation on all endpoints
- [ ] Error messages don't expose sensitive info
- [ ] No API keys or passwords in code
