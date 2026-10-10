<?php

namespace App\Models\Integration;

use App\Models\Amazon\AmazonListing;
use App\Models\Amazon\AmazonSyncLog;
use App\Models\Ebay\EbayListing;
use App\Models\Ebay\EbayPolicy;
use App\Models\Ebay\EbayPolicyRule;
use App\Models\Ebay\EbaySyncLog;
use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\Shopify\ShopifyListing;
use App\Models\Shopify\ShopifySyncLog;
use App\Services\Amazon\AmazonOAuthService;
use App\Services\EbayConfigService;
use App\Traits\LogsAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class IntegrationCredential extends Model
{
    use LogsAudit;

    protected ?string $auditCategory = 'security';

    protected ?string $auditSeverity = 'warning';

    protected ?string $auditActionPrefix = 'credential';

    protected array $auditExclude = ['client_secret', 'config', 'last_verified_at'];

    protected $table = 'integration_credentials';

    protected $fillable = [
        'team_id',
        'organization_id',
        'platform',
        'environment',
        'name',
        'client_id',
        'client_secret',
        'config',
        'is_active',
        'last_verified_at',
        'ru_name',
        'marketplace_id',
        'seller_username',
        'fulfillment_policy_id',
        'payment_policy_id',
        'return_policy_id',
        'merchant_location_key',
        'auto_sync_enabled',
        'sync_interval_minutes',
        'last_full_sync_at',
    ];

    protected $casts = [
        'config' => 'array',
        'is_active' => 'boolean',
        'last_verified_at' => 'datetime',
        'auto_sync_enabled' => 'boolean',
        'sync_interval_minutes' => 'integer',
        'last_full_sync_at' => 'datetime',
    ];

    protected $hidden = [
        'client_secret',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // Encrypt credentials before saving
        static::saving(function ($credential) {
            if ($credential->isDirty('client_id') && ! empty($credential->client_id)) {
                try {
                    // Only encrypt if not already encrypted
                    Crypt::decryptString($credential->client_id);
                } catch (\Exception $e) {
                    $credential->client_id = Crypt::encryptString($credential->client_id);
                }
            }

            if ($credential->isDirty('client_secret') && ! empty($credential->client_secret)) {
                try {
                    // Only encrypt if not already encrypted
                    Crypt::decryptString($credential->client_secret);
                } catch (\Exception $e) {
                    $credential->client_secret = Crypt::encryptString($credential->client_secret);
                }
            }

            // Encrypt ru_name
            if ($credential->isDirty('ru_name') && ! empty($credential->ru_name)) {
                try {
                    Crypt::decryptString($credential->ru_name);
                } catch (\Exception $e) {
                    $credential->ru_name = Crypt::encryptString($credential->ru_name);
                }
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function oauthTokens(): HasMany
    {
        return $this->hasMany(OAuthToken::class);
    }

    /**
     * Get decrypted client ID
     */
    public function getDecryptedClientIdAttribute(): ?string
    {
        if ($this->client_id === null) {
            return null;
        }

        try {
            return Crypt::decryptString($this->client_id);
        } catch (\Exception $e) {
            // If already decrypted or invalid, return as is
            return $this->client_id;
        }
    }

    /**
     * Get decrypted client secret
     */
    public function getDecryptedClientSecretAttribute(): ?string
    {
        if ($this->client_secret === null) {
            return null;
        }

        try {
            return Crypt::decryptString($this->client_secret);
        } catch (\Exception $e) {
            // If already decrypted or invalid, return as is
            return $this->client_secret;
        }
    }

    /**
     * Check if credentials are valid.
     *
     * For eBay, this returns true if app-level credentials are configured
     * (users don't need to provide their own credentials).
     * For other platforms, checks for stored client_id and client_secret.
     */
    public function isComplete(): bool
    {
        if ($this->platform === 'ebay') {
            return $this->hasAppLevelCredentials();
        }

        return ! empty($this->client_id) && ! empty($this->client_secret);
    }

    /**
     * Check if this platform uses app-level credentials from config.
     */
    public function usesAppLevelCredentials(): bool
    {
        return $this->platform === 'ebay';
    }

    /**
     * Check if app-level credentials are configured for eBay.
     */
    public function hasAppLevelCredentials(): bool
    {
        if ($this->platform !== 'ebay') {
            return false;
        }

        $config = app(EbayConfigService::class);

        return $config->isConfigured();
    }

    /**
     * Get the OAuth authorization URL for this platform
     */
    public function getAuthorizationUrl(): ?string
    {
        return match ($this->platform) {
            'ebay' => $this->getEbayAuthUrl(),
            'amazon' => $this->getAmazonAuthUrl(),
            'newegg' => null, // To be implemented
            default => null,
        };
    }

    /**
     * Get eBay authorization URL.
     *
     * Uses app-level credentials from EbayConfigService, so users
     * don't need to provide their own client_id or ru_name.
     */
    private function getEbayAuthUrl(): string
    {
        $config = app(EbayConfigService::class);

        $authBaseUrl = $config->getAuthBaseUrl();
        $clientId = $config->getClientId();
        $ruName = $config->getRuName();
        $scopes = $config->getOAuthScopesString();
        $environment = $config->getEnvironment();

        $params = http_build_query([
            'client_id' => $clientId,
            'response_type' => 'code',
            'redirect_uri' => $ruName,
            'scope' => $scopes,
            'state' => encrypt([
                'organization_id' => $this->organization_id,
                'credential_id' => $this->id,
                'timestamp' => now()->timestamp,
            ]),
        ]);

        return $authBaseUrl.'?'.$params;
    }

    /**
     * Scope for active credentials
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for a specific platform
     */
    public function scopePlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Scope for a specific environment
     */
    public function scopeEnvironment($query, string $environment)
    {
        return $query->where('environment', $environment);
    }

    /**
     * Relationship: eBay listings using this credential
     */
    public function ebayListings(): HasMany
    {
        return $this->hasMany(EbayListing::class);
    }

    /**
     * Relationship: eBay sync logs for this credential
     */
    public function ebaySyncLogs(): HasMany
    {
        return $this->hasMany(EbaySyncLog::class);
    }

    /**
     * Get decrypted RuName
     */
    public function getDecryptedRuNameAttribute(): ?string
    {
        if (empty($this->ru_name)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->ru_name);
        } catch (\Exception $e) {
            return $this->ru_name;
        }
    }

    /**
     * Check if this credential has all required eBay settings
     */
    public function hasEbayPolicies(): bool
    {
        return $this->platform === 'ebay' &&
               ! empty($this->fulfillment_policy_id) &&
               ! empty($this->payment_policy_id) &&
               ! empty($this->return_policy_id);
    }

    /**
     * Check if this credential has a merchant location
     */
    public function hasMerchantLocation(): bool
    {
        return $this->platform === 'ebay' &&
               ! empty($this->merchant_location_key);
    }

    /**
     * Check if eBay credential is fully configured
     */
    public function isEbayReady(): bool
    {
        return $this->platform === 'ebay' &&
               $this->isComplete() &&
               $this->hasEbayPolicies() &&
               $this->hasMerchantLocation();
    }

    /**
     * Get the base API URL for eBay.
     *
     * Uses the environment from EbayConfigService.
     */
    public function getEbayApiUrl(): string
    {
        $config = app(EbayConfigService::class);

        return $config->getApiBaseUrl();
    }

    /**
     * Get active OAuth token for eBay
     */
    public function getActiveEbayToken(): ?OAuthToken
    {
        return $this->oauthTokens()
            ->where('platform', 'ebay')
            ->where('environment', $this->environment)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get published listings count
     */
    public function getPublishedListingsCountAttribute(): int
    {
        return $this->ebayListings()
            ->where('status', 'published')
            ->count();
    }

    /**
     * Get listings with errors count
     */
    public function getErrorListingsCountAttribute(): int
    {
        return $this->ebayListings()
            ->where('status', 'error')
            ->count();
    }

    /**
     * Scope for eBay credentials only
     */
    public function scopeEbay($query)
    {
        return $query->where('platform', 'ebay');
    }

    /**
     * Scope for Shopify credentials only
     */
    public function scopeShopify($query)
    {
        return $query->where('platform', 'shopify');
    }

    /**
     * Check if Shopify credential is ready
     */
    public function isShopifyReady(): bool
    {
        return $this->platform === 'shopify' &&
               $this->is_active &&
               $this->getActiveShopifyToken() !== null;
    }

    /**
     * Get active OAuth token for Shopify
     */
    public function getActiveShopifyToken(): ?OAuthToken
    {
        return $this->oauthTokens()
            ->where('platform', 'shopify')
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get active OAuth token for Amazon
     */
    public function getActiveAmazonToken(): ?OAuthToken
    {
        return $this->oauthTokens()
            ->where('platform', 'amazon')
            ->where('is_active', true)
            ->first();
    }

    /**
     * Relationship: Shopify listings using this credential
     */
    public function shopifyListings(): HasMany
    {
        return $this->hasMany(ShopifyListing::class);
    }

    /**
     * Relationship: Shopify sync logs for this credential
     */
    public function shopifySyncLogs(): HasMany
    {
        return $this->hasMany(ShopifySyncLog::class);
    }

    public function scopeAmazon($query)
    {
        return $query->where('platform', 'amazon');
    }

    public function isAmazonReady(): bool
    {
        return $this->platform === 'amazon'
            && $this->is_active
            && ! empty($this->config['amazon_seller_id'] ?? null)
            && $this->getActiveAmazonToken() !== null;
    }

    private function getAmazonAuthUrl(): string
    {
        return app(AmazonOAuthService::class)->getAuthorizationUrl($this);
    }

    public function amazonListings(): HasMany
    {
        return $this->hasMany(AmazonListing::class);
    }

    public function amazonSyncLogs(): HasMany
    {
        return $this->hasMany(AmazonSyncLog::class);
    }

    /**
     * Get the eBay ECN verification token stored in config.
     * Used for both the challenge response and HMAC signature verification.
     */
    public function getEbayNotificationTokenAttribute(): ?string
    {
        return $this->config['ebay_notification_token'] ?? null;
    }

    /**
     * Scope for credentials ready to sync
     */
    public function scopeReadyForSync($query)
    {
        return $query->where('platform', 'ebay')
            ->where('is_active', true)
            ->where('auto_sync_enabled', true)
            ->whereNotNull('fulfillment_policy_id')
            ->whereNotNull('payment_policy_id')
            ->whereNotNull('return_policy_id')
            ->whereNotNull('merchant_location_key');
    }

    /**
     * Relationship: eBay policies synced for this credential
     */
    public function ebayPolicies(): HasMany
    {
        return $this->hasMany(EbayPolicy::class);
    }

    /**
     * Relationship: eBay policy rules for this credential
     */
    public function ebayPolicyRules(): HasMany
    {
        return $this->hasMany(EbayPolicyRule::class);
    }

    /**
     * Get default policy for a given type
     */
    public function getDefaultEbayPolicy(string $type): ?EbayPolicy
    {
        return $this->ebayPolicies()
            ->where('policy_type', $type)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get all active policies of a given type
     */
    public function getActiveEbayPolicies(string $type): \Illuminate\Database\Eloquent\Collection
    {
        return $this->ebayPolicies()
            ->where('policy_type', $type)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }
}
