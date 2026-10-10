# Notifications Test Plan
## Document ID: FLIPSTACK-TP-NTF-001
**Version:** 1.0 | **Last Updated:** 2026-04-15 | **Status:** Approved

## Scope
Verify notification listing, recent notifications retrieval, read status management, and notification deletion.

## Related Docs
| Resource | Path |
|----------|------|
| Feature docs | `docs/NOTIFICATIONS.md` |
| Existing tests | `tests/Feature/UserInvitationNotificationTest.php` |

## Features Under Test
| # | Feature | Controller@method | Route Name | Priority |
|---|---------|-------------------|------------|----------|
| 1 | Notification listing | NotificationController@index | notifications.index | High |
| 2 | Recent notifications | NotificationController@recent | notifications.recent | High |
| 3 | Mark single as read | NotificationController@markAsRead | notifications.markAsRead | Medium |
| 4 | Mark all as read | NotificationController@markAllAsRead | notifications.markAllAsRead | Medium |
| 5 | Delete notification | NotificationController@destroy | notifications.destroy | Low |

## Test Cases
| ID | Title | File |
|----|-------|------|
| TC-NTF-001 | Notification listing, recent fetch, and read marking | `tests/Feature/Notifications/NotificationFlowTest.php` |
| TC-NTF-002 | Bulk mark-all-read and notification deletion | `tests/Feature/Notifications/NotificationManagementTest.php` |

## Coverage Goals
| Metric | Current | Target |
|--------|---------|--------|
| Feature test coverage | Partial (UserInvitationNotificationTest.php) | 90% route coverage |
| NotificationController coverage | Low | 85% |
| NotificationLog model coverage | Low | 75% |
