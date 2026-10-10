# TC-MOB-008: Profile

| Field            | Value                                              |
|------------------|----------------------------------------------------|
| Document ID      | TC-MOB-008                                         |
| Domain           | Mobile App                                         |
| Related Test Plan| TP-MOB (mobile-app-test-plan.md)                   |
| Last Updated     | 2026-04-15                                         |

---

## TC-MOB-008-01: Profile Page Loads User Data

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** User authenticated.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Navigate to Profile screen          | GET /api/auth/profile called                         |
| 2    | Profile renders                     | Name, email, role, team displayed                    |
| 3    | Verify avatar                       | User avatar or initials placeholder shown            |
| 4    | Verify organization name            | Current organization displayed                       |
| 5    | Loading state                       | Skeleton shown while data loads                      |

---

## TC-MOB-008-02: Edit Profile Updates Name and Email

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** User on profile screen.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Tap Edit Profile button             | Edit form opens with current values pre-filled       |
| 2    | Change name field                   | Field accepts new value                              |
| 3    | Change email field                  | Field accepts new value                              |
| 4    | Tap Save                            | PUT /api/auth/profile called with updated data       |
| 5    | Server responds 200                 | Success message, profile view updates                |
| 6    | Submit with invalid email           | Validation error shown inline                        |

---

## TC-MOB-008-03: Change Password With Validation

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** User on profile screen.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Tap Change Password                 | Password form opens                                  |
| 2    | Enter current password              | Field accepts input (obscured)                       |
| 3    | Enter new password                  | Field accepts input with strength indicator          |
| 4    | Enter confirmation (matching)       | Confirmation field matches                           |
| 5    | Tap Save                            | PUT to password update endpoint                      |
| 6    | Server responds 200                 | Success message displayed                            |
| 7    | Enter wrong current password        | Server responds 422, error shown                     |
| 8    | Mismatched confirmation             | Client-side validation error before submission       |

---

## TC-MOB-008-04: Active Sessions List

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P2                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** User logged in on multiple devices or browsers.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Navigate to Sessions section        | API call to fetch active sessions                    |
| 2    | Session list renders                | Device name, IP, last active time per session        |
| 3    | Current session identified          | Current session marked with "This device" label      |
| 4    | Verify session count                | Number of sessions matches backend data              |

---

## TC-MOB-008-05: Revoke Session

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P2                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** Multiple active sessions exist.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Tap revoke on a non-current session | Confirmation dialog appears                          |
| 2    | Confirm revocation                  | DELETE to session revoke endpoint                    |
| 3    | Server responds 200                 | Session removed from list                            |
| 4    | Revoked session's device            | That device is logged out on next API call           |
| 5    | Attempt to revoke current session   | Action blocked or triggers full logout flow          |

---

## TC-MOB-008-06: Delete Account

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P2                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | No                        |

**Preconditions:** User on profile screen. User is not the sole owner of an organization.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Tap Delete Account                  | Warning dialog with consequences explained           |
| 2    | Enter password for confirmation     | Password field accepts input                         |
| 3    | Confirm deletion                    | DELETE to account deletion endpoint                  |
| 4    | Server responds 200                 | Account deleted, token invalidated                   |
| 5    | App navigates to login              | Login screen displayed, cannot re-authenticate       |
| 6    | Sole org owner attempts delete      | Server responds 403, error explains ownership block  |
