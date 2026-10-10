---
name: buyer-communication
description: Marketplace buyer message inbox, thread view, reply flow, and eBay message sync
user-invocable: false
---

# Buyer Communication

## Key Files

| Area | File | Purpose |
|------|------|---------|
| **Models** | `app/Models/Communication/MarketplaceMessage.php` | Inbound/outbound message with thread grouping, status, customer/order links |
| | `app/Models/Communication/MarketplaceMessageReply.php` | Reply to a message, tracks sent_by user and delivery status |
| **Services** | `app/Services/Communication/MessageSyncService.php` | Orchestrates sync per org, upserts messages, auto-links customers, inbox stats |
| | `app/Services/Communication/EbayMessageService.php` | eBay Trading API GetMyMessages fetch and AddMemberMessageAAQToPartner reply |
| **Controllers** | `app/Http/Controllers/Communication/MessageController.php` | Inbox, thread view, reply, mark read, archive |
| **Jobs** | `app/Jobs/Communication/SyncMarketplaceMessages.php` | Queued job to sync messages (queue: `ebay-sync`, every 15 min) |
| **Routes** | `routes/web.php` (messages prefix) | Message inbox and thread routes |
| **Pages** | `resources/js/Pages/Communication/Inbox.vue` | Message inbox list with filters |
| | `resources/js/Pages/Communication/Thread.vue` | Thread view with reply form |

## MarketplaceMessage Fields

| Field | Type | Purpose |
|-------|------|---------|
| `organization_id` | string | Org scope |
| `customer_id` | uuid (nullable) | Linked customer record |
| `order_id` | uuid (nullable) | Linked order record |
| `marketplace` | string | Source: `ebay`, `shopify`, `amazon` |
| `marketplace_message_id` | string | External message ID for dedup |
| `marketplace_thread_id` | string | Groups messages into a conversation thread |
| `marketplace_item_id` | string (nullable) | eBay ItemID, links message to listing/product |
| `direction` | string | `inbound` (buyer to seller) or `outbound` (seller to buyer) |
| `subject` | string | Message subject line |
| `body` | text | Message body content |
| `sender_name` | string | Display name of the sender |
| `sender_id` | string | External sender identifier |
| `status` | string | `unread`, `read`, `replied`, `archived` |
| `received_at` | datetime | When the message was received from the marketplace |
| `read_at` | datetime | When the message was first read |
| `replied_at` | datetime | When the message was replied to |

Unique index: `(organization_id, marketplace, marketplace_message_id)` for dedup during sync.

## MarketplaceMessageReply Fields

| Field | Type | Purpose |
|-------|------|---------|
| `message_id` | uuid | FK to MarketplaceMessage |
| `body` | text | Reply content |
| `sent_by` | integer | FK to User who sent the reply |
| `sent_at` | datetime | When the reply was sent |
| `marketplace_response_id` | string (nullable) | External response ID from marketplace API |
| `status` | string | `draft`, `sent`, `failed` |

## Message Statuses

| Status | Meaning |
|--------|---------|
| `unread` | New inbound message, not yet viewed |
| `read` | Message has been opened/viewed |
| `replied` | A reply has been sent |
| `archived` | Removed from active inbox |

## eBay Message Sync

### How it works

`EbayMessageService::fetchMessages()` uses a two-phase approach via the eBay Trading API:

1. **Phase 1 (Headers)**: `GetMyMessages` with `DetailLevel=ReturnHeaders` to get message IDs and metadata for a date range. Paginated at 200 per page.
2. **Phase 2 (Details)**: `GetMyMessages` with `DetailLevel=ReturnMessages` batching 10 message IDs per call to get full body text.

System/marketing messages (MessageType = System or HighPriorityInfo) are filtered out.

### Field mapping (eBay XML -> MarketplaceMessage)

| eBay Field | MarketplaceMessage Field |
|-----------|-------------------------|
| `MessageID` | `marketplace_message_id` |
| `ExternalMessageID` | `marketplace_thread_id` |
| `ItemID` | `marketplace_item_id` |
| `Subject` | `subject` |
| `Text` | `body` |
| `Sender` | `sender_name` |
| `SendingUserID` | `sender_id` |
| `ReceiveDate` | `received_at` |

### Sync orchestration

`MessageSyncService::syncForOrganization($orgId)`:
1. Queries active eBay `IntegrationCredential` records for the org
2. For each credential, resolves `since` from `credential.config.last_message_sync_at` (or 30 days ago on first sync)
3. Calls `EbayMessageService::fetchMessages($since)`
4. Upserts messages using `(organization_id, marketplace, marketplace_message_id)` as match key
5. Auto-links to `Customer` via `sender_id` matching `marketplace_customer_id`
6. Updates `last_message_sync_at` in credential config on success

### Schedule

- Job: `SyncMarketplaceMessages` on `ebay-sync` queue
- Scheduled every 15 minutes in `routes/console.php`
- Dispatched for all organizations, uses `withoutOverlapping()`

## Thread View

Messages are grouped by `marketplace_thread_id`. When viewing a thread:
1. The controller loads the primary message with customer, order, and replies
2. If `marketplace_thread_id` is set, all messages sharing that thread ID are loaded chronologically
3. The primary message is automatically marked as read via `MessageSyncService::markAsRead()`

## Reply Flow

1. User submits reply body (max 5000 chars) via `MessageController::reply()`
2. Controller resolves org's active eBay `IntegrationCredential`
3. `EbayMessageService::sendReply()` sends via eBay Trading API `AddMemberMessageAAQToPartner` with ItemID, RecipientID (sender_id), and Body
4. On success: creates `MarketplaceMessageReply` with `status = sent`, updates parent message to `replied`
5. On API failure: creates reply with `status = failed`, logs error

## Inbox Statistics

`MessageSyncService::getInboxStats()` returns:
- `unread`: Total unread count
- `total`: Total non-archived count
- `by_marketplace`: Per-marketplace breakdown of total and unread counts

## Routes

| Method | URI | Name | Action |
|--------|-----|------|--------|
| GET | `/messages` | `messages.inbox` | Inbox list (filterable by marketplace, status) |
| GET | `/messages/{message}` | `messages.thread` | Thread view |
| POST | `/messages/{message}/reply` | `messages.reply` | Send reply |
| POST | `/messages/{message}/read` | `messages.read` | Mark as read |
| POST | `/messages/{message}/archive` | `messages.archive` | Archive message |

## Filters

The inbox supports query parameter filters:
- `marketplace`: Filter by source (`ebay`, `shopify`, `amazon`)
- `status`: Filter by status (`unread`, `read`, `replied`)

Archived messages are always excluded from the inbox.

## Auto-Linking

During sync, messages are auto-linked to:
- **Customer**: matched via `sender_id` against `Customer.marketplace_customer_id` where `marketplace_source = 'ebay'`
- **Order**: via `order_id` FK (manual or future auto-match)

These links are displayed in both the inbox list and thread view.

## Scopes

- `unread()` -- filters to `status = unread`
- `forThread($threadId)` -- filters by `marketplace_thread_id`
- `inbound()` -- filters to `direction = inbound`

## Debugging Commands

```php
// Inbox stats for an organization
$sync = app(\App\Services\Communication\MessageSyncService::class);
$sync->getInboxStats($orgId);

// Manually trigger sync for an org
$svc = new \App\Services\Communication\MessageSyncService();
$svc->syncForOrganization($orgId);

// Check last sync time for a credential
$cred = \App\Models\Integration\IntegrationCredential::find($credId);
echo $cred->config['last_message_sync_at'] ?? 'never';

// Unread message count
MarketplaceMessage::where('organization_id', $orgId)
  ->where('status', 'unread')
  ->count();

// Messages by marketplace
MarketplaceMessage::where('organization_id', $orgId)
  ->selectRaw("marketplace, status, COUNT(*) as count")
  ->groupBy('marketplace', 'status')
  ->get();

// Recent inbound messages with customer/order links
MarketplaceMessage::where('organization_id', $orgId)
  ->where('direction', 'inbound')
  ->with(['customer:id,name,email', 'order:id,order_number'])
  ->orderByDesc('received_at')
  ->take(20)
  ->get(['id', 'marketplace', 'subject', 'sender_name', 'status', 'customer_id', 'order_id', 'received_at']);

// Replies for a message
MarketplaceMessageReply::where('message_id', $messageId)
  ->with('sender:id,name')
  ->orderBy('sent_at')
  ->get(['id', 'body', 'sent_by', 'status', 'sent_at']);

// Messages without customer link (candidates for auto-matching)
MarketplaceMessage::where('organization_id', $orgId)
  ->whereNull('customer_id')
  ->where('direction', 'inbound')
  ->count();
```

## Audit Logging

| Action | Category | Severity | Metadata keys |
|--------|----------|----------|---------------|
| `message.received` | communication | info | marketplace, sender_name, subject, source: webhook|cron |
| `message.replied` | communication | info | marketplace, response_seconds, source |
| `message.marked_resolved` | communication | info | marketplace, resolution_reason |

Trait: models in this feature use `LogsAudit` (`app/Traits/LogsAudit.php`) for auto CRUD diff. Domain lifecycle events emitted from `app/Services/Communication/MessageSyncService`, `EbayMessagingService`, `MessageController@reply` via `UserActivityLog::log(action, desc, $user, $metadata, $severity, $category)`.

KPI consumer: `UserProductivityDaily.messages_answered_count` (from `message.replied`).

Spec: `docs/AUDIT_SYSTEM.md`. Enforced rule: `.claude/rules/audit-logging.md`. Tests: `use Tests\Support\AssertsAudits;` then `$this->assertAudited('action', $user, [...])`.
