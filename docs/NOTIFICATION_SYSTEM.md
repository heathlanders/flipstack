# Notification System

Notifications use a **per-org rules engine** (`notification_rules` + `notification_rule_recipients` tables) plus a **central event registry** (`config/notifications-events.php`). Domain code dispatches **events**, not notification classes — recipients, channels, dedup, and escalation are resolved per-org from rules (or registry defaults).

## Dispatching an event

```php
app(\App\Services\NotificationService::class)->dispatchEvent(
    eventKey: 'procurement.po.overdue',
    organizationId: (int) $po->organization_id,
    args: [(string) $po->id, (string) $po->po_number, $supplierName, $expectedDate, $daysLate],
    context: ['order' => ['buyer_email' => $order->buyer_email]], // for customer recipient resolution
    relatedId: (string) $po->id, // dedup key suffix
);
```

`args` are passed to the notification class constructor. `relatedId` keys the dedup window — same `(org, event_key, relatedId)` within the rule's `dedup_minutes` is suppressed (Cache::add atomic).

## Adding a new event

1. Create a notification class in `app/Notifications/<Domain>/` extending `BaseNotification` (or `BaseBuyerNotification` for buyer-facing). Implement `getPriority`, `getCategory`, `getTitle`, `getMessage`, `getActionUrl`, optionally `toMail`.

2. Register in `config/notifications-events.php`:
   ```php
   'my.event.key' => [
       'class' => \App\Notifications\Domain\MyNotification::class,
       'priority' => 'high',
       'category' => 'orders',
       'description' => 'Human label for the rules UI',
       'default_recipients' => [
           ['type' => 'role', 'value' => 'organization_owner', 'step' => 0],
       ],
       'dedup_minutes' => 60,
   ],
   ```

3. Call `dispatchEvent('my.event.key', ...)` in the domain code.

## Recipient types

| type | value | resolves to |
|---|---|---|
| `user` | user id | the User model |
| `role` | role slug | all org users with that role |
| `external_email` | email address | `Notification::route('mail', email)` |
| `supplier` | supplier id | supplier's email via `Notification::route` |
| `customer` | context path like `order.buyer_email` | resolved from `dispatchEvent($context)` |

## Buyer-facing notifications

`BaseBuyerNotification` (in `app/Notifications/Customer/`) ignores user preferences (no User to look up). Channel is fixed to `mail` and gated by:
- `OrganizationSettings.customer_notifications_enabled` (default true)
- `OrganizationSettings.customer_notifications_marketplace_overrides` (defaults: `ebay`, `shopify`, `amazon` suppressed — they send their own buyer emails)

Buyer classes: `OrderShippedToBuyer`, `RefundProcessedToBuyer`, `ReturnApprovedToBuyer`.

## Event registry (24 events as of 2026-05-04)

Failure / sync errors:
`ebay.listing.sync_failed`, `shopify.product.sync_failed`, `intake.item.failed`, `pricing.rule.error`, `communication.message.sync_failed`

Procurement:
`procurement.po.created`, `procurement.po.submitted`, `procurement.po.received`, `procurement.po.overdue`

Fulfillment internal:
`fulfillment.order.shipped`, `fulfillment.return.approved`, `fulfillment.return.received`, `fulfillment.refund.processed`, `fulfillment.routing.failed`

Buyer-facing:
`fulfillment.order.shipped_to_buyer`, `fulfillment.return.approved_to_buyer`, `fulfillment.refund.processed_to_buyer`

Analytics insights:
`analytics.health_score.alert`, `analytics.stockout.warning`, `analytics.reorder.suggestions`, `pricing.guard.hit`

Security:
`security.login.threshold_breached`, `security.login.new_device`, `security.role.changed`

## Escalation

Set `escalation_minutes` on a `NotificationRule` row plus recipients at `escalation_step > 0`. After dispatch, `EscalateNotificationJob` runs at the delay; if step N has no read receipt, step N+1 fires. Loops until no more recipients exist for the next step.

## Owner UI

- `/notifications/rules` — per-org override editor (active/recipients/dedup/escalation per event)
- `/notifications/metrics` — 30-day delivery/open/click/failure rates from `notification_logs`
- `/notifications/preferences` — per-user channel toggles (still active for in-app/email/SMS choice)

## Channels

| channel | how it ships |
|---|---|
| `database` | `notifications` table, read by bell |
| `broadcast` | Pusher/Reverb (real-time bell across tabs) |
| `mail` | configured Laravel mailer |
| `sms` | `App\Notifications\Channels\SmsChannel` (Twilio) — registered as `'sms'` alias in `AppServiceProvider` |
| `fcm` | `App\Notifications\Channels\FcmChannel` (mobile push) — registered as `'fcm'` alias |

`BaseNotification::via()` reads `NotificationPreferenceService`. It appends `fcm` automatically when `push_enabled` AND user has an active row in `device_tokens`.

## Mobile FCM

- Table `device_tokens` (user_id, organization_id, fcm_token UNIQUE, platform, last_seen_at, revoked_at)
- `App\Services\Notification\Fcm\FcmDispatcher` — raw HTTP to FCM v1 API. Auth via service account JSON, JWT-signed with `openssl_sign` (no SDK dependency). Access token cached 50min.
- Required env: `FCM_PROJECT_ID`, `FCM_CREDENTIALS_PATH` (path to service account JSON, mount via volume in production)
- Endpoints: `POST /api/auth/fcm-token` (register/refresh), `DELETE /api/auth/fcm-token` (revoke)
- Flutter side: `mobile/lib/core/notifications/push_notification_service.dart` — registers token on cold start + refresh, surfaces messages via `FcmActionNotifier` ValueNotifier (consumer page navigates from `data.action_url`), revokes on logout

## Digest emails

- `App\Mail\NotificationDigestMail` + `resources/views/emails/notifications/digest.blade.php`
- `SendNotificationDigestJob` reads `users.notification_settings->digest_frequency` (JSON column)
- Schedules: hourly, daily 09:00, weekly Mon 09:00
- `CleanupOldNotificationsJob` runs daily 03:00 (deletes read >30d, expired)

## Priority levels & categories

Priorities: `critical`, `high`, `medium`, `low` (red/orange/blue/green border in UI).
Categories: `orders`, `inventory`, `financial`, `platform`, `customer`, `system`.

## Frontend

| Component | Location |
|---|---|
| `NotificationBell.vue` | `resources/js/Components/` |
| `NotificationToast.vue` | `resources/js/Components/` |
| Inbox | `Pages/Notifications/Index.vue` |
| Preferences | `Pages/Notifications/Preferences.vue` |
| Rules editor | `Pages/Settings/Notifications/Rules.vue` |
| Metrics | `Pages/Settings/Notifications/Metrics.vue` |

## Database schema

| Table | Purpose |
|---|---|
| `notifications` | Laravel built-in + priority/category/tenant_id/action_url/expires_at |
| `notification_logs` | Per-channel delivery tracking (sent/delivered/opened/clicked/failed) |
| `notification_templates` | Per-tenant/channel templates with variable substitution |
| `notification_rules` | Per-org event overrides (active, recipients, channels, dedup, escalation) |
| `notification_rule_recipients` | Recipient rows for a rule (type, value, escalation_step) |
| `device_tokens` | FCM tokens per user (replaces 30-day Redis cache) |

## Files

| Concern | File |
|---|---|
| Event registry | `config/notifications-events.php` |
| Service entry | `app/Services/NotificationService.php` |
| Dispatcher | `app/Services/NotificationDispatch/EventDispatcher.php` |
| Recipient resolver | `app/Services/NotificationDispatch/RecipientResolver.php` |
| Dedup | `app/Services/NotificationDispatch/Dedup.php` |
| Escalation | `app/Jobs/Notification/EscalateNotificationJob.php` |
| Base internal | `app/Notifications/BaseNotification.php` |
| Base buyer | `app/Notifications/Customer/BaseBuyerNotification.php` |
| Rules controller | `app/Http/Controllers/Notification/NotificationRuleController.php` |
| Metrics controller | `app/Http/Controllers/Notification/NotificationMetricsController.php` |
| FCM channel | `app/Notifications/Channels/FcmChannel.php` |
| FCM dispatcher | `app/Services/Notification/Fcm/FcmDispatcher.php` |
| SMS channel | `app/Notifications/Channels/SmsChannel.php` |
| Channel aliases | `app/Providers/AppServiceProvider.php` (`sms`, `fcm` registered via ChannelManager) |
| Schedules | `routes/console.php` |
| Digest job | `app/Jobs/SendNotificationDigestJob.php` |
| Cleanup job | `app/Jobs/CleanupOldNotificationsJob.php` |
| Models | `app/Models/Notification/{NotificationRule,NotificationRuleRecipient,DeviceToken,NotificationLog,NotificationTemplate}.php` |

## Verification

```bash
# All registered classes resolve
docker exec ecommerce_app php artisan tinker --execute="foreach(config('notifications-events.events') as \$k=>\$c) if(!class_exists(\$c['class'])) echo \"MISSING: \$k\n\";"

# Schedules registered
docker exec ecommerce_app php artisan schedule:list | grep notification

# Tests
docker exec ecommerce_app php artisan test
```

## Migrating legacy notifications to the rules engine

Pre-overhaul notifications still hardcode recipient resolution (e.g. `User::where(...)->whereHas('roles', ...)`). Migrate them when touching surrounding code:

1. Add the notification class to `config/notifications-events.php` with `default_recipients`
2. Replace `$users->each->notify(new X(...))` with `dispatchEvent('event.key', $orgId, $args)`
3. Verify the same users still get notified (default recipients should match)

Legacy notifications still using direct dispatch (not regressions, just not yet migrated):
`OrderSyncFailedNotification`, `TrackingUploadFailedNotification`, `NewOrderNotification`, `PaymentFailedNotification`, `LowStockNotification`, `BulkOperationCompletedNotification`, `MarketplaceInventorySyncFailedNotification`, `LabelingWorkOrderCreated`, `ShopifySyncNotification`, `EbayImportCompleted`, `EbayListingCompleted`, `NewMessageNotification`, `OAuthTokenFailedNotification`, `UserInvitationNotification`, `WelcomeToOrganizationNotification`.

## Deferred work
- **Phase 8b** — polymorphic `notification_subscriptions` table for arbitrary model-watching ("subscribe me to product X")
- Web push (Service Worker browser notifications)
- Retry/fallback chain for failed deliveries (currently best-effort only)

## History
- 2026-05-04: Major overhaul — rules engine, 17 new event notifications, FCM mobile push end-to-end, digest mail rebuilt, Twilio/sms/fcm channel registration, metrics dashboard. See [memory entry](../.claude/projects/-home-user-ecommerce-saas/memory/project_notification_system_overhaul.md) for full context.
