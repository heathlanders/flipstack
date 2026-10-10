# TC-MOB-007: Notifications

| Field            | Value                                              |
|------------------|----------------------------------------------------|
| Document ID      | TC-MOB-007                                         |
| Domain           | Mobile App                                         |
| Related Test Plan| TP-MOB (mobile-app-test-plan.md)                   |
| Last Updated     | 2026-04-15                                         |

---

## TC-MOB-007-01: Notification List Paginated

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** User authenticated. Multiple notifications exist (more than one page).

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Navigate to Notifications screen    | GET /api/notifications/recent called                 |
| 2    | List renders                        | Notifications shown with title, message, timestamp   |
| 3    | Verify sort order                   | Newest notifications first                           |
| 4    | Scroll to bottom                    | Next page loaded via pagination                      |
| 5    | Verify unread styling               | Unread items have distinct background or indicator   |

---

## TC-MOB-007-02: Unread Badge Count

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Widget                    |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** Unread notifications exist for the user.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | View bottom navigation bar          | Notification tab shows badge with unread count       |
| 2    | Verify count matches API            | Badge number equals unread_count from API response   |
| 3    | Navigate to Notifications           | Badge count visible on screen header                 |
| 4    | Mark all as read                    | Badge disappears or shows 0                         |

---

## TC-MOB-007-03: Mark As Read

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** Unread notifications exist.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Tap an unread notification          | POST /api/notifications/{id}/read called             |
| 2    | Server responds 200                 | Notification marked as read                          |
| 3    | UI updates                          | Visual indicator changes to read styling             |
| 4    | Badge count decrements              | Unread count reduced by 1                            |
| 5    | Tap "Mark All Read"                 | POST /api/notifications/mark-all-read called         |
| 6    | All items update                    | All notifications show read styling                  |

---

## TC-MOB-007-04: Notification Preferences Toggle

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** User on notification preferences screen.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Navigate to notification settings   | Preferences screen loads with current settings       |
| 2    | Toggle a notification category off  | Switch visually toggles to off state                 |
| 3    | Save preferences                    | API call to update preferences                       |
| 4    | Server responds 200                 | Preferences saved successfully                       |
| 5    | Revisit preferences screen          | Previously toggled category still off                |

---

## TC-MOB-007-05: Pull to Refresh

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** Notification list loaded.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Pull down on notification list      | Refresh indicator appears                            |
| 2    | Release                             | API re-fetched                                       |
| 3    | New notifications added server-side | Newly created notifications appear at top of list    |
| 4    | Badge count updates                 | Unread count reflects new notifications              |

---

## TC-MOB-007-06: Empty State

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P2                        |
| Type          | Widget                    |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** User has no notifications.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Navigate to Notifications screen    | API returns empty list                               |
| 2    | Empty state renders                 | Informative message displayed (e.g., "No notifications") |
| 3    | No badge shown                      | Navigation tab has no badge or shows 0               |
| 4    | Pull to refresh still works         | Refresh gesture functional even on empty state       |
