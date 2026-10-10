# TC-API-006: Notification Endpoints

| Field            | Value                                              |
|------------------|----------------------------------------------------|
| Document ID      | TC-API-006                                         |
| Domain           | API Endpoints                                      |
| Related Test Plan| TP-API (api-endpoints-test-plan.md)                |
| Last Updated     | 2026-04-15                                         |

---

## TC-API-006-01: GET /api/notifications/recent Returns 5 + Count

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** User authenticated. Multiple notifications exist (more than 5).

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | GET /api/notifications/recent       | 200 response                                        |
| 2    | Verify notifications array          | Exactly 5 most recent notifications returned         |
| 3    | Verify unread_count field           | Total unread count included in response              |
| 4    | Verify notification fields          | id, type, title, message, read_at, created_at        |
| 5    | Verify sort order                   | Newest notification first                            |
| 6    | User with no notifications          | 200 response with empty array and count=0            |

---

## TC-API-006-02: POST /api/notifications/{id}/read Marks Read

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** Unread notification exists for the user.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | POST /api/notifications/{id}/read   | 200 response                                        |
| 2    | Verify read_at timestamp set        | Notification now has non-null read_at                |
| 3    | Verify unread count decremented     | Subsequent GET /recent shows reduced unread_count    |
| 4    | POST on already-read notification   | 200 response (idempotent, no error)                  |
| 5    | POST on another user's notification | 404 response                                        |

---

## TC-API-006-03: POST /api/notifications/mark-all-read

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** Multiple unread notifications exist for the user.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | POST /api/notifications/mark-all-read | 200 response                                      |
| 2    | Verify all notifications read       | All user notifications have non-null read_at         |
| 3    | Verify unread count is zero         | GET /recent returns unread_count=0                   |
| 4    | POST when none unread               | 200 response (idempotent, no error)                  |

---

## TC-API-006-04: DELETE /api/notifications/{id}

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** Notification exists for the user.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | DELETE /api/notifications/{id}      | 200 response                                        |
| 2    | Verify notification removed         | GET /recent no longer includes deleted notification  |
| 3    | DELETE same notification again      | 404 response (already deleted)                       |
| 4    | DELETE another user's notification  | 404 response                                        |

---

## TC-API-006-05: Notifications User-Scoped

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** Two users exist with separate notifications.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Authenticate as User A              | Token for User A                                     |
| 2    | GET /api/notifications/recent       | Only User A notifications returned                   |
| 3    | Authenticate as User B              | Token for User B                                     |
| 4    | GET /api/notifications/recent       | Only User B notifications returned                   |
| 5    | User A marks User B notification    | 404 response (notification not found for this user)  |

---

## TC-API-006-06: Non-Existent Notification Returns 404

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** User authenticated.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | POST /api/notifications/99999/read  | 404 response                                        |
| 2    | DELETE /api/notifications/99999     | 404 response                                        |
| 3    | Verify response format              | JSON error body with appropriate message             |
| 4    | Use UUID format for non-existent    | 404 response (not 500 or exception)                  |
