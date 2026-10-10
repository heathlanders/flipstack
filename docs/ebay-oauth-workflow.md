# eBay OAuth 2.0 Authorization Code Flow

Visual workflow of the eBay OAuth integration as implemented in this application.

## Flow Diagram

```mermaid
flowchart TD
    %% ===== INITIATION =====
    subgraph Initiation["1. OAuth Initiation"]
        A[User clicks 'Connect to eBay'] --> B{Organization exists?}
        B -- No --> B1[Return error]
        B -- Yes --> C{App credentials configured?<br/>EbayConfigService::isConfigured}
        C -- No --> C1[Return error:<br/>eBay not configured]
        C -- Yes --> D[Find or create<br/>IntegrationCredential]
        D --> E[Generate authorization URL<br/>with encrypted state]
        E --> F[State payload:<br/>organization_id, credential_id, timestamp]
        F --> G[Redirect to eBay<br/>via Inertia::location]
    end

    %% ===== EBAY AUTHORIZATION =====
    subgraph EbayAuth["2. eBay Authorization (External)"]
        G --> H[User lands on<br/>auth.ebay.com/oauth2/authorize]
        H --> I[User logs in to eBay]
        I --> J[User grants permissions<br/>for requested scopes]
        J --> K{User authorizes?}
        K -- Denied --> K1[eBay redirects with<br/>error + error_description]
        K -- Approved --> L[eBay redirects with<br/>authorization code + state]
    end

    %% ===== CALLBACK =====
    subgraph Callback["3. Callback Processing"]
        K1 --> M1[Log warning and<br/>redirect with error message]
        L --> M[Decrypt state parameter]
        M --> N{State valid?<br/>Has org_id and credential_id?}
        N -- No --> N1[Throw: Invalid state parameter]
        N -- Yes --> O{Timestamp within<br/>10 minutes?}
        O -- No --> O1[Throw: OAuth state expired]
        O -- Yes --> P[Load IntegrationCredential<br/>from state credential_id]
        P --> Q{User belongs to<br/>organization?}
        Q -- No --> Q1[Abort 403]
        Q -- Yes --> R{Code present<br/>in request?}
        R -- No --> R1[Throw: No authorization code]
        R -- Yes --> S[Call exchangeCodeForToken]
    end

    %% ===== TOKEN EXCHANGE =====
    subgraph Exchange["4. Token Exchange"]
        S --> T[POST /identity/v1/oauth2/token<br/>with HTTP Basic Auth<br/>client_id:client_secret]
        T --> T1[Form body:<br/>grant_type=authorization_code<br/>code + redirect_uri]
        T1 --> U{Response successful?}
        U -- No --> U1[Throw: Failed to exchange code]
        U -- Yes --> V[Parse access_token, refresh_token,<br/>expires_in, scope]
        V --> W[Fetch eBay user info<br/>GET /commerce/identity/v1/user/]
    end

    %% ===== TOKEN STORAGE =====
    subgraph Storage["5. Token Storage"]
        W --> X[Deactivate existing tokens<br/>for same org/user/platform/env]
        X --> Y[Create new OAuthToken record]
        Y --> Z[Model encrypts tokens<br/>on save via Crypt::encryptString]
        Z --> Z1[Store: access_token, refresh_token,<br/>expires_at, scopes, metadata]
        Z1 --> Z2{Organization has<br/>existing products?}
        Z2 -- No --> Z3[Flash show_ebay_import_prompt=true<br/>Prompt user to import eBay inventory]
        Z2 -- Yes --> Z4[No import prompt needed]
        Z3 --> Z5[Redirect to organization settings<br/>with success message]
        Z4 --> Z5
    end

    %% ===== TOKEN USAGE =====
    subgraph Usage["6. Token Usage in API Calls"]
        AA[Service needs to call eBay API<br/>e.g. EbayInventoryService] --> AB[getToken: Retrieve active token<br/>from IntegrationCredential]
        AB --> AC{Token needs refresh?<br/>Expiring within 30 min?}
        AC -- Yes --> AD[Refresh token first]
        AD --> AE[Attach to request]
        AC -- No --> AE
        AE --> AF[Set header:<br/>Authorization: Bearer access_token]
        AF --> AG[Make API request<br/>with marketplace headers]
        AG --> AH[Mark token as used<br/>update last_used_at]
    end

    %% ===== TOKEN REFRESH =====
    subgraph Refresh["7. Token Refresh"]
        direction TB
        BA[Scheduled Job:<br/>RefreshOAuthTokens] --> BC
        BB[Manual trigger:<br/>EbayOAuthController::refreshToken] --> BC
        BC[Find active tokens expiring<br/>within 30 minutes]
        BC --> BD[POST /identity/v1/oauth2/token<br/>with HTTP Basic Auth]
        BD --> BE[Form body:<br/>grant_type=refresh_token<br/>refresh_token + scope]
        BE --> BF{Refresh successful?}
        BF -- Yes --> BG[Update access_token<br/>and expires_at on token]
        BF -- No --> BH[Deactivate token<br/>user must re-authorize]
    end

    %% ===== DISCONNECT =====
    subgraph Disconnect["8. Disconnect / Revoke"]
        CA[User clicks Disconnect] --> CB[Find active OAuthToken<br/>for org/platform/environment]
        CB --> CC{Token found?}
        CC -- No --> CC1[Return error:<br/>No active connection]
        CC -- Yes --> CD[POST /identity/v1/oauth2/revoke<br/>with HTTP Basic Auth]
        CD --> CE[Form body:<br/>token = access_token]
        CE --> CF[Deactivate token locally<br/>is_active = false]
        CF --> CG[Redirect with<br/>success message]
    end

    %% ===== CROSS-SUBGRAPH CONNECTIONS =====
    Z5 -.->|Token now available| AA
    AD -.->|Uses same refresh flow| BD
```

## Key Files

| File | Role |
|------|------|
| `app/Http/Controllers/Integration/IntegrationController.php` | OAuth initiation, credential creation |
| `app/Http/Controllers/Ebay/EbayOAuthController.php` | Callback, manual refresh, disconnect |
| `app/Services/EbayOAuthService.php` | Token exchange, refresh, revoke logic |
| `app/Services/EbayConfigService.php` | App-level credential config (client_id, secret, RuName) |
| `app/Models/Integration/OAuthToken.php` | Token model with auto-encryption |
| `app/Models/Integration/IntegrationCredential.php` | Credential model, auth URL generation |
| `app/Jobs/RefreshOAuthTokens.php` | Scheduled job for automatic token refresh |
| `app/Services/EbayInventoryService.php` | Example of token usage in API calls |

## OAuth Scopes Requested

- `sell.inventory` - Manage inventory
- `sell.fulfillment` - Manage orders/fulfillment
- `sell.account` - Manage account settings
- `sell.marketing` - Marketing tools
- `sell.analytics.readonly` - Read analytics
- `sell.finances` - Financial data
- `sell.payment.dispute` - Payment disputes
- `commerce.identity.readonly` - Read user identity

## Security Details

- **State parameter**: Encrypted with Laravel's `encrypt()`, contains org_id, credential_id, and timestamp
- **State expiry**: 10-minute window enforced on callback
- **Token storage**: Access and refresh tokens encrypted at rest via `Crypt::encryptString` in model boot
- **Token refresh threshold**: Tokens are refreshed when expiring within 30 minutes
- **App-level credentials**: Client ID, secret, and RuName come from environment config, not user input
- **HTTP Basic Auth**: Token exchange, refresh, and revoke all use `client_id:client_secret` as Basic Auth credentials
