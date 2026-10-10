# API Endpoints Test Plan

| Field            | Value                                              |
|------------------|----------------------------------------------------|
| Document ID      | TP-API                                             |
| Domain           | API Endpoints                                      |
| Last Updated     | 2026-04-15                                         |

## Scope

REST API with Laravel Sanctum authentication. ~100+ endpoints across 16 route groups defined in `routes/api.php`. Serves the Flutter mobile app and potential third-party integrations. All responses use JSON. Multi-tenant with organization scoping.

## Current Test Coverage

- 0 dedicated API endpoint tests
- Some endpoints tested indirectly via existing feature tests
- No automated contract or schema validation tests

## Test Types

| Type             | Framework          | Location                        |
|------------------|--------------------|---------------------------------|
| Feature tests    | PHPUnit + Laravel  | `tests/Feature/Api/`           |
| Unit tests       | PHPUnit            | `tests/Unit/`                   |

## Test Case Files

| File ID    | Title               | Cases | Priority Areas                              |
|------------|----------------------|-------|---------------------------------------------|
| TC-API-001 | Auth Endpoints       | 8     | Login, register, logout, 2FA, token refresh |
| TC-API-002 | Product Endpoints    | 8     | CRUD, barcode lookup, org scoping           |
| TC-API-003 | Fulfillment Endpoints| 8     | Pick, pack, ship, returns                   |
| TC-API-004 | Inventory Endpoints  | 8     | Levels, adjust, transfer, movements         |
| TC-API-005 | eBay Endpoints       | 8     | Status, listings, sync, config              |
| TC-API-006 | Notification Endpoints| 6    | CRUD, mark read, user scoping               |
| TC-API-007 | User & Team Endpoints| 8     | Users, roles, teams, org stats              |
| TC-API-008 | Printing Endpoints   | 6     | Labels, printers, templates, jobs           |

## Key Risks

- Organization scoping bypass could leak data between tenants
- Token expiration and refresh edge cases
- Bulk operations may exceed memory or timeout limits
- Race conditions on concurrent inventory adjustments

## Environment Requirements

- Docker container `ecommerce_app` running with PostgreSQL and Redis
- Sanctum configured for token-based API authentication
- Test database seeded with multi-org fixture data
- Queue set to `sync` driver for deterministic job execution in tests

## Exit Criteria

- All P0 and P1 test cases pass
- No critical or high-severity defects open
- Organization scoping verified on every data-returning endpoint
- 401/403 responses confirmed for unauthenticated and unauthorized requests
- Response payloads validated against expected structure
