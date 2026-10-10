# Authentication Test Cases

## Document ID: TC-AUT-001

**Domain:** Auth
**Related Test Plan:** `docs/testing/plans/auth-test-plan.md`
**Last Updated:** 2026-04-15

---

### TC-AUT-001-01: Login with valid credentials

| Field | Value |
|-------|-------|
| **ID** | TC-AUT-001-01 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. A registered User with known email and password
2. User is not currently authenticated

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `/login` | 200 response, login form rendered |
| 2 | POST `/login` with valid email and password | Redirect to /dashboard |
| 3 | Verify session | Authenticated session created (auth:sanctum + Jetstream session) |
| 4 | Verify redirect | User with force_password_change=true redirected to /password/change instead of dashboard |

---

### TC-AUT-001-02: Login fails with wrong password

| Field | Value |
|-------|-------|
| **ID** | TC-AUT-001-02 |
| **Priority** | P0 |
| **Type** | Negative |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. A registered User with known email

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | POST `/login` with valid email and wrong password | Redirect back with validation error |
| 2 | Verify error | "These credentials do not match our records" message displayed |
| 3 | Verify session | No authenticated session created |
| 4 | Multiple failed attempts | Account not locked immediately (rate limiting via Fortify throttle) |

---

### TC-AUT-001-03: Registration creates user and organization

| Field | Value |
|-------|-------|
| **ID** | TC-AUT-001-03 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Registration is enabled (canRegister route exists)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `/register` | 200 response, registration form rendered |
| 2 | POST `/register` with name, email, password, password_confirmation | User record created, redirect to dashboard or onboarding |
| 3 | Verify User record | User created with correct name, email, hashed password |
| 4 | Verify Organization | Organization created and linked to user via organization_id |
| 5 | Verify Team | Default team created for the organization with user as owner |
| 6 | POST with duplicate email | Validation error: email already taken |
| 7 | POST with weak password | Validation error: password requirements not met |

---

### TC-AUT-001-04: Password reset email sent

| Field | Value |
|-------|-------|
| **ID** | TC-AUT-001-04 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. A registered User with valid email

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `/forgot-password` | 200 response, form rendered |
| 2 | POST `/forgot-password` with registered email | Redirect back with status message "We have emailed your password reset link" |
| 3 | Verify email | Password reset notification sent to user's email |
| 4 | Verify token | Password reset token stored in password_reset_tokens table |
| 5 | POST with unregistered email | Status message returned (no indication whether email exists, to prevent enumeration) |

---

### TC-AUT-001-05: Password reset with valid token

| Field | Value |
|-------|-------|
| **ID** | TC-AUT-001-05 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. A valid password reset token generated for a user

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `/reset-password/{token}` with valid token and email | 200 response, reset form rendered |
| 2 | POST `/reset-password` with token, email, password, password_confirmation | Redirect to login with success status |
| 3 | Verify password | User can login with new password |
| 4 | Verify old password | Old password no longer works |
| 5 | POST with expired token | Validation error: token is invalid or expired |
| 6 | POST with token belonging to different email | Reset fails, validation error |

---

### TC-AUT-001-06: 2FA enable flow

| Field | Value |
|-------|-------|
| **ID** | TC-AUT-001-06 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user without 2FA enabled
2. Jetstream two-factor authentication feature enabled

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | POST to enable two-factor authentication (Jetstream endpoint) | 2FA secret generated and stored |
| 2 | Verify QR code | QR code SVG returned for authenticator app scanning |
| 3 | Verify recovery codes | Set of recovery codes generated and displayed |
| 4 | Confirm 2FA with valid TOTP code | 2FA confirmed and active on account |
| 5 | Confirm with invalid code | Error returned, 2FA not confirmed |

**Notes:**
- Uses Jetstream's built-in 2FA via Laravel Fortify

---

### TC-AUT-001-07: 2FA verification on login

| Field | Value |
|-------|-------|
| **ID** | TC-AUT-001-07 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. User with 2FA enabled and confirmed

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | POST `/login` with valid email and password | Redirect to 2FA challenge page (not dashboard) |
| 2 | POST 2FA challenge with valid TOTP code | Redirect to dashboard, session fully authenticated |
| 3 | POST 2FA challenge with invalid code | Error displayed, session remains in 2FA-pending state |
| 4 | POST 2FA challenge with valid recovery code | Redirect to dashboard, recovery code consumed (single-use) |
| 5 | Use same recovery code again | Error: recovery code already used |

---

### TC-AUT-001-08: Forced password change redirect

| Field | Value |
|-------|-------|
| **ID** | TC-AUT-001-08 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. User with `force_password_change` flag set to true (e.g., first login after admin-created account)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | POST `/login` with valid credentials | Redirect to `/password/change` instead of dashboard |
| 2 | GET `/password/change` | 200 response, password change form rendered |
| 3 | Attempt to navigate to any other authenticated route | Redirected back to /password/change |
| 4 | POST `/password/change` with current password and new password | Password updated, force_password_change set to false, redirect to dashboard |
| 5 | Subsequent logins | Normal redirect to dashboard, no forced change |

**Notes:**
- Password change route is outside the onboarding middleware group to ensure it is always accessible
- Route: `password.change` and `password.change.update`
