# Multi-Currency Support

## Overview

The multi-currency system provides exchange rate management and currency conversion for organizations operating across international marketplaces. Exchange rates are synced from an external provider (configurable), stored historically in the database, and used for transaction-time conversion. The system supports 10 currencies and includes a settings UI for viewing rates, triggering syncs, and converting amounts.

---

## Architecture

### Key Files

| File | Purpose |
|------|---------|
| `app/Models/System/ExchangeRate.php` | Exchange rate record with currency pair lookup and inverse fallback |
| `app/Services/Currency/CurrencyService.php` | Conversion, formatting, and supported currency registry |
| `app/Services/Currency/ExchangeRateProvider.php` | Fetches rates from external API and persists to database |
| `app/Jobs/Currency/SyncExchangeRates.php` | Queued job for scheduled rate sync (3 retries, 5min backoff) |
| `app/Http/Controllers/Settings/CurrencyController.php` | Settings page, sync trigger, and conversion endpoint |

---

## Data Model

### `exchange_rates`

| Column | Type | Description |
|--------|------|-------------|
| `id` | integer | Primary key |
| `base_currency` | string | Source currency code (e.g., `USD`) |
| `target_currency` | string | Target currency code (e.g., `EUR`) |
| `rate` | decimal(16,8) | Conversion rate (1 base = rate target) |
| `source` | string | Rate source identifier (e.g., `api`) |
| `fetched_at` | datetime | When the rate was fetched |

Rates are stored as historical records -- each sync creates new rows rather than updating existing ones. This enables point-in-time lookups for historical transactions.

### Supported Currencies

| Code | Label | Symbol |
|------|-------|--------|
| USD | US Dollar | $ |
| EUR | Euro | EUR |
| GBP | British Pound | GBP |
| CAD | Canadian Dollar | CA$ |
| AUD | Australian Dollar | A$ |
| JPY | Japanese Yen | JPY |
| CHF | Swiss Franc | CHF |
| CNY | Chinese Yuan | CNY |
| INR | Indian Rupee | INR |
| MXN | Mexican Peso | MX$ |

---

## Key Flows

### Exchange Rate Sync

```
Scheduler dispatches SyncExchangeRates job
    |
    v
ExchangeRateProvider::syncRates('USD')
    |
    ExchangeRateProvider::fetchRates('USD')
        |  Calls external API (placeholder uses hardcoded sample rates)
        |  Returns array: {'EUR' => 0.92150000, 'GBP' => 0.79230000, ...}
    |
    For each rate:
        ExchangeRate::create({base, target, rate, source: 'api', fetched_at: now})
    |
    v
Rates stored as new historical records
```

The `SyncExchangeRates` job runs on the `default` queue with 3 retries and a 5-minute backoff. Timeout is 60 seconds.

For non-USD base currencies, `ExchangeRateProvider::fetchRates()` converts from USD rates using cross-rate calculation: `rate_in_new_base = usd_rate / base_to_usd_rate`.

### Currency Conversion

`CurrencyService::convert(amount, from, to, date?)`

1. If `from === to`, return the amount unchanged
2. Look up rate via `ExchangeRate::getRate(from, to, date)`
3. If no direct rate exists, try the inverse pair: `1 / getRate(to, from)`
4. Multiply amount by rate, round to 2 decimal places
5. Throw `RuntimeException` if no rate is found in either direction

The optional `date` parameter enables historical conversion -- it finds the most recent rate at or before the given date.

### Rate Lookup with Inverse Fallback

`ExchangeRate::getRate(from, to, date?)`

```
1. Query exchange_rates WHERE base = from AND target = to
   Order by fetched_at DESC (or <= date if provided)
   Return first match

2. If no match, try inverse:
   Query WHERE base = to AND target = from
   Return 1.0 / rate

3. If neither exists, return null
```

This means only one direction needs to be stored per currency pair -- the inverse is computed automatically.

### Display Formatting

`CurrencyService::formatAmount(amount, currency)`

Formats with the currency symbol prefix and appropriate decimal places:
- JPY: 0 decimal places (e.g., `JPY149`)
- All others: 2 decimal places (e.g., `$100.00`, `CA$136.54`)

### Settings UI

The `CurrencyController` provides:

1. **Index page** (`GET /settings/currency`) -- displays current rates for USD base, list of supported currencies, and a converter tool
2. **Sync trigger** (`POST /settings/currency/sync`) -- dispatches `SyncExchangeRates` job and redirects back with success message
3. **Convert endpoint** (`POST /settings/currency/convert`) -- JSON API that validates amount/from/to and returns converted amount with formatting

---

## Integration Points

- **Transaction Recording**: Other modules should store amounts in their original currency and use `CurrencyService::convert()` at display time for reporting in the organization's base currency.
- **Analytics / P&L**: Profit and loss calculations for international orders should convert using the rate closest to the transaction date via the `date` parameter.
- **Marketplace Orders**: eBay and Shopify orders from international sites arrive in the marketplace's currency. The conversion service translates these to the organization's base currency.
- **Scheduling**: The `SyncExchangeRates` job should be added to the Laravel scheduler (e.g., daily) to keep rates current. Without scheduled syncs, rates become stale.
- **Provider Replacement**: The `ExchangeRateProvider::fetchRates()` method currently returns hardcoded sample rates. For production, replace with a call to a real exchange rate API (exchangeratesapi.io, Open Exchange Rates, or similar). The `syncRates()` persistence layer does not need to change.
- **Routes**: Currency settings routes are in `routes/web.php`, under the settings group.
- **Frontend**: `resources/js/Pages/Settings/Currency/` contains the settings page with rate display and converter.
