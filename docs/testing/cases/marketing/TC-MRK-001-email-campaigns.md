# Email Campaigns Test Cases

## Document ID: TC-MRK-001

**Domain:** Marketing
**Related Test Plan:** `docs/testing/plans/marketing-test-plan.md`
**Last Updated:** 2026-04-15

---

### TC-MRK-001-01: List campaigns with stats

| Field | Value |
|-------|-------|
| **ID** | TC-MRK-001-01 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user with organization
2. Multiple EmailCampaign records exist for the organization (mix of active/inactive)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `marketing.campaigns.index` | 200 response, renders Marketing/Campaigns/Index |
| 2 | Verify campaigns list | Each campaign includes: id, name, type, type_label, trigger_event, trigger_label, trigger_delay_hours, is_active, sent_count, open_rate, click_rate, created_at |
| 3 | Verify ordering | Campaigns ordered by created_at descending |
| 4 | Verify stats object | Contains: total_campaigns, active_campaigns, total_sent, avg_open_rate |
| 5 | Verify type_label mapping | type_label resolved from EmailCampaign::TYPES constant (e.g., post_purchase_followup -> Post-Purchase Follow-up) |

---

### TC-MRK-001-02: Create campaign with trigger event

| Field | Value |
|-------|-------|
| **ID** | TC-MRK-001-02 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user with organization

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `marketing.campaigns.create` | 200 response, renders create form with types, triggers, and availableVariables |
| 2 | POST `marketing.campaigns.store` with valid data: name, type=post_purchase_followup, trigger_event=order_delivered, trigger_delay_hours=24, subject_template, body_template | Redirect to campaigns.index with success flash |
| 3 | Verify record | EmailCampaign created with correct organization_id and all fields |
| 4 | POST with missing name | Validation error: name is required |
| 5 | POST with invalid type | Validation error: type must be one of defined TYPES |
| 6 | POST with invalid trigger_event | Validation error: trigger_event must be one of defined TRIGGERS |
| 7 | POST with trigger_delay_hours > 8760 | Validation error: max 8760 hours (1 year) |

**Notes:**
- Valid types: post_purchase_followup, review_request, restock_alert, win_back
- Valid triggers: order_delivered, order_shipped, days_after_delivery

---

### TC-MRK-001-03: Update campaign

| Field | Value |
|-------|-------|
| **ID** | TC-MRK-001-03 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user
2. An existing EmailCampaign for the user's organization

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `marketing.campaigns.edit` for campaign | 200 response, renders edit form pre-filled with campaign data, types, triggers, availableVariables |
| 2 | PUT `marketing.campaigns.update` with changed name and trigger_delay_hours | Redirect to campaigns.index with success flash |
| 3 | Verify record | Campaign fields updated in database |
| 4 | PUT with invalid data | Validation errors returned, no update applied |

---

### TC-MRK-001-04: Delete campaign

| Field | Value |
|-------|-------|
| **ID** | TC-MRK-001-04 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user
2. An existing EmailCampaign

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | DELETE `marketing.campaigns.destroy` for campaign | Redirect to campaigns.index with success flash |
| 2 | Verify deletion | Campaign record deleted from database |
| 3 | Verify cascade | Associated EmailCampaignSend records handled appropriately |

---

### TC-MRK-001-05: Toggle active/inactive

| Field | Value |
|-------|-------|
| **ID** | TC-MRK-001-05 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user
2. An existing EmailCampaign with is_active = true

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | POST `marketing.campaigns.toggle` for active campaign | Redirect back with "Campaign deactivated successfully" flash |
| 2 | Verify state | is_active changed to false |
| 3 | POST toggle again | Redirect back with "Campaign activated successfully" flash |
| 4 | Verify state | is_active changed back to true |
| 5 | Verify inactive campaign | Deactivated campaign does not fire on trigger events |

---

### TC-MRK-001-06: View campaign stats

| Field | Value |
|-------|-------|
| **ID** | TC-MRK-001-06 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user
2. An EmailCampaign with existing EmailCampaignSend records (mix of sent, opened, clicked)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `marketing.campaigns.stats` for campaign | 200 response, renders Marketing/Campaigns/Stats |
| 2 | Verify campaign summary | Includes id, name, type, type_label, is_active |
| 3 | Verify stats object | Aggregated statistics from EmailCampaignService::getStats |
| 4 | Verify recentSends list | Up to 50 most recent sends, each with: customer_name, customer_email, order_number, status, sent_at, opened_at, clicked_at |
| 5 | Verify date formatting | All timestamps formatted as toDateTimeString |

---

### TC-MRK-001-07: Preview rendered template

| Field | Value |
|-------|-------|
| **ID** | TC-MRK-001-07 |
| **Priority** | P2 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user
2. An EmailCampaign with subject_template and body_template containing variable placeholders

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | POST `marketing.campaigns.preview` for campaign | JSON response with rendered HTML |
| 2 | Verify rendering | EmailTemplateRenderer::preview replaces variables with sample data |
| 3 | Verify response format | JSON contains rendered subject and body fields |

**Notes:**
- Preview uses sample/placeholder data, not a real customer record

---

### TC-MRK-001-08: Campaign trigger fires on event

| Field | Value |
|-------|-------|
| **ID** | TC-MRK-001-08 |
| **Priority** | P0 |
| **Type** | Integration |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. An active EmailCampaign with trigger_event=order_delivered and trigger_delay_hours=0
2. A customer with a valid email address linked to an order

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Trigger order_delivered event for an order | Campaign trigger job dispatched |
| 2 | Verify send record | EmailCampaignSend created with campaign_id, customer_id, order_id |
| 3 | Verify email content | Subject and body rendered with actual customer/order data |
| 4 | Trigger event with campaign inactive | No send record created, no email dispatched |
| 5 | Trigger with trigger_delay_hours=24 | Job scheduled with 24-hour delay, not immediate |

---

### TC-MRK-001-09: Send tracking (open/click/bounce)

| Field | Value |
|-------|-------|
| **ID** | TC-MRK-001-09 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. An EmailCampaignSend with status=sent

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Track open event for the send | opened_at timestamp set, campaign open_count incremented |
| 2 | Track click event for the send | clicked_at timestamp set, campaign click_count incremented |
| 3 | Track bounce event | Send status set to bounced |
| 4 | Verify open_rate calculation | EmailCampaign::openRate() returns open_count / sent_count as percentage |
| 5 | Verify click_rate calculation | EmailCampaign::clickRate() returns click_count / sent_count as percentage |
| 6 | Double open tracking | opened_at not overwritten, open_count not double-counted |

---

### TC-MRK-001-10: Organization isolation

| Field | Value |
|-------|-------|
| **ID** | TC-MRK-001-10 |
| **Priority** | P0 |
| **Type** | Security |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Two organizations (Org A, Org B) each with campaigns
2. User authenticated as Org A member

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `marketing.campaigns.index` as Org A user | Only Org A campaigns returned |
| 2 | Verify query | Campaigns filtered by organization_id from authenticated user |
| 3 | Attempt to edit an Org B campaign directly via URL | 404 or 403 (model binding does not resolve cross-org) |
| 4 | POST create campaign | Campaign created with Org A's organization_id, not spoofable via request |

**Notes:**
- Organization ID is derived server-side from the authenticated user, never from request input
