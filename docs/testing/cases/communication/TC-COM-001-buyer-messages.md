# Buyer Messages Test Cases

## Document ID: TC-COM-001

**Domain:** Communication
**Related Test Plan:** `docs/testing/plans/communication-test-plan.md`
**Last Updated:** 2026-04-15

---

### TC-COM-001-01: Inbox listing with filters

| Field | Value |
|-------|-------|
| **ID** | TC-COM-001-01 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user with an organization that has marketplace messages
2. Messages exist in multiple statuses (unread, read, replied) and from multiple marketplaces (ebay, shopify)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `messages.inbox` with no filters | 200 response, paginated list of non-archived messages ordered by received_at descending |
| 2 | GET `messages.inbox` with `marketplace=ebay` | Only eBay messages returned |
| 3 | GET `messages.inbox` with `status=unread` | Only unread messages returned |
| 4 | GET `messages.inbox` with both `marketplace=ebay` and `status=read` | Only eBay messages with read status returned |
| 5 | Verify stats object | Contains inbox stats from MessageSyncService::getInboxStats |
| 6 | Verify pagination | 25 messages per page, withQueryString preserves filter params |

**Notes:**
- Archived messages are excluded via `whereNot('status', 'archived')`
- Messages are eager-loaded with customer, order, and product relations

---

### TC-COM-001-02: View thread with replies

| Field | Value |
|-------|-------|
| **ID** | TC-COM-001-02 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user
2. A MarketplaceMessage with `marketplace_thread_id` set and at least 2 messages in the same thread
3. Replies (MarketplaceMessageReply) exist on one or more messages in the thread

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `messages.thread` for a message with a thread ID | 200 response, renders Communication/Thread |
| 2 | Verify threadMessages | All messages with the same marketplace_thread_id returned, ordered by received_at |
| 3 | Verify message relations | Message loaded with customer, order, product, and replies.sender |
| 4 | Verify auto-mark-read | Message status is set to read via MessageSyncService::markAsRead |
| 5 | GET thread for a message without marketplace_thread_id | Only the single message returned in threadMessages |

---

### TC-COM-001-03: Send reply via marketplace API

| Field | Value |
|-------|-------|
| **ID** | TC-COM-001-03 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user with an active eBay IntegrationCredential for the organization
2. An existing inbound MarketplaceMessage to reply to

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | POST `messages.reply` with body text (max 5000 chars) | Redirect back with success flash |
| 2 | Verify eBay API call | EbayMessageService::sendReply called with message, body, and user ID |
| 3 | Verify reply record | MarketplaceMessageReply created with body, sent_by, sent_at |
| 4 | POST reply with empty body | Validation error: body is required |
| 5 | POST reply with body exceeding 5000 chars | Validation error: body max 5000 |
| 6 | POST reply when no eBay credential exists | Reply saved locally with status=failed, message status set to replied |

**Notes:**
- Fallback behavior when no credential: reply is stored but not sent to marketplace

---

### TC-COM-001-04: Mark message as read

| Field | Value |
|-------|-------|
| **ID** | TC-COM-001-04 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user
2. An unread MarketplaceMessage in the user's organization

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | POST `messages.read` for an unread message | Redirect back with success flash |
| 2 | Verify status | Message status changed from unread to read |
| 3 | Verify read_at | read_at timestamp set to current time |
| 4 | POST mark-read on an already-read message | No error, remains read |

---

### TC-COM-001-05: Archive message

| Field | Value |
|-------|-------|
| **ID** | TC-COM-001-05 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user
2. A MarketplaceMessage in any non-archived status

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | POST `messages.archive` for the message | Redirect to messages.inbox with success flash |
| 2 | Verify status | Message status changed to archived |
| 3 | Verify inbox listing | Archived message no longer appears in inbox listing |
| 4 | Archive an already-archived message | No error, remains archived |

---

### TC-COM-001-06: Messages scoped to organization

| Field | Value |
|-------|-------|
| **ID** | TC-COM-001-06 |
| **Priority** | P0 |
| **Type** | Security |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Two organizations (Org A and Org B) each with their own messages
2. Authenticated user belonging to Org A

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `messages.inbox` as Org A user | Only Org A messages returned |
| 2 | Verify query scope | Messages filtered by organization_id derived from user's current team or organization |
| 3 | Attempt to view a thread for an Org B message | 404 or 403 response (model binding fails org scope) |

**Notes:**
- Organization resolution order: currentTeam.organization_id, then current_organization_id, then organization_id

---

### TC-COM-001-07: Unread count in badge

| Field | Value |
|-------|-------|
| **ID** | TC-COM-001-07 |
| **Priority** | P2 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user with messages in various statuses
2. At least 3 unread messages and 2 read messages

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `messages.inbox` | Stats object includes unread count |
| 2 | Mark one message as read | Unread count decrements by 1 |
| 3 | Verify badge display | Inbox page renders unread count in navigation badge |
| 4 | All messages read | Badge shows 0 or is hidden |

---

### TC-COM-001-08: Message sync from marketplace creates local record

| Field | Value |
|-------|-------|
| **ID** | TC-COM-001-08 |
| **Priority** | P0 |
| **Type** | Integration |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Organization with an active eBay IntegrationCredential
2. eBay account has unsynchronized inbound messages

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Run MessageSyncService::syncForOrganization | Returns count of newly synced messages |
| 2 | Verify record creation | MarketplaceMessage records created with status=unread, direction=inbound |
| 3 | Verify deduplication | Running sync again does not create duplicate records (checked via marketplace_message_id) |
| 4 | Verify auto-linking | Messages with marketplace_item_id linked to product via EbayListing lookup |
| 5 | Verify customer linking | Messages with sender_id linked to existing Customer record by marketplace_customer_id |
| 6 | Verify notifications | New inbound messages trigger NewMessageNotification to organization users |

**Notes:**
- Sync uses upsert logic: existing messages (by marketplace_message_id) are skipped
- Last sync time is tracked per credential to avoid re-fetching old messages
