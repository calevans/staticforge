---
name: financial-calculations
description: Stock market technical indicators and financial calculation formulas. Use when implementing analysis features or calculating stock metrics.
---

# Financial Calculations

This skill provides formulas and implementations for common stock market technical indicators and financial calculations.

## Price-Based Indicators

### Simple Moving Average (SMA)
Average price over N periods
```php
function calculateSMA(array $prices, int $period): ?float
{
    if (count($prices) < $period) {
        return null;
    }

    $subset = array_slice($prices, -$period);
    return array_sum($subset) / $period;
}

// Usage
$sma20 = calculateSMA($closePrices, 20);
$sma50 = calculateSMA($closePrices, 50);
sma200 = calculateSMA($closePrices, 200);
```

### Exponential Moving Average (EMA)
Weighted average giving more importance to recent prices
```php
function calculateEMA(array $prices, int $period): ?float
{
    if (count($prices) < $period) {
        return null;
    }

    $multiplier = 2 / ($period + 1);

    // Start with SMA
    $sma = array_sum(array_slice($prices, 0, $period)) / $period;
    $ema = $sma;

    // Calculate EMA for remaining prices
    for ($i = $period; $i < count($prices); $i++) {
        $ema = ($prices[$i] * $multiplier) + ($ema * (1 - $multiplier));
    }

    return $ema;
}

// Common EMAs
$ema12 = calculateEMA($closePrices, 12);
$ema26 = calculateEMA($closePrices, 26);
```

### Bollinger Bands
Volatility bands around a moving average
```php
function calculateBollingerBands(array $prices, int $period = 20, float $stdDevs = 2): ?array
{
    if (count($prices) < $period) {
        return null;
    }

    $subset = array_slice($prices, -$period);
    $sma = array_sum($subset) / $period;

    // Calculate standard deviation
    $variance = 0;
    foreach ($subset as $price) {
        $variance += pow($price - $sma, 2);
    }
    $stdDev = sqrt($variance / $period);

    return [
        'middle' => $sma,
        'upper' => $sma + ($stdDevs * $stdDev),
        'lower' => $sma - ($stdDevs * $stdDev),
        'bandwidth' => (($stdDevs * $stdDev * 2) / $sma) * 100
    ];
}
```

## Momentum Indicators

### Relative Strength Index (RSI)
Measures speed and magnitude of price changes (0-100)
```php
function calculateRSI(array $prices, int $period = 14): ?float
{
    if (count($prices) < $period + 1) {
        return null;
    }

    $gains = [];
    $losses = [];

    // Calculate gains and losses
    for ($i = 1; $i < count($prices); $i++) {
        $change = $prices[$i] - $prices[$i - 1];
        $gains[] = max(0, $change);
        $losses[] = max(0, -$change);
    }

    // Average gains and losses over period
    $avgGain = array_sum(array_slice($gains, -$period)) / $period;
    $avgLoss = array_sum(array_slice($losses, -$period)) / $period;

    if ($avgLoss == 0) {
        return 100;
    }

    $rs = $avgGain / $avgLoss;
    $rsi = 100 - (100 / (1 + $rs));

    return $rsi;
}

// Interpretation
// RSI > 70: Overbought
// RSI < 30: Oversold
```

### MACD (Moving Average Convergence Divergence)
Trend-following momentum indicator
```php
function calculateMACD(array $prices): ?array
{
    $ema12 = calculateEMA($prices, 12);
    $ema26 = calculateEMA($prices, 26);

    if ($ema12 === null || $ema26 === null) {
        return null;
    }

    $macd = $ema12 - $ema26;

    // Signal line is 9-day EMA of MACD
    // (simplified - would need historical MACD values)

    return [
        'macd' => $macd,
        'ema12' => $ema12,
        'ema26' => $ema26
    ];
}

// Interpretation
// MACD > Signal: Bullish
// MACD < Signal: Bearish
```

### Rate of Change (ROC)
Percentage price change over N periods
```php
function calculateROC(array $prices, int $period = 12): ?float
{
    if (count($prices) < $period + 1) {
        return null;
    }

    $current = end($prices);
    $previous = $prices[count($prices) - $period - 1];

    if ($previous == 0) {
        return null;
    }

    return (($current - $previous) / $previous) * 100;
}
```

## Volume Indicators

### On-Balance Volume (OBV)
Cumulative volume indicator
```php
function calculateOBV(array $prices, array $volumes): ?int
{
    if (count($prices) !== count($volumes) || count($prices) < 2) {
        return null;
    }

    $obv = 0;

    for ($i = 1; $i < count($prices); $i++) {
        if ($prices[$i] > $prices[$i - 1]) {
            $obv += $volumes[$i];
        } elseif ($prices[$i] < $prices[$i - 1]) {
            $obv -= $volumes[$i];
        }
    }

    return $obv;
}
```

### Volume Weighted Average Price (VWAP)
Average price weighted by volume
```php
function calculateVWAP(array $highs, array $lows, array $closes, array $volumes): ?float
{
    if (count($highs) !== count($lows) ||
        count($highs) !== count($closes) ||
        count($highs) !== count($volumes)) {
        return null;
    }

    $totalPriceVolume = 0;
    $totalVolume = 0;

    for ($i = 0; $i < count($highs); $i++) {
        $typicalPrice = ($highs[$i] + $lows[$i] + $closes[$i]) / 3;
        $totalPriceVolume += $typicalPrice * $volumes[$i];
        $totalVolume += $volumes[$i];
    }

    if ($totalVolume == 0) {
        return null;
    }

    return $totalPriceVolume / $totalVolume;
}
```

## Volatility Indicators

### Average True Range (ATR)
Measure of volatility
```php
function calculateATR(array $highs, array $lows, array $closes, int $period = 14): ?float
{
    if (count($highs) < $period + 1) {
        return null;
    }

    $trueRanges = [];

    for ($i = 1; $i < count($highs); $i++) {
        $tr = max(
            $highs[$i] - $lows[$i],
            abs($highs[$i] - $closes[$i - 1]),
            abs($lows[$i] - $closes[$i - 1])
        );
        $trueRanges[] = $tr;
    }

    return array_sum(array_slice($trueRanges, -$period)) / $period;
}
```

### Standard Deviation
Price volatility measure
```php
function calculateStdDev(array $prices, int $period = 20): ?float
{
    if (count($prices) < $period) {
        return null;
    }

    $subset = array_slice($prices, -$period);
    $mean = array_sum($subset) / $period;

    $variance = 0;
    foreach ($subset as $price) {
        $variance += pow($price - $mean, 2);
    }

    return sqrt($variance / $period);
}
```

## Performance Metrics

### Return on Investment (ROI)
```php
function calculateROI(float $purchasePrice, float $currentPrice, float $shares = 1): array
{
    $invested = $purchasePrice * $shares;
    $current = $currentPrice * $shares;
    $profit = $current - $invested;
    $roiPercent = ($profit / $invested) * 100;

    return [
        'invested' => $invested,
        'current_value' => $current,
        'profit' => $profit,
        'roi_percent' => $roiPercent
    ];
}
```

### Sharpe Ratio
Risk-adjusted return (simplified)
```php
function calculateSharpeRatio(array $returns, float $riskFreeRate = 0.02): ?float
{
    if (count($returns) < 2) {
        return null;
    }

    $avgReturn = array_sum($returns) / count($returns);

    // Calculate standard deviation
    $variance = 0;
    foreach ($returns as $return) {
        $variance += pow($return - $avgReturn, 2);
    }
    $stdDev = sqrt($variance / count($returns));

    if ($stdDev == 0) {
        return null;
    }

    return ($avgReturn - $riskFreeRate) / $stdDev;
}
```

### Beta (Market Correlation)
```php
function calculateBeta(array $stockReturns, array $marketReturns): ?float
{
    if (count($stockReturns) !== count($marketReturns) || count($stockReturns) < 2) {
        return null;
    }

    $stockMean = array_sum($stockReturns) / count($stockReturns);
    $marketMean = array_sum($marketReturns) / count($marketReturns);

    $covariance = 0;
    $marketVariance = 0;

    for ($i = 0; $i < count($stockReturns); $i++) {
        $covariance += ($stockReturns[$i] - $stockMean) * ($marketReturns[$i] - $marketMean);
        $marketVariance += pow($marketReturns[$i] - $marketMean, 2);
    }

    if ($marketVariance == 0) {
        return null;
    }

    return $covariance / $marketVariance;
}
```

## Support/Resistance Levels

### Find Support Levels
```php
function findSupportLevels(array $prices, int $lookback = 20, float $tolerance = 0.02): array
{
    $levels = [];

    for ($i = $lookback; $i < count($prices) - $lookback; $i++) {
        $isSupport = true;

        // Check if this is a local minimum
        for ($j = $i - $lookback; $j <= $i + $lookback; $j++) {
            if ($j !== $i && $prices[$j] < $prices[$i]) {
                $isSupport = false;
                break;
            }
        }

        if ($isSupport) {
            $levels[] = $prices[$i];
        }
    }

    // Cluster similar levels
    return clusterLevels($levels, $tolerance);
}

function clusterLevels(array $levels, float $tolerance): array
{
    if (empty($levels)) {
        return [];
    }

    sort($levels);
    $clustered = [];
    $current = [$levels[0]];

    for ($i = 1; $i < count($levels); $i++) {
        $diff = abs($levels[$i] - $levels[$i - 1]) / $levels[$i - 1];

        if ($diff <= $tolerance) {
            $current[] = $levels[$i];
        } else {
            $clustered[] = array_sum($current) / count($current);
            $current = [$levels[$i]];
        }
    }

    $clustered[] = array_sum($current) / count($current);
    return $clustered;
}
