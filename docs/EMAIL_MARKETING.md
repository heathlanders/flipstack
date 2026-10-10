# Email Marketing System

## Overview

The email marketing system sends automated, trigger-based email campaigns to customers based on order lifecycle events. Campaigns are configured with a trigger event (e.g., order shipped, order delivered), a delay period, and a template with variable substitution. The system finds eligible orders that match the trigger criteria, renders personalized emails, and tracks delivery, opens, and clicks.

---

## Architecture

### Key Files

| File | Purpose |
|------|---------|
| `app/Models/Marketing/EmailCampaign.php` | Campaign model with type/trigger constants and engagement metrics |
| `app/Models/Marketing/EmailCampaignSend.php` | Individual send record linking campaign to customer and order |
| `app/Services/Marketing/EmailCampaignService.php` | Campaign CRUD, eligibility checking, and statistics |
| `app/Services/Marketing/EmailTemplateRenderer.php` | Template variable substitution and preview rendering |
| `app/Jobs/Marketing/` | Background jobs for campaign triggers and email sends |
| `app/Http/Controllers/Marketing/` | Campaign management controllers |

---

## Data Model

### `email_campaigns`

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid | Primary key |
| `organization_id` | uuid | FK to organizations |
| `name` | string | Campaign name |
| `type` | string | `post_purchase_followup`, `review_request`, `restock_alert`, `win_back` |
| `trigger_event` | string | `order_shipped`, `order_delivered`, `days_after_delivery` |
| `trigger_delay_hours` | integer | Hours to wait after trigger before sending |
| `subject_template` | string | Email subject with `{{variable}}` placeholders |
| `body_template` | text | Email body with `{{variable}}` placeholders |
| `is_active` | boolean | Whether the campaign is active |
| `sent_count` | integer | Denormalized total sends |
| `open_count` | integer | Denormalized total opens |
| `click_count` | integer | Denormalized total clicks |

### `email_campaign_sends`

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid | Primary key |
| `campaign_id` | uuid | FK to email_campaigns |
| `customer_id` | uuid | FK to customers |
| `order_id` | uuid | FK to orders (the order that triggered this send) |
| `status` | string | `pending`, `sent`, `delivered`, `opened`, `clicked`, `bounced`, `unsubscribed` |
| `sent_at` | datetime | When the email was sent |
| `opened_at` | datetime | When the email was opened |
| `clicked_at` | datetime | When a link was clicked |

---

## Key Flows

### Campaign Trigger Engine

A scheduled job periodically checks all active campaigns and finds eligible orders:

```
Scheduler triggers campaign check job
    |
    v
For each active EmailCampaign:
    |
    EmailCampaignService::findEligibleOrders(campaign)
        |
        Query orders matching trigger_event:
            order_shipped  -> status = 'shipped', updated_at <= now - delay_hours
            order_delivered -> status = 'delivered', updated_at <= now - delay_hours
            days_after_delivery -> status = 'delivered', updated_at <= now - delay_hours
        |
        Exclude orders that already have a send record for this campaign
        |
        Limit to 100 per batch
    |
    v
For each eligible order:
    |
    Create EmailCampaignSend (status = 'pending')
    |
    EmailTemplateRenderer::buildVariablesForOrder(order)
    |
    EmailTemplateRenderer::render(template, variables)
    |
    Dispatch email send job
```

### Campaign Types

| Type | Description |
|------|-------------|
| `post_purchase_followup` | Thank-you or care instructions after purchase |
| `review_request` | Ask customer for a product review |
| `restock_alert` | Notify past buyers when a product is back in stock |
| `win_back` | Re-engage customers who have not purchased recently |

### Template Variable System

Templates use `{{variable_name}}` syntax. The `EmailTemplateRenderer` provides these variables:

| Variable | Source |
|----------|--------|
| `{{customer_name}}` | `Customer.name` |
| `{{customer_first_name}}` | First word of `Customer.name` |
| `{{customer_email}}` | `Customer.email` |
| `{{order_number}}` | `Order.order_number` or `marketplace_order_id` |
| `{{order_total}}` | `Order.total` formatted to 2 decimals |
| `{{order_date}}` | `Order.created_at` formatted as `M j, Y` |
| `{{product_name}}` | First order item's product name |
| `{{product_sku}}` | First order item's product SKU |
| `{{tracking_url}}` | `Order.tracking_url` |
| `{{tracking_number}}` | `Order.tracking_number` |
| `{{store_name}}` | `Organization.name` |
| `{{unsubscribe_url}}` | Unsubscribe link |

### Preview System

`EmailTemplateRenderer::preview(campaign, sampleOrder?)` renders the template with either real order data or placeholder descriptions (e.g., `[Full name of the customer]`), allowing staff to preview before activating.

### Engagement Tracking

Each `EmailCampaignSend` tracks status progression:

```
pending -> sent -> delivered -> opened -> clicked
                            \-> bounced
                            \-> unsubscribed
```

Campaign-level metrics are computed by `EmailCampaignService::getStats()`:
- Open rate = opened / total_sent * 100
- Click rate = clicked / total_sent * 100
- Bounce rate = bounced / total_sent * 100

The `EmailCampaign` model also has `openRate()` and `clickRate()` convenience methods using denormalized counters.

---

## Integration Points

- **Order Lifecycle**: Campaigns trigger based on `Order.status` transitions (shipped, delivered). The trigger delay is measured from `Order.updated_at`.
- **Customer Records**: Each send links to a `Customer` via the order relationship. Unsubscribe status should be tracked per customer.
- **Background Jobs**: `app/Jobs/Marketing/` contains jobs for campaign trigger evaluation and individual email sending. These run via the queue worker.
- **Routes**: Campaign management routes are in `routes/web.php`, handled by controllers in `app/Http/Controllers/Marketing/`.
- **Frontend**: `resources/js/Pages/Marketing/` contains campaign list, editor, and statistics views.
- **Unsubscribe Compliance**: The `{{unsubscribe_url}}` variable must be included in all campaign templates. The current implementation uses a placeholder URL -- production use requires a real unsubscribe endpoint that updates customer preferences.
