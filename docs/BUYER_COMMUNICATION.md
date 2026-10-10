# Buyer Communication System

## Overview

The buyer communication system provides a unified inbox for marketplace messages across eBay, Shopify, and Amazon. Messages are synced from marketplace APIs (eBay via Trading API `GetMyMessages`), threaded by `marketplace_thread_id`, and linked to orders and customers when possible. Staff can reply from within the app, and replies are delivered back through the marketplace API (`AddMemberMessageAAQToPartner`).

---

## Architecture

### Key Files

| File | Purpose |
|------|---------|
| `app/Models/Communication/MarketplaceMessage.php` | Message model with status, direction, and thread scoping |
| `app/Models/Communication/MarketplaceMessageReply.php` | Reply model linked to a message, tracks delivery status |
| `app/Services/Communication/MessageSyncService.php` | Orchestrates sync per org, upserts messages, auto-links customers, inbox state |
| `app/Services/Communication/EbayMessageService.php` | eBay Trading API message fetch (GetMyMessages) and reply delivery (AddMemberMessageAAQToPartner) |
| `app/Jobs/Communication/SyncMarketplaceMessages.php` | Queued job (ebay-sync queue, every 15 min) |
| `app/Http/Controllers/Communication/MessageController.php` | Inbox UI controllers |
| `resources/js/Pages/Communication/Inbox.vue` | Inbox list with filters |
| `resources/js/Pages/Communication/Thread.vue` | Thread view with reply form |

### Message Flow

```
eBay Trading API (GetMyMessages)
    |
    v
EbayMessageService::fetchMessages()
    |  Phase 1: GetMyMessages(ReturnHeaders) -> message IDs
    |  Phase 2: GetMyMessages(ReturnMessages) -> full body, batched 10 at a time
    |
    v
MessageSyncService::syncForOrganization()
    |  Upserts by (org_id, marketplace, marketplace_message_id)
    |  Auto-links to Customer via sender_id -> marketplace_customer_id
    |  Stores last_message_sync_at in credential config
    |
    v
MarketplaceMessage records created (direction = 'inbound')
    |  Grouped by marketplace_thread_id for threading
    |
    v
Staff views unified inbox (/messages)
    |
    v
Staff composes reply
    |
    v
EbayMessageService::sendReply()
    |  Calls AddMemberMessageAAQToPartner Trading API
    |  Creates MarketplaceMessageReply (status = 'sent' or 'failed')
    |  Updates parent message status to 'replied'
    |
    v
Reply delivered to buyer via eBay
```

---

## Data Model

### `marketplace_messages`

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid | Primary key |
| `organization_id` | uuid | FK to organizations |
| `customer_id` | uuid | FK to customers (nullable, auto-linked during sync) |
| `order_id` | uuid | FK to orders (nullable, auto-linked) |
| `marketplace` | string | Source: `ebay`, `shopify`, `amazon` |
| `marketplace_message_id` | string | External message ID from the marketplace |
| `marketplace_thread_id` | string | Thread grouping ID from the marketplace |
| `marketplace_item_id` | string | eBay ItemID (nullable), links to listing/product |
| `direction` | string | `inbound` or `outbound` |
| `subject` | string | Message subject line |
| `body` | text | Message body content |
| `sender_name` | string | Display name of the sender |
| `sender_id` | string | Marketplace user ID of the sender |
| `status` | string | `unread`, `read`, `replied`, `archived` |
| `replied_at` | datetime | When the message was replied to |
| `read_at` | datetime | When the message was marked as read |
| `received_at` | datetime | Original send time from the marketplace |

Unique index: `(organization_id, marketplace, marketplace_message_id)` for dedup.

### `marketplace_message_replies`

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid | Primary key |
| `message_id` | uuid | FK to marketplace_messages |
| `body` | text | Reply content |
| `sent_by` | integer | FK to users (staff member who sent) |
| `sent_at` | datetime | When the reply was sent |
| `marketplace_response_id` | string | Response ID from marketplace API (nullable) |
| `status` | string | `draft`, `sent`, `failed` |

---

## Key Flows

### Message Sync (eBay)

`MessageSyncService::syncForOrganization(orgId)` orchestrates sync:

1. Queries active eBay `IntegrationCredential` records for the org
2. For each credential, resolves `since` from `credential.config.last_message_sync_at` (or 30 days ago on first sync)
3. `EbayMessageService::fetchMessages($since)` calls eBay Trading API:
   - **Phase 1**: `GetMyMessages(ReturnHeaders)` with date range and pagination (200/page) to get message IDs
   - **Phase 2**: `GetMyMessages(ReturnMessages)` batching 10 IDs per call for full body text
   - Filters out system/marketing messages (MessageType = System or HighPriorityInfo)
4. Each message is deduplicated by `marketplace_message_id` and grouped into threads by `marketplace_thread_id` (eBay's ExternalMessageID)
5. Auto-links to Customer via `sender_id` matching `Customer.marketplace_customer_id`
6. Updates `last_message_sync_at` in credential config

**Schedule**: `SyncMarketplaceMessages` job runs every 15 minutes on `ebay-sync` queue (configured in `routes/console.php`).

### Thread Linking

Messages within the same conversation share a `marketplace_thread_id`. The `scopeForThread(threadId)` scope on `MarketplaceMessage` retrieves all messages in a thread. The inbox UI groups messages by thread for a conversation view.

### Auto-Linking to Customers

During sync, the service matches `sender_id` against existing `Customer` records using `marketplace_customer_id` where `marketplace_source = 'ebay'`.

### Reply Delivery

When staff sends a reply via `MessageController::reply()`:

1. Controller resolves the org's active eBay `IntegrationCredential`
2. `EbayMessageService::sendReply()` calls eBay Trading API `AddMemberMessageAAQToPartner` with:
   - `ItemID` (from `marketplace_item_id`)
   - `RecipientID` (from `sender_id`)
   - `ParentMessageID` (from `marketplace_message_id`)
   - Reply `Body`
3. On success: `MarketplaceMessageReply` created with `status = 'sent'`, parent message updated to `replied`
4. On API failure: reply created with `status = 'failed'`, error logged

### Inbox Statistics

`MessageSyncService::getInboxStats(orgId)` returns:
- Total unread count
- Total non-archived count
- Breakdown by marketplace (total and unread per marketplace)

### Status Transitions

```
unread -> read      (MessageSyncService::markAsRead)
read   -> replied   (EbayMessageService::sendReply)
any    -> archived  (MessageSyncService::archive)
```

---

## Integration Points

- **eBay Trading API**: `EbayMessageService` uses `IntegrationCredential` for OAuth tokens. Posts XML to `https://api.ebay.com/ws/api.dll` with IAF token headers.
- **Customer CRM**: Messages link to `Customer` records via `customer_id`, enabling message history in the customer profile.
- **Order Management**: Messages link to `Order` records via `order_id`, providing context during fulfillment.
- **Background Jobs**: `SyncMarketplaceMessages` runs every 15 min on `ebay-sync` queue via Horizon.
- **Routes**: Message inbox routes are in `routes/web.php`, handled by controllers in `app/Http/Controllers/Communication/`.
- **Frontend**: `resources/js/Pages/Communication/` contains the inbox and thread view components.
