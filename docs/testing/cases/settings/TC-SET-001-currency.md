# Currency Settings Test Cases

## Document ID: TC-SET-001

**Domain:** Settings
**Related Test Plan:** `docs/testing/plans/settings-test-plan.md`
**Last Updated:** 2026-04-15

---

### TC-SET-001-01: View currency rates page

| Field | Value |
|-------|-------|
| **ID** | TC-SET-001-01 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user (controller applies `auth` middleware)
2. CurrencyService injected and operational
3. ExchangeRate records exist in database (or service returns cached/default rates)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `GET` request to `route('settings.currency.index')` | Response status 200, Inertia renders `Settings/Currency/Index` |
| 2 | Assert `baseCurrency` prop equals `'USD'` | Base currency hardcoded to USD |
| 3 | Assert `rates` prop contains exchange rate data from `CurrencyService::getAvailableRates('USD')` | Array of currency code to rate mappings |
| 4 | Assert `supportedCurrencies` prop contains list from `CurrencyService::getSupportedCurrencies()` | List of supported currency codes with names |
| 5 | Assert `organizationId` prop is set to the user's current organization | Derived from `currentTeam->organization_id` or `organization_id` fallback chain |
| 6 | Assert page loads without error when no exchange rates exist in database | CurrencyService handles empty state gracefully |

**Notes:**
- Route: `GET /settings/currency` named `settings.currency.index`
- Base currency is currently hardcoded to `'USD'` in the controller
- Organization ID resolution uses the three-level fallback: `currentTeam->organization_id`, `current_organization_id`, `organization_id`
- CurrencyService is injected via constructor with `protected CurrencyService $currencyService`

---

### TC-SET-001-02: Sync exchange rates dispatches job

| Field | Value |
|-------|-------|
| **ID** | TC-SET-001-02 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user
2. Queue system (Redis/Horizon) operational
3. `SyncExchangeRates` job class exists in `App\Jobs\Currency`

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` request to `route('settings.currency.sync')` | Response redirects back |
| 2 | Assert flash message: `'Exchange rate sync has been queued.'` | Success feedback displayed |
| 3 | Assert `SyncExchangeRates` job was dispatched to the queue | `Queue::assertPushed(SyncExchangeRates::class)` in test |
| 4 | After job executes: assert ExchangeRate records updated in database | Rates fetched from external API and persisted |
| 5 | After job executes: reload currency page and verify `rates` prop has fresh data | Updated rates visible on page |

**Notes:**
- Route: `POST /settings/currency/sync` named `settings.currency.sync`
- `SyncExchangeRates::dispatch()` queues the job -- it does not execute inline
- The job fetches rates from an external exchange rate API provider
- No request parameters are required for sync -- dispatches with no arguments
- Rate limiting or cooldown on the sync button should be enforced at the UI level

---

### TC-SET-001-03: Convert amount between currencies

| Field | Value |
|-------|-------|
| **ID** | TC-SET-001-03 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user
2. ExchangeRate records exist for the source and target currencies (e.g., USD to EUR rate stored)
3. CurrencyService operational

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` to `route('settings.currency.convert')` with `amount=100.00`, `from='USD'`, `to='EUR'` | Response status 200, JSON response |
| 2 | Assert response contains `success: true` | Conversion succeeded |
| 3 | Assert response contains `original_amount: 100.00` | Input echoed back |
| 4 | Assert response contains `converted_amount` as a numeric value | Converted amount via `CurrencyService::convert()` |
| 5 | Assert response contains `formatted` string with currency symbol/formatting | `CurrencyService::formatAmount($converted, 'EUR')` |
| 6 | Assert response contains `from: 'USD'` and `to: 'EUR'` | Currency codes echoed back |

**Notes:**
- Route: `POST /settings/currency/convert` named `settings.currency.convert`
- Validation: `amount` required numeric min:0, `from` required string size:3, `to` required string size:3
- On `RuntimeException` (e.g., unsupported currency pair), returns `422` with `success: false` and error message
- This is a JSON endpoint, not an Inertia page transition

---

### TC-SET-001-04: Conversion uses latest rate

| Field | Value |
|-------|-------|
| **ID** | TC-SET-001-04 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. ExchangeRate record exists for USD-to-GBP with a known rate (e.g., 0.80)
2. CurrencyService uses the latest rate from the database

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Convert 100 USD to GBP | `converted_amount` equals `100 * 0.80 = 80.00` (based on stored rate) |
| 2 | Update ExchangeRate USD-to-GBP rate to 0.75 (simulating rate sync) | Rate changed in database |
| 3 | Convert 100 USD to GBP again | `converted_amount` equals `100 * 0.75 = 75.00` (uses new rate) |
| 4 | Assert the old rate (0.80) is no longer used | Conversion reflects the latest stored rate |
| 5 | Verify reverse conversion: convert 75 GBP to USD | Result approximately 100 USD (inverse rate applied) |

**Notes:**
- CurrencyService should use the most recently updated ExchangeRate record
- Rate caching (if implemented) must be invalidated when rates are synced
- Reverse conversion may use `1 / rate` or a separately stored inverse rate
- Precision: converted amounts should be rounded appropriately for the target currency

---

### TC-SET-001-05: Supported currencies list

| Field | Value |
|-------|-------|
| **ID** | TC-SET-001-05 |
| **Priority** | P2 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user
2. CurrencyService configured with supported currencies

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `GET` to `route('settings.currency.index')` | Response includes `supportedCurrencies` prop |
| 2 | Assert `supportedCurrencies` is an array/collection | List of supported currency objects |
| 3 | Assert each entry has a currency code (3-letter ISO 4217 format) | e.g., `USD`, `EUR`, `GBP`, `CAD`, `AUD` |
| 4 | Assert USD is included in the supported list | Base currency always supported |
| 5 | Attempt conversion with unsupported currency: POST convert with `to='XYZ'` | Response status 422 with `success: false` and error message from `RuntimeException` |

**Notes:**
- `CurrencyService::getSupportedCurrencies()` returns the list
- The list may be hardcoded or derived from ExchangeRate records in the database
- Validation enforces `size:3` for currency codes but does not validate against the supported list at the validation layer -- the service throws `RuntimeException` for unsupported pairs

---

### TC-SET-001-06: Organization isolation on currency rates

| Field | Value |
|-------|-------|
| **ID** | TC-SET-001-06 |
| **Priority** | P1 |
| **Type** | Security |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Two organizations (Org A and Org B) with users
2. Exchange rates are global (not org-scoped) or org-scoped depending on implementation

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Authenticate as Org A user, GET currency page | Page loads with `organizationId` matching Org A |
| 2 | Assert `organizationId` prop equals Org A's ID | Organization context correct |
| 3 | Authenticate as Org B user, GET currency page | Page loads with `organizationId` matching Org B |
| 4 | Assert `organizationId` prop equals Org B's ID | Organization context correct |
| 5 | Sync rates as Org A user | Job dispatched; rates available to all orgs (exchange rates are typically global) |
| 6 | Verify Org B user also sees updated rates | Exchange rates are shared data, not org-scoped |

**Notes:**
- Exchange rates are inherently global (USD/EUR rate is the same for all organizations)
- The `organizationId` prop is passed for context but may not scope the rate data
- The `CurrencyService::getAvailableRates()` and `getSupportedCurrencies()` methods do not take organization_id
- `SyncExchangeRates::dispatch()` does not pass organization context -- rates are system-wide
- The currency page itself is org-contextualized (for potential org-specific base currency in future), but the rate data is global
