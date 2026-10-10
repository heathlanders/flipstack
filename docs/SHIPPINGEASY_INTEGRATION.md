# ShippingEasy Integration

## Overview

ShippingEasy is integrated as a shipping provider option alongside EasyPost and ShipStation. When selected as the active provider in Fulfillment Settings, it handles rate shopping, label purchasing, tracking webhooks, and order management.

## Architecture

```
ShipmentService (provider selector)
  -> ShippingEasyService (API client)
     -> ShippingEasySyncLog (audit trail)

ShippingEasyWebhookController (signature verification)
  -> ProcessShippingEasyWebhook (async job)
     -> TrackingService (status updates)
```

## Authentication

ShippingEasy uses HMAC-SHA256 signed requests with three credentials:

| Credential | Storage | Purpose |
|---|---|---|
| API Key | `IntegrationCredential.client_id` (encrypted) | Account identifier, sent in `api_key` header |
| API Secret | `IntegrationCredential.client_secret` (encrypted) | HMAC signing key |
| Store API Key | `IntegrationCredential.config.store_api_key` | Per-store identifier for order creation |

**Signature format (matches official SE PHP client):**
1. Add `api_key` and `api_timestamp` to query params
2. `ksort()` all params alphabetically
3. Plaintext: `METHOD & PATH & http_build_query(params) & normalized_body`
4. Body normalization: `str_replace('\\/', '/', $body)`, omit if empty or `"null"`
5. `api_signature = HMAC-SHA256(plaintext, API_SECRET)`
6. All auth sent as URL query params (not headers)

All credentials are stored encrypted. The `decrypted_client_id` and `decrypted_client_secret` accessors provide plaintext values.

## Key Files

| Category | File | Purpose |
|---|---|---|
| Service | `app/Services/Fulfillment/ShippingEasyService.php` | API client with rate, order, label, and carrier operations |
| Webhook Controller | `app/Http/Controllers/Fulfillment/ShippingEasyWebhookController.php` | HMAC signature verification and event dispatch |
| Webhook Job | `app/Jobs/Fulfillment/ProcessShippingEasyWebhook.php` | Async webhook processing (tracking, labels) |
| Tracking | `app/Services/Fulfillment/TrackingService.php` | `processShippingEasyUpdate()` and status mapping |
| Sync Log | `app/Models/Fulfillment/ShippingEasySyncLog.php` | Audit trail for all API operations |
| Settings | `app/Http/Controllers/Fulfillment/SettingsController.php` | Credential storage and carrier sync |
| Settings UI | `resources/js/Pages/Fulfillment/Settings/Index.vue` | API key, secret, store API key, webhook URL |
| Routes | `routes/fulfillment.php` | Webhook route (CSRF-exempt) |
| Migration | `database/migrations/2026_03_23_000001_create_shipping_easy_sync_logs_table.php` | Sync log table |
| Shipment Model | Columns: `shippingeasy_shipment_id`, `shippingeasy_order_id` on shipments table |

## API Endpoints Used

### Documented Endpoints (Official SE API)

| Operation | Method | Endpoint |
|---|---|---|
| Create Order | POST | `/api/stores/{store_api_key}/orders` |
| Cancel Order | POST | `/api/stores/{store_api_key}/orders/{external_order_identifier}/cancellations` |
| Get Orders | GET | `/api/orders` (paginated: `page`, `per_page` max 200) |
| Get Order | GET | `/api/orders/{order_id}` |
| Get Stores | GET | `/api/stores` |
| Find Order by External ID | GET | `/api/stores/{store_api_key}/orders/{external_order_identifier}` |
| Update Order Status | PUT | `/api/stores/{store_api_key}/orders/{external_order_identifier}/status` |
| Get Carriers | GET | `/api/carriers` |
| Test Connection | GET | `/api/carriers` |

### Undocumented Endpoints (Verify with Real Credentials)

| Operation | Method | Endpoint | Note |
|---|---|---|---|
| Get Rates | POST | `/api/rates` | Not in official API docs |
| Purchase Label | POST | `/api/orders/{order_id}/labels` | Not in official API docs |
| Void Label | POST | `/api/shipments/{shipment_id}/void` | Not in official API docs |

Base URL: `https://app.shippingeasy.com/api`

## Webhook Events

Webhook URL: `POST /webhooks/shippingeasy` (CSRF-exempt, HMAC-verified)

ShippingEasy sends a shipment notification callback with a top-level `shipment` object containing a `workflow_state` field. The controller maps workflow states to internal event types:

| SE Workflow State | Internal Event | Handler | Action |
|---|---|---|---|
| `label_ready` | `label.purchased` | `handleLabelPurchased` | Set tracking number, label URL, cost (cents -> dollars) |
| `label_printed` | `label.purchased` | `handleLabelPurchased` | Same as label_ready |
| `cancelled` | `shipment.cancelled` | `handleCancelled` | Mark shipment as cancelled |
| `drop_shipped` | `shipment.shipped` | `handleTrackingEvent` | Update tracking via TrackingService |
| `label_pending` | `label.pending` | (ignored) | No action, not processable |

### Callback Payload Structure

The SE callback sends `api_signature`, `api_key`, and `api_timestamp` as query parameters (controller also accepts headers as fallback). Payload contains:
- `shipment.id` -- SE shipment ID
- `shipment.tracking_number`, `carrier_key`, `carrier_service_key`
- `shipment.shipment_cost` -- cost in **cents**
- `shipment.workflow_state` -- determines event type
- `shipment.orders[]` -- nested array with `id` (SE order ID) and `external_order_identifier`

### Shipment Lookup Priority

`findShipment()` tries matching in order: SE shipment ID -> SE order ID (from nested `orders[0].id`) -> tracking number.

## Provider Selection

The active shipping provider is stored in `OrganizationSettings` key `fulfillment_shipping_provider`. `ShipmentService::getShippingService()` dynamically resolves the service:

```php
match ($provider) {
    'shippingeasy' => new ShippingEasyService($organization),
    'shipstation' => new ShipStationService($organization),
    default => new EasyPostService($organization),
}
```

## Order Creation with Line Items

When purchasing a label, `createOrder()` includes full line item details:

```php
'line_items_attributes' => [
    ['product_name' => '...', 'sku' => '...', 'quantity' => 1, 'unit_price' => 29.99, 'weight_in_ounces' => 8.0]
]
```

## Rate Limiting

ShippingEasy allows **120 API calls per minute per API key**. On HTTP 429, the service retries with exponential backoff (1s, 2s, 4s delays) up to 3 times. If all retries are exhausted, it throws a "rate limit exceeded" exception. Each retry re-signs the request with a fresh timestamp. Server errors (5xx) are not retried but the sync log is marked as retryable.

## Sync Logging

Every API call creates a `ShippingEasySyncLog` record with:
- Request details (endpoint, method, payload)
- Response details (status code, payload, response time)
- Error details (message, code, retryable flag)
- Organization and credential linkage

Operations: `create_order`, `purchase_label`, `void_label`, `get_rates`, `get_orders`, `get_order`, `cancel_order`, `get_carriers`, `webhook_received`, `test_connection`

## Setup Instructions

1. Get API credentials from ShippingEasy: Settings > API Credentials
2. Get Store API Key from ShippingEasy: Settings > Stores
3. In FlipStack: Fulfillment > Settings > select ShippingEasy as provider
4. Enter API Key, API Secret, and Store API Key
5. Click "Test Connection & Save"
6. Copy the webhook URL and add it to ShippingEasy: Settings > Webhooks

## Troubleshooting

**Connection test fails:**
- Verify API Key and Secret are correct
- Check `shipping_easy_sync_logs` for error details

**Labels not purchasing:**
- Ensure Store API Key is configured (required for order creation)
- Check sync logs for API errors

**Webhooks not arriving:**
- Verify webhook URL is configured in ShippingEasy dashboard
- Check that CSRF middleware is excluded on webhook route
- Check signature verification: webhook `api_key` must match stored credential

**Tracking not updating:**
- Check `ProcessShippingEasyWebhook` job in failed_jobs
- Verify shipment has `shippingeasy_order_id` or `shippingeasy_shipment_id`
