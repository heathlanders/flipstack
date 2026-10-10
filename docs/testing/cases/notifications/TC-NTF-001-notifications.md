# Notification Management Test Cases

## Document ID: TC-NTF-001

**Domain:** Notifications
**Related Test Plan:** `docs/testing/plans/notifications-test-plan.md`
**Last Updated:** 2026-04-15

---

### TC-NTF-001-01: List notifications paginated

| Field | Value |
|-------|-------|
| **ID** | TC-NTF-001-01 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user with notifications in the `notifications` table (Laravel's polymorphic notification system)
2. At least 25 notifications exist for the user across various categories and read/unread states
3. NotificationService injected into NotificationController

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `GET` request to `route('notifications.index')` | Response status 200, Inertia renders `Notifications/Index` |
| 2 | Assert `notifications` prop is a paginated collection with `data`, `current_page`, `per_page` keys | Paginated at 20 per page |
| 3 | Assert notifications are ordered by `created_at desc` | Most recent notifications first |
| 4 | Assert `unreadCount` prop is returned from `NotificationService::getUnreadCount()` | Integer count of unread notifications |
| 5 | Send `GET` with `?filter=unread` | Only notifications where `read_at IS NULL` are returned |
| 6 | Send `GET` with `?category=orders` | Only notifications where `data->category = 'orders'` are returned |
| 7 | Assert `filters` prop echoes back `filter` and `category` values | Filter state preserved for frontend |

**Notes:**
- Notifications use Laravel's built-in `$user->notifications()` morphMany relationship
- Category filter uses JSON column query: `data->category` (PostgreSQL JSONB)
- The `unread` filter applies `whereNull('read_at')` scope
- Pagination preserves query string parameters

---

### TC-NTF-001-02: Recent notifications endpoint returns 5 + unread count

| Field | Value |
|-------|-------|
| **ID** | TC-NTF-001-02 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user with at least 10 notifications (mix of read and unread)
2. NotificationService correctly configured

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `GET` request to `route('notifications.api.recent')` | Response status 200, JSON response |
| 2 | Assert response JSON has `notifications` key with exactly 5 items | `NotificationService::getRecent($user, 5)` limits to 5 |
| 3 | Assert response JSON has `unreadCount` key with integer value | Unread count matches `NotificationService::getUnreadCount()` |
| 4 | Assert notifications are ordered by recency | Most recent first |
| 5 | Create a new notification, re-request | New notification appears at top of list, `unreadCount` increments by 1 |

**Notes:**
- This endpoint powers the notification bell dropdown in the AppLayout header
- Returns JSON (not Inertia) since it is called via AJAX from the frontend
- The `recent` method uses `Auth::user()` to identify the current user
- Route: `GET /notifications/api/recent` named `notifications.api.recent`

---

### TC-NTF-001-03: Mark single notification as read

| Field | Value |
|-------|-------|
| **ID** | TC-NTF-001-03 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user with at least one unread notification (where `read_at IS NULL`)
2. Known notification ID (UUID) belonging to the user

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` request to `route('notifications.api.read', $notificationId)` | Response status 200, JSON response |
| 2 | Assert response contains `success: true` | Operation succeeded |
| 3 | Assert response contains `unreadCount` with decremented value | Unread count reduced by 1 |
| 4 | Query database: assert notification's `read_at` is now non-null | `read_at` set to current timestamp |
| 5 | Send same POST request again for the already-read notification | Response still `success: true` (idempotent), `unreadCount` unchanged |
| 6 | Attempt to mark another user's notification as read | NotificationService should scope to current user; notification not found or no effect |

**Notes:**
- Route: `POST /notifications/api/{id}/read` named `notifications.api.read`
- `NotificationService::markAsRead($id, $user)` handles the update
- The operation should be idempotent -- marking an already-read notification does not error
- Notification ID is a UUID string (Laravel's notification table uses UUID PKs)

---

### TC-NTF-001-04: Mark all notifications as read

| Field | Value |
|-------|-------|
| **ID** | TC-NTF-001-04 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user with at least 5 unread notifications
2. Some notifications already read (to verify they are not affected)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Verify initial `unreadCount` is >= 5 | Precondition confirmed |
| 2 | Send `POST` request to `route('notifications.api.mark-all-read')` | Response status 200, JSON response |
| 3 | Assert response contains `success: true` | Operation succeeded |
| 4 | Assert response contains `unreadCount: 0` | All notifications now marked read |
| 5 | Query database: assert all user's notifications have non-null `read_at` | `$user->unreadNotifications()->count()` equals 0 |
| 6 | Verify previously-read notifications still have their original `read_at` timestamp | Already-read notifications not re-stamped |

**Notes:**
- Route: `POST /notifications/api/mark-all-read` named `notifications.api.mark-all-read`
- `NotificationService::markAllAsRead($user)` updates all unread notifications
- The controller returns hardcoded `unreadCount: 0` after this operation
- Should only affect the authenticated user's notifications (multi-tenant safety)

---

### TC-NTF-001-05: Delete notification

| Field | Value |
|-------|-------|
| **ID** | TC-NTF-001-05 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user with at least one notification
2. Known notification ID (UUID) belonging to the user

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `DELETE` request to `route('notifications.api.destroy', $notificationId)` | Response status 200, JSON response |
| 2 | Assert response contains `success: true` | Deletion succeeded |
| 3 | Query database: assert notification no longer exists | `$user->notifications()->where('id', $id)->count()` equals 0 |
| 4 | Send `DELETE` again for the same notification ID | Returns success (row already gone, delete of 0 rows is not an error) |
| 5 | Attempt to delete another user's notification by ID | `$user->notifications()->where('id', $id)->delete()` scopes to current user; other user's notification not affected |

**Notes:**
- Route: `DELETE /notifications/api/{id}` named `notifications.api.destroy`
- The controller directly queries `$user->notifications()->where('id', $id)->delete()` -- scoped to the authenticated user
- This is a hard delete, not a soft delete -- the notification row is permanently removed
- No NotificationService method is used; the controller handles deletion inline

---

### TC-NTF-001-06: Notification data scoped to user

| Field | Value |
|-------|-------|
| **ID** | TC-NTF-001-06 |
| **Priority** | P0 |
| **Type** | Security |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Two users (User A and User B) in the same or different organizations
2. User A has 5 notifications; User B has 3 notifications
3. Authenticate as User A

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `GET` to `route('notifications.index')` as User A | Only User A's 5 notifications returned |
| 2 | Assert none of User B's notification IDs appear in the response | No cross-user data leakage |
| 3 | Send `GET` to `route('notifications.api.recent')` as User A | Only User A's recent notifications returned |
| 4 | Attempt to mark User B's notification as read via `POST` to `route('notifications.api.read', $userBNotificationId)` | No effect on User B's notification; operation silently succeeds or fails gracefully |
| 5 | Attempt to delete User B's notification via `DELETE` to `route('notifications.api.destroy', $userBNotificationId)` | User B's notification is not deleted; `$user->notifications()->where('id', ...)` scopes to User A |
| 6 | Assert User B's notification count remains unchanged | User B still has 3 notifications |

**Notes:**
- Notifications use Laravel's polymorphic `notifiable_id` which maps to the user's ID
- `$user->notifications()` is a morphMany relationship scoped by `notifiable_type` and `notifiable_id`
- The controller uses `Auth::user()` to derive the current user for all queries
- This test validates that notification isolation works regardless of whether users share an organization
