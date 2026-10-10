---
name: email-marketing
description: Automated email campaigns with trigger-based sending, template rendering, and send tracking (open/click/bounce/unsubscribe)
user-invocable: false
---

# Email Marketing

## Key Files

| Area | File | Purpose |
|------|------|---------|
| **Models** | `app/Models/Marketing/EmailCampaign.php` | Campaign definition: type, trigger, templates, aggregate stats |
| | `app/Models/Marketing/EmailCampaignSend.php` | Individual send record per customer/order with delivery tracking |
| **Services** | `app/Services/Marketing/EmailCampaignService.php` | Campaign CRUD, stats aggregation, eligible order finder |
| | `app/Services/Marketing/EmailTemplateRenderer.php` | Mustache-style `{{variable}}` replacement, variable builder, preview |
| **Controllers** | `app/Http/Controllers/Marketing/EmailCampaignController.php` | Full CRUD, toggle, stats dashboard, preview endpoint |
| **Jobs** | `app/Jobs/Marketing/ProcessEmailCampaignTriggers.php` | Scans all active campaigns for eligible orders, queues sends |
| | `app/Jobs/Marketing/SendCampaignEmail.php` | Renders and sends a single campaign email (placeholder for mail provider) |
| **Routes** | `routes/web.php` (marketing/campaigns prefix) | Campaign management routes |
| **Pages** | `resources/js/Pages/Marketing/Campaigns/` | Index, Create, Edit, Stats Vue pages |

## Campaign Types

| Type | Constant | Label | Use Case |
|------|----------|-------|----------|
| `post_purchase_followup` | `TYPE_POST_PURCHASE_FOLLOWUP` | Post-Purchase Follow-up | Thank-you or care instructions after purchase |
| `review_request` | `TYPE_REVIEW_REQUEST` | Review Request | Ask buyer to leave a review |
| `restock_alert` | `TYPE_RESTOCK_ALERT` | Restock Alert | Notify past buyers when item is back in stock |
| `win_back` | `TYPE_WIN_BACK` | Win Back | Re-engage inactive buyers |

## Trigger Events

| Trigger | Constant | Label | How Eligible Orders Are Found |
|---------|----------|-------|-------------------------------|
| `order_shipped` | `TRIGGER_ORDER_SHIPPED` | Order Shipped | `status = 'shipped'` AND `updated_at <= now - delay_hours` |
| `order_delivered` | `TRIGGER_ORDER_DELIVERED` | Order Delivered | `status = 'delivered'` AND `updated_at <= now - delay_hours` |
| `days_after_delivery` | `TRIGGER_DAYS_AFTER_DELIVERY` | Days After Delivery | `status = 'delivered'` AND `updated_at <= now - delay_hours` |

The `trigger_delay_hours` field (0-8760) controls how long after the trigger event before the email is sent.

## Trigger Engine Flow

1. `ProcessEmailCampaignTriggers` job runs (tries: 1, timeout: 300s)
2. Iterates all `EmailCampaign::active()` campaigns
3. For each campaign, `EmailCampaignService::findEligibleOrders()` finds orders matching the trigger that have not already been sent
4. Creates an `EmailCampaignSend` record with `status = pending` for each eligible order
5. Dispatches `SendCampaignEmail` job for each send record
6. Already-sent orders are excluded via `campaign.sends().pluck('order_id')`
7. Max 100 eligible orders per campaign per run

## Send Job Flow

1. `SendCampaignEmail` job runs (tries: 3, backoff: [60, 120, 300]s, timeout: 30s)
2. Loads `EmailCampaignSend` with campaign, customer, and order
3. Skips if send is not `pending` or campaign is not active
4. Builds template variables from order via `EmailTemplateRenderer::buildVariablesForOrder()`
5. Renders subject and body templates with `{{variable}}` replacement
6. Sends email (currently placeholder -- logs instead of calling mail provider)
7. Updates send status to `sent`, sets `sent_at`, increments campaign `sent_count`
8. On failure: updates send status to `bounced`

## Template Variables

| Variable | Source |
|----------|--------|
| `{{customer_name}}` | `customer.name` or "Valued Customer" |
| `{{customer_first_name}}` | First word of customer name |
| `{{customer_email}}` | `customer.email` |
| `{{order_number}}` | `order.order_number` or `marketplace_order_id` |
| `{{order_total}}` | Formatted `order.total` |
| `{{order_date}}` | `order.created_at` formatted as "M j, Y" |
| `{{product_name}}` | First order item product name |
| `{{product_sku}}` | First order item SKU |
| `{{tracking_url}}` | `order.tracking_url` |
| `{{tracking_number}}` | `order.tracking_number` |
| `{{store_name}}` | `order.organization.name` |
| `{{unsubscribe_url}}` | Placeholder `#unsubscribe` |

## EmailCampaign Fields

| Field | Type | Purpose |
|-------|------|---------|
| `organization_id` | string | Org scope |
| `name` | string | Campaign name |
| `type` | string | One of the 4 campaign types |
| `trigger_event` | string | What triggers the email |
| `trigger_delay_hours` | integer | Hours to wait after trigger event |
| `subject_template` | string (max 500) | Email subject with `{{variables}}` |
| `body_template` | text (max 65535) | Email body with `{{variables}}` |
| `is_active` | boolean | Only active campaigns are processed |
| `sent_count` | integer | Running total of emails sent |
| `open_count` | integer | Running total of opens tracked |
| `click_count` | integer | Running total of clicks tracked |

## EmailCampaignSend Statuses

| Status | Meaning |
|--------|---------|
| `pending` | Queued for sending |
| `sent` | Email dispatched to mail provider |
| `delivered` | Confirmed delivery |
| `opened` | Open tracking pixel fired |
| `clicked` | Link click tracked |
| `bounced` | Delivery failed or job failed |
| `unsubscribed` | Recipient unsubscribed |

## Campaign Stats

`EmailCampaignService::getStats()` returns per-campaign metrics:
- Counts by status: pending, sent, delivered, opened, clicked, bounced, unsubscribed
- Computed rates: `open_rate`, `click_rate`, `bounce_rate` (as percentages)
- `total_sent` = sent + delivered + opened + clicked

## Routes

| Method | URI | Name | Action |
|--------|-----|------|--------|
| GET | `/marketing/campaigns` | `marketing.campaigns.index` | List campaigns with stats |
| GET | `/marketing/campaigns/create` | `marketing.campaigns.create` | Create form |
| POST | `/marketing/campaigns` | `marketing.campaigns.store` | Store campaign |
| GET | `/marketing/campaigns/{campaign}/edit` | `marketing.campaigns.edit` | Edit form |
| PUT | `/marketing/campaigns/{campaign}` | `marketing.campaigns.update` | Update campaign |
| DELETE | `/marketing/campaigns/{campaign}` | `marketing.campaigns.destroy` | Delete campaign |
| POST | `/marketing/campaigns/{campaign}/toggle` | `marketing.campaigns.toggle` | Toggle active |
| GET | `/marketing/campaigns/{campaign}/stats` | `marketing.campaigns.stats` | Stats dashboard with recent sends |
| POST | `/marketing/campaigns/{campaign}/preview` | `marketing.campaigns.preview` | Render preview with placeholder values |

## Unsubscribe Compliance

- The `{{unsubscribe_url}}` variable is included in available template variables
- Currently a placeholder (`#unsubscribe`) -- requires implementation with a signed URL and unsubscribe handler
- `EmailCampaignSend` tracks `unsubscribed` status for compliance reporting

## Business Rules

1. Campaigns only fire for `is_active = true`
2. Each order receives at most one email per campaign (dedup via `sends.order_id`)
3. Max 100 orders processed per campaign per trigger run
4. Failed sends are marked as `bounced` via the job's `failed()` method
5. `sent_count` is incremented on the campaign model after each successful send
6. Preview mode uses placeholder descriptions when no sample order is provided

## Debugging Commands

```php
// List campaigns with stats
EmailCampaign::where('organization_id', $orgId)
  ->get(['id', 'name', 'type', 'trigger_event', 'trigger_delay_hours', 'is_active', 'sent_count', 'open_count', 'click_count']);

// Get detailed stats for a campaign
$service = app(\App\Services\Marketing\EmailCampaignService::class);
$service->getStats(EmailCampaign::find($campaignId));

// Find eligible orders for a campaign (dry run)
$campaign = EmailCampaign::find($campaignId);
$service = app(\App\Services\Marketing\EmailCampaignService::class);
$service->findEligibleOrders($campaign);

// Recent sends with status
EmailCampaignSend::where('campaign_id', $campaignId)
  ->with(['customer:id,name,email', 'order:id,order_number'])
  ->orderByDesc('created_at')
  ->take(20)
  ->get(['id', 'customer_id', 'order_id', 'status', 'sent_at', 'opened_at', 'clicked_at']);

// Send status distribution for a campaign
EmailCampaignSend::where('campaign_id', $campaignId)
  ->selectRaw("status, COUNT(*) as count")
  ->groupBy('status')
  ->pluck('count', 'status');

// Preview a campaign template
$renderer = app(\App\Services\Marketing\EmailTemplateRenderer::class);
$renderer->preview(EmailCampaign::find($campaignId));
```

## Audit Logging

| Action | Category | Severity | Metadata keys |
|--------|----------|----------|---------------|
| `campaign.created` | marketing | info | trigger_event, campaign_type |
| `campaign.activated` | marketing | info | |
| `campaign.paused` | marketing | info | pause_reason |
| `campaign.sent` | marketing | info | recipient_count, source: cron |
| `email.sent` | marketing | debug | campaign_id, customer_id |
| `email.bounced` | marketing | warning | bounce_type (hard|soft), campaign_id |
| `email.opened` | marketing | debug | campaign_id |
| `email.clicked` | marketing | debug | campaign_id, link_url |
| `email.unsubscribed` | marketing | info | campaign_id |

Trait: models in this feature use `LogsAudit` (`app/Traits/LogsAudit.php`) for auto CRUD diff. Domain lifecycle events emitted from `app/Services/Marketing/EmailCampaignService`, `SendEmailJob` (wrap in `withAuditActor`); `EmailCampaign` model (`LogsAudit`) via `UserActivityLog::log(action, desc, $user, $metadata, $severity, $category)`.

Aggregate email.* events sparingly -- use debug severity and consider sampling for high-volume sends.

Spec: `docs/AUDIT_SYSTEM.md`. Enforced rule: `.claude/rules/audit-logging.md`. Tests: `use Tests\Support\AssertsAudits;` then `$this->assertAudited('action', $user, [...])`.
