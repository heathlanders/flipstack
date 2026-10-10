Run tests for a specific feature area. Map the feature name to the correct test paths.

**Usage**: `/test-feature <area>`

**Feature area mapping**:

| Area | Test Paths |
|------|-----------|
| `intake` | `tests/Feature/IntakeQueue/`, `tests/Unit/Models/ProductIntakeQueueTest.php` |
| `ebay` | `tests/Feature/Ebay/`, `tests/Unit/Services/Ebay/` |
| `inventory` | `tests/Feature/Inventory/`, `tests/Unit/Models/Inventory/` |
| `sync` | `tests/Feature/Inventory/MarketplaceInventorySyncTest.php`, `tests/Unit/Services/MarketplaceSyncRuleEngineTest.php` |
| `shopify` | `tests/Feature/Shopify/` |
| `product` | `tests/Feature/Product/`, `tests/Unit/Models/Catalog/` |
| `supplier` | `tests/Feature/Supplier/` |
| `auth` | `tests/Feature/Auth/` |
| `fulfillment` | `tests/Feature/Fulfillment/`, `tests/Unit/Services/Fulfillment/` |
| `api` | `tests/Feature/Api/` |
| `all` | (no filter -- runs everything) |

**Steps**:

1. Parse the `$ARGUMENTS` to determine the feature area.
2. If the area is not recognized, list available areas and ask the user.
3. Check that the test directories exist using `ls`. If a mapped path doesn't exist, skip it silently.
4. Run the tests:
   ```bash
   docker exec ecommerce_app php artisan test <paths>
   ```
5. If tests fail, show the failure details and suggest which files to investigate.
