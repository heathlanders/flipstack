# TC-MOB-001: Authentication

| Field            | Value                                              |
|------------------|----------------------------------------------------|
| Document ID      | TC-MOB-001                                         |
| Domain           | Mobile App                                         |
| Related Test Plan| TP-MOB (mobile-app-test-plan.md)                   |
| Last Updated     | 2026-04-15                                         |

---

## TC-MOB-001-01: Splash Screen Routing

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** App freshly installed, no stored token.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Launch the app                      | Splash screen displays with logo                     |
| 2    | Wait for initialization             | App checks secure storage for existing token          |
| 3    | No token found                      | GoRouter redirects to login screen                   |
| 4    | Repeat with valid stored token      | GoRouter redirects to dashboard screen               |

---

## TC-MOB-001-02: Login With Credentials Returns Sanctum Token

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** User account exists in backend. App on login screen.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Enter valid email and password      | Fields accept input, no validation errors            |
| 2    | Tap Login button                    | Dio sends POST to /api/auth/login                    |
| 3    | Server responds 200 with token      | Token received in response body                      |
| 4    | Verify navigation                   | App navigates to dashboard                           |
| 5    | Enter invalid credentials           | Server responds 422, error message shown             |

---

## TC-MOB-001-03: Login Stores Token in Secure Storage

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Widget                    |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** Successful login completed.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Complete login flow                 | Token returned from API                              |
| 2    | Check secure storage                | Token persisted via flutter_secure_storage            |
| 3    | Force-close and relaunch app        | Token retrieved from storage on startup              |
| 4    | Verify authenticated state          | App navigates directly to dashboard, not login        |

---

## TC-MOB-001-04: 2FA Challenge Screen

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** User has 2FA enabled. Login with correct credentials.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Submit valid email/password         | Server responds with 2FA challenge required          |
| 2    | App navigates to 2FA screen         | 6-digit code input field displayed                   |
| 3    | Enter valid TOTP code               | POST to /api/auth/two-factor-challenge succeeds      |
| 4    | Verify token stored                 | Token saved, app navigates to dashboard              |
| 5    | Enter invalid code                  | Error message displayed, remains on 2FA screen       |

---

## TC-MOB-001-05: Registration Creates Account

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** App on login screen. Registration enabled.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Tap Register link                   | Navigate to registration form                        |
| 2    | Fill name, email, password, confirm | All fields accept input                              |
| 3    | Tap Register button                 | POST to /api/auth/register                           |
| 4    | Server responds 201                 | Account created, token returned                      |
| 5    | Verify navigation                   | App navigates to dashboard                           |
| 6    | Submit with existing email          | Server responds 422, duplicate email error shown     |

---

## TC-MOB-001-06: Forgot Password Sends Email

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P2                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | No                        |

**Preconditions:** App on login screen. User account exists.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Tap Forgot Password link            | Navigate to forgot password screen                   |
| 2    | Enter registered email              | Field accepts input                                  |
| 3    | Tap Send Reset Link                 | POST to /api/auth/forgot-password                    |
| 4    | Server responds 200                 | Success message displayed                            |
| 5    | Enter unregistered email            | Same success message (no user enumeration)           |

---

## TC-MOB-001-07: Auto-Refresh Expired Token

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Provider                  |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** User authenticated. Token near expiry.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Make API request with expired token | Dio interceptor detects 401 response                 |
| 2    | Interceptor triggers refresh        | POST to /api/auth/refresh with current token         |
| 3    | Server returns new token            | New token saved to secure storage                    |
| 4    | Original request retried            | Request succeeds with new token                      |
| 5    | Refresh also fails                  | User logged out, redirected to login screen          |

---

## TC-MOB-001-08: Logout Clears Token and Navigates to Login

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** User authenticated and on any screen.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Tap profile/menu icon               | Profile menu or drawer opens                         |
| 2    | Tap Logout                          | POST to /api/auth/logout                             |
| 3    | Server responds 200                 | Token invalidated server-side                        |
| 4    | Token removed from secure storage   | Secure storage cleared                               |
| 5    | App navigates to login screen       | Login screen displayed, back navigation blocked      |
