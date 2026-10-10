# Notification Preferences Test Cases

## Document ID: TC-NTF-002

**Domain:** Notifications
**Related Test Plan:** `docs/testing/plans/notifications-test-plan.md`
**Last Updated:** 2026-04-15

---

### TC-NTF-002-01: View notification preferences

| Field | Value |
|-------|-------|
| **ID** | TC-NTF-002-01 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user with existing `notification_settings` JSON column on the users table (or null for defaults)
2. NotificationPreferenceService available

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `GET` request to `route('notifications.preferences')` | Response status 200, Inertia renders preferences page |
| 2 | Assert preferences include global channel flags: `email_enabled`, `sms_enabled`, `push_enabled`, `database_enabled` | Channel toggle states returned |
| 3 | Assert preferences include `category_preferences` with keys: `orders`, `inventory`, `financial`, `platform`, `customer`, `system` | Per-category channel settings |
| 4 | Assert each category has `email`, `push`, `sms`, `database` boolean flags | Granular channel control per category |
| 5 | Assert digest settings: `digest_frequency` (one of: realtime, hourly, daily, weekly) and `digest_time` | Digest scheduling preferences |
| 6 | Assert quiet hours settings: `quiet_hours_enabled`, `quiet_start`, `quiet_end` | Quiet hours configuration |

**Notes:**
- Route: `GET /notifications/preferences` named `notifications.preferences`
- NotificationPreferenceController delegates to `NotificationPreferenceService::getPreferences($user)`
- Preferences are stored in the `notification_settings` JSON column on the `users` table
- If `notification_settings` is null, `getDefaultPreferences()` provides defaults
- Preferences are cached for 24 hours via `Cache::remember("notification_preferences:{$user->id}", ...)`
- Default `push_enabled` is `false` (TODO note in code: "Enable after configuring broadcast")

---

### TC-NTF-002-02: Update email notification preferences

| Field | Value |
|-------|-------|
| **ID** | TC-NTF-002-02 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user with default preferences (email_enabled = true)
2. NotificationPreferenceService available

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` to `route('notifications.preferences.update')` with `email_enabled = false` | Redirect back with success |
| 2 | Assert user's `notification_settings` in database has `email_enabled = false` | JSON column updated |
| 3 | Assert other settings remain unchanged | `array_merge` preserves existing values |
| 4 | Call `NotificationPreferenceService::getEnabledChannels($user, 'orders')` | `'mail'` channel is NOT included in returned array |
| 5 | Re-enable: POST with `email_enabled = true` | Setting toggled back |
| 6 | Assert cache was cleared | `Cache::forget("notification_preferences:{$user->id}")` called after update |

**Notes:**
- Route: `POST /notifications/preferences` named `notifications.preferences.update`
- `updatePreferences()` merges new values with existing settings via `array_merge()`
- Cache is explicitly cleared after update to prevent stale reads
- The `getEnabledChannels()` method checks `$preferences['email_enabled']` before adding `'mail'` to channels

---

### TC-NTF-002-03: Update in-app notification preferences

| Field | Value |
|-------|-------|
| **ID** | TC-NTF-002-03 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user with default preferences (database_enabled = true, push_enabled = false)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` to `route('notifications.preferences.update')` with `push_enabled = true` | Redirect back with success |
| 2 | Assert `notification_settings.push_enabled` is `true` in database | Push notifications enabled |
| 3 | Call `getEnabledChannels($user, 'orders')` | `'broadcast'` channel now included (assuming `push` is enabled for the `orders` category) |
| 4 | Update category-specific: POST with `category_preferences.orders.push = false` | Per-category override saved |
| 5 | Call `getEnabledChannels($user, 'orders')` after category update | `'broadcast'` NOT included for `orders` category specifically |
| 6 | Call `getEnabledChannels($user, 'inventory')` | `'broadcast'` IS included for `inventory` (still using global default) |

**Notes:**
- `push_enabled` controls whether broadcast channel is added globally
- `category_preferences.{category}.push` overrides at category level via `isChannelEnabledForCategory()`
- `database_enabled` controls the `'database'` channel which stores notifications in the DB
- `database_enabled = true` is the default and should always be on for the notification system to function

---

### TC-NTF-002-04: Disable notifications for category

| Field | Value |
|-------|-------|
| **ID** | TC-NTF-002-04 |
| **Priority** | P2 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user with default preferences (all categories enabled for database channel)
2. User has `email_enabled = true` globally

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` with `category_preferences.financial.email = false` and `category_preferences.financial.push = false` | Category preferences updated |
| 2 | Call `getEnabledChannels($user, 'financial')` | Returns only `['database']` -- email and broadcast excluded for `financial` category |
| 3 | Call `getEnabledChannels($user, 'orders')` | Returns `['database', 'mail']` -- orders category unaffected |
| 4 | Verify database: `notification_settings.category_preferences.financial.email` is `false` | Stored correctly in JSON column |
| 5 | Re-enable: POST with `category_preferences.financial.email = true` | Financial email notifications re-enabled |
| 6 | Call `getEnabledChannels($user, 'financial')` | `'mail'` channel now included again |

**Notes:**
- Six categories are supported: `orders`, `inventory`, `financial`, `platform`, `customer`, `system`
- `isChannelEnabledForCategory()` checks `$categoryPrefs[$category][$channel]` and defaults to `true` if not set
- Disabling all channels for a category does not prevent the notification from being created in the database (if `database_enabled` is true globally)
- The default preferences set all category channels to `false` except `database`

---

### TC-NTF-002-05: Preferences scoped to user

| Field | Value |
|-------|-------|
| **ID** | TC-NTF-002-05 |
| **Priority** | P0 |
| **Type** | Security |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. User A with custom preferences (email_enabled = false, quiet_hours_enabled = true)
2. User B with default preferences (email_enabled = true)
3. Both users in the same organization

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Authenticate as User A, GET preferences | Shows User A's custom settings: `email_enabled = false`, `quiet_hours_enabled = true` |
| 2 | Authenticate as User B, GET preferences | Shows User B's defaults: `email_enabled = true`, `quiet_hours_enabled = false` |
| 3 | As User B, update `email_enabled = false` | User B's setting changed; User A's unchanged |
| 4 | Verify User A's DB `notification_settings` still has `email_enabled = false` | No cross-user contamination |
| 5 | Call `getEnabledChannels($userA, 'orders')` | Returns channels based on User A's preferences |
| 6 | Call `getEnabledChannels($userB, 'orders')` | Returns channels based on User B's preferences |

**Notes:**
- Preferences are stored per-user in the `notification_settings` column on the `users` table
- Cache key includes user ID: `notification_preferences:{$user->id}` preventing cache collisions
- The `getPreferences()` method loads settings from the specific `$user` model instance
- Preferences are user-level, not organization-level -- users within the same org have independent settings

---

### TC-NTF-002-06: Default preferences set on user creation

| Field | Value |
|-------|-------|
| **ID** | TC-NTF-002-06 |
| **Priority** | P2 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. A newly created user (via factory or registration) with `notification_settings = null`

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create a new user with `notification_settings = null` | User record created |
| 2 | Call `NotificationPreferenceService::getPreferences($newUser)` | Returns default preferences from `getDefaultPreferences()` |
| 3 | Assert defaults: `email_enabled = true`, `sms_enabled = false`, `push_enabled = false`, `database_enabled = true` | Global channel defaults correct |
| 4 | Assert default `digest_frequency = 'realtime'` and `digest_time = '09:00'` | Digest defaults correct |
| 5 | Assert default `quiet_hours_enabled = false` with `quiet_start = '22:00'`, `quiet_end = '08:00'` | Quiet hours defaults correct |
| 6 | Assert default `min_priority = 'low'` | All priority levels shown by default |

**Notes:**
- When `notification_settings` is null or empty, `getPreferences()` calls `array_merge($this->getDefaultPreferences(), $settings)` with `$settings = []`
- The defaults are defined in `NotificationPreferenceService::getDefaultPreferences()`
- Default category preferences set all channels to `false` except `database = true` for each category
- `resetToDefaults()` method exists to restore a user's preferences to the default state
- There is no observer or boot method that auto-populates `notification_settings` on user creation -- defaults are applied at read time via `array_merge`
