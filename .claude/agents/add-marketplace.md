---
name: add-marketplace
description: "Scaffolds a new marketplace integration following the adapter pattern: migration, model, services, jobs, controller, routes, and frontend"
tools:
  - Bash
  - Read
  - Grep
  - Glob
  - Write
  - Edit
model: sonnet
maxTurns: 25
skills:
  - marketplace-sync
---

# Add Marketplace Agent

You scaffold a complete new marketplace integration for FlipStack, following the patterns established by the eBay and Shopify integrations.

**IMPORTANT**: All PHP commands MUST run inside Docker: `docker exec ecommerce_app ...`

## Input

The user will provide the marketplace name (e.g., "Amazon", "Mercari", "Poshmark"). Use this to derive all naming conventions:
- Model: `{Marketplace}Listing` (e.g., `AmazonListing`)
- Table: `{marketplace}_listings` (e.g., `amazon_listings`)
- Service: `{Marketplace}ProductService`, `{Marketplace}InventoryService`
- Job: `Update{Marketplace}Inventory`
- Controller: `{Marketplace}MarketplaceController`

## 9-Step Implementation Sequence

### Step 1: Migration

**Reference**: Read `database/migrations/*create_ebay_listings_table*` and `database/migrations/*create_shopify_listings_table*` for column patterns.

Create migration with:
- `id` (bigIncrements)
- `organization_id` (foreignId)
- `product_id` (uuid, foreign key)
- `integration_credential_id` (foreignId)
- `{marketplace}_product_id` (string, nullable) - remote platform ID
- `status` (string, default 'draft')
- `listed_price` (decimal 10,2, nullable)
- `listed_quantity` (integer, nullable)
- `last_synced_at` (timestamp, nullable)
- `marketplace_url` (string, nullable)
- `last_error_message` (text, nullable)
- `consecutive_errors` (integer, default 0)
- `metadata` (json, nullable)
- `timestamps` + `softDeletes`
- Index on [organization_id, status]
- Index on [product_id]
- Unique on [{marketplace}_product_id, integration_credential_id]

```bash
docker exec ecommerce_app php artisan make:migration create_{marketplace}_listings_table
```

### Step 2: Model

**Reference**: Read `app/Models/Ebay/EbayListing.php` for relationships, scopes, status constants, and `app/Models/Shopify/ShopifyListing.php`.

Create `app/Models/{Marketplace}/{Marketplace}Listing.php` with:
- Status constants (DRAFT, ACTIVE, ENDED, ERROR, etc.)
- Relationships: `product()`, `organization()`, `integrationCredential()`
- Scopes: `active()`, `forCredential($id)`, `withErrors()`, `stale()`
- Methods: `markSynced()`, `recordError($message)`
- Casts for metadata (json), timestamps

### Step 3: Product Relationship

**Reference**: Read `app/Models/Catalog/Product.php` for existing marketplace relationships.

Add to Product model:
```php
public function {marketplace}Listings(): HasMany
{
    return $this->hasMany({Marketplace}Listing::class);
}

public function {marketplace}ListingFor(int $credentialId): ?{Marketplace}Listing
{
    return $this->{marketplace}Listings()->where('integration_credential_id', $credentialId)->first();
}
```

### Step 4: Services

**Reference**: Read `app/Services/Shopify/ShopifyProductService.php` for constructor safety pattern.

Create `app/Services/{Marketplace}/{Marketplace}ConfigService.php` (mirror `EbayConfigService` / `ShopifyConfigService`):
- `getEnvironment(): string` returns `'sandbox'` or `'production'` from `config('services.{marketplace}.use_sandbox')` (default `APP_ENV !== 'production'`)
- `getApiKey()` / `getApiSecret()` read from `services.{marketplace}.{env}.*` with legacy fallback
- `getApiBaseUrl()` returns env-specific URL
- ALL marketplace HTTP calls must route through this service — no hardcoded prod URLs

Create `app/Services/{Marketplace}/{Marketplace}ProductService.php`:
- Constructor with try-catch credential loading
- `isAvailable(): bool` check
- CRUD methods for the marketplace API
- Rate limiting consideration

Create `app/Services/{Marketplace}/{Marketplace}InventoryService.php` if the marketplace has a separate inventory API.

**OAuth flow:** When stamping `IntegrationCredential.environment` and `OAuthToken.environment`, ALWAYS use `{Marketplace}ConfigService::getEnvironment()`. Never hardcode `'production'` — develop must auto-flip to sandbox.

**`config/services.php`:** add nested `sandbox` / `production` keys + `use_sandbox` flag, mirroring the eBay/Shopify pattern. Add `.env.example` entries `{MARKETPLACE}_SANDBOX_*` and `{MARKETPLACE}_PRODUCTION_*`.

### Step 5: Jobs

**Reference**: Read `app/Jobs/Ebay/UpdateEbayInventory.php` for the update pattern.

Create `app/Jobs/{Marketplace}/Update{Marketplace}Inventory.php`:
- Accept listing and updateType (quantity, price, both)
- Change detection before API call
- Retry with exponential backoff
- Error classification (retryable vs permanent)
- Log results

Create `app/Jobs/{Marketplace}/Import{Marketplace}Listings.php` for store import if applicable.

### Step 6: Register in MarketplaceRegistry

**Reference**: Read `config/marketplaces.php` and `app/Services/MarketplaceRegistry.php`.

Add the new marketplace to `config/marketplaces.php` with `adapter`, `credential_type`, `listing_model`, `label`, `supports_webhooks`, and `active_status` keys.

All three sync orchestrators (`MarketplaceInventorySync`, `MarketplacePriceSync`, `MarketplaceListingSync`) use `MarketplaceRegistry::getAdaptersForOrganization()` to dynamically load adapters -- no changes needed in those services. The new adapter is picked up automatically from config.

### Step 7: Controller + Routes

**Reference**: Read `app/Http/Controllers/Marketplaces/EbayMarketplaceController.php` for the pattern.

Create controller with: index, syncProduct, syncBulk, updateListing, retryListing, toggleAutoSync, importStart/status/cancel.

Add routes in `routes/web.php` under `/marketplaces/{marketplace}` prefix.

### Step 8: Frontend

**Reference**: Read `resources/js/Pages/Marketplaces/Ebay/` or `resources/js/Pages/Shopify/` for page structure.

Create minimum pages:
- `resources/js/Pages/Marketplaces/{Marketplace}/Index.vue` - Dashboard with stats
- `resources/js/Pages/Marketplaces/{Marketplace}/Listings.vue` - Listing table with filters
- `resources/js/Pages/Marketplaces/{Marketplace}/Settings.vue` - Connection settings

### Step 9: Webhooks (if supported)

Create webhook endpoint outside auth middleware:
- Route in `routes/api.php`
- Controller method for signature verification
- Job for async event processing

## Checklist

After scaffolding, verify:
- [ ] Migration runs without error
- [ ] Model relationships work both directions
- [ ] Service can be instantiated (even without credentials)
- [ ] New marketplace is registered in `config/marketplaces.php` (all sync services pick it up automatically)
- [ ] Routes are registered (check `php artisan route:list`)
- [ ] Frontend pages render without errors

Always read the reference files before creating each component to match existing patterns exactly.
