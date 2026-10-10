<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product;
use App\Models\Integration\IntegrationCredential;
use App\Models\Inventory\InventoryLocation;
use App\Models\Organization\Organization;
use App\Models\Organization\OrganizationSettings;
use App\Models\Organization\Team;
use App\Models\User\User;
use App\Models\User\UserActivityLog;
use App\Services\EbayConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class OrganizationController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
    }

    /**
     * Organization settings dashboard
     */
    public function settings(Request $request, EbayConfigService $ebayConfig)
    {
        $user = $request->user();
        $organization = $user->organization;
        $team = $user->currentTeam;

        $this->authorize('manageOrganization', $organization);

        // Get all organization settings
        $settings = OrganizationSettings::where('organization_id', $organization->id)
            ->get()
            ->keyBy('key')
            ->mapWithKeys(function ($setting) {
                return [$setting->key => $setting->value];
            });

        // Default settings if not exists
        $defaultSettings = [
            'company_name' => $organization->name,
            'company_description' => $organization->description ?? '',
            'company_website' => $organization->website ?? '',
            'company_phone' => $organization->phone ?? '',
            'company_email' => $organization->email ?? '',
            'company_address' => $organization->address ?? [
                'street' => '',
                'city' => '',
                'state' => '',
                'zip' => '',
                'country' => 'US',
            ],
            'branding' => [
                'logo_url' => '',
                'primary_color' => '#3B82F6',
                'secondary_color' => '#10B981',
                'accent_color' => '#F59E0B',
            ],
            'preferences' => [
                'timezone' => 'UTC',
                'date_format' => 'M j, Y',
                'time_format' => '12',
                'currency' => 'USD',
                'language' => 'en',
            ],
            'notifications' => [
                'email_notifications' => true,
                'slack_notifications' => false,
                'sms_notifications' => false,
                'notification_frequency' => 'real_time',
            ],
            'security' => [
                'require_2fa' => false,
                'password_expiry_days' => 90,
                'session_timeout_minutes' => 480,
                'allowed_domains' => [],
            ],
            'integrations' => [
                'slack_webhook' => '',
                'email_provider' => 'default',
                'backup_provider' => 'local',
            ],
        ];

        $mergedSettings = array_merge($defaultSettings, $settings->toArray());

        // Get all integration credentials for the organization
        $integrations = collect();
        $existingEbayCredential = null;
        $ebayHasActiveToken = false;
        $ebayEnvironment = $ebayConfig->getEnvironment();

        $integrations = IntegrationCredential::where('organization_id', $organization->id)
            ->with('oauthTokens')
            ->get()
            ->map(function ($credential) {
                $activeToken = $credential->oauthTokens()
                    ->active()
                    ->where(function ($query) {
                        $query->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                    })
                    ->first();

                return [
                    'id' => $credential->id,
                    'platform' => $credential->platform,
                    'environment' => $credential->environment,
                    'name' => $credential->name,
                    'is_active' => $credential->is_active,
                    'is_complete' => $credential->isComplete(),
                    'last_verified_at' => $credential->last_verified_at,
                    'has_active_token' => $activeToken !== null,
                    'seller_username' => $credential->seller_username,
                    'account_username' => $credential->seller_username ?? ($activeToken?->metadata ?? [])['username'] ?? null,
                    'authorization_url' => $credential->isComplete()
                        ? $credential->getAuthorizationUrl()
                        : null,
                ];
            });

        // Get all eBay credentials for the organization
        $ebayCredentials = IntegrationCredential::where('organization_id', $organization->id)
            ->where('platform', 'ebay')
            ->where('environment', $ebayEnvironment)
            ->with('oauthTokens')
            ->get();

        $ebayHasActiveToken = $ebayCredentials->contains(function ($cred) {
            return $cred->oauthTokens()
                ->active()
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->exists();
        });

        // Get all Shopify credentials for the organization
        $shopifyCredentials = IntegrationCredential::where('organization_id', $organization->id)
            ->where('platform', 'shopify')
            ->with('oauthTokens')
            ->get();

        $shopifyHasActiveToken = $shopifyCredentials->contains(function ($cred) {
            return $cred->oauthTokens()
                ->where('platform', 'shopify')
                ->where('is_active', true)
                ->exists();
        });

        $shopifyConfigured = ! empty(config('services.shopify.api_key')) && ! empty(config('services.shopify.api_secret'));

        // Available platforms configuration
        $availablePlatforms = [
            'ebay' => [
                'name' => 'eBay',
                'description' => 'Connect to eBay to manage inventory, orders, and listings',
                'environments' => ['sandbox', 'production'],
                'oauth_enabled' => true,
            ],
            'barcode_spider' => [
                'name' => 'Barcode Spider',
                'description' => 'UPC/Barcode lookup service (100 free lookups/day)',
                'environments' => ['production'],
                'oauth_enabled' => false,
            ],
            'gemini' => [
                'name' => 'Gemini AI Research',
                'description' => 'AI-powered product research (1,500 free requests/day)',
                'environments' => ['production'],
                'oauth_enabled' => false,
            ],
            'shopify' => [
                'name' => 'Shopify',
                'description' => 'Connect to Shopify to sync products, inventory, and orders',
                'environments' => ['production'],
                'oauth_enabled' => true,
            ],
            'amazon' => [
                'name' => 'Amazon',
                'description' => 'Connect to Amazon Seller Central',
                'environments' => ['production'],
                'coming_soon' => true,
            ],
            'newegg' => [
                'name' => 'Newegg',
                'description' => 'Connect to Newegg Marketplace',
                'environments' => ['production'],
                'coming_soon' => true,
            ],
            'easypost' => [
                'name' => 'EasyPost Shipping',
                'description' => 'Multi-carrier shipping API for label purchase, rate shopping, and tracking',
                'environments' => ['production'],
                'oauth_enabled' => false,
            ],
        ];

        return Inertia::render('Organization/Settings', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'created_at' => $organization->created_at->format('M j, Y'),
                'updated_at' => $organization->updated_at->diffForHumans(),
            ],
            'settings' => $mergedSettings,
            'timezones' => $this->getTimezoneOptions(),
            'currencies' => $this->getCurrencyOptions(),
            'languages' => $this->getLanguageOptions(),
            'integrations' => $integrations,
            'ebayConfig' => [
                'configured' => $ebayConfig->isConfigured(),
                'environment' => $ebayEnvironment,
                'is_sandbox' => $ebayConfig->isSandbox(),
                'has_active_token' => $ebayHasActiveToken,
                'account_count' => $ebayCredentials->count(),
            ],
            'ebayAccounts' => $ebayCredentials->map(function ($cred) {
                $hasToken = $cred->oauthTokens()->active()
                    ->where(function ($q) {
                        $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    })->exists();

                $config = $cred->config ?? [];

                return [
                    'id' => $cred->id,
                    'name' => $cred->name,
                    'seller_username' => $cred->seller_username,
                    'environment' => $cred->environment,
                    'is_active' => $cred->is_active,
                    'has_active_token' => $hasToken,
                    'last_verified_at' => $cred->last_verified_at,
                    'auto_sync_enabled' => $cred->auto_sync_enabled,
                    'last_full_sync_at' => $cred->last_full_sync_at,
                    'webhook_registered' => ! empty($config['ebay_notification_destination_id']),
                    'webhook_token_set' => ! empty($config['ebay_notification_token']),
                ];
            })->values(),
            'shopifyConfig' => [
                'configured' => $shopifyConfigured,
                'has_active_token' => $shopifyHasActiveToken,
                'shop_domain' => $shopifyCredentials->first()?->config['shop_domain'] ?? null,
                'account_count' => $shopifyCredentials->count(),
            ],
            'shopifyAccounts' => $shopifyCredentials->map(function ($cred) {
                $hasToken = $cred->oauthTokens()
                    ->where('platform', 'shopify')
                    ->where('is_active', true)
                    ->exists();

                return [
                    'id' => $cred->id,
                    'name' => $cred->name,
                    'shop_domain' => $cred->config['shop_domain'] ?? null,
                    'is_active' => $cred->is_active,
                    'has_active_token' => $hasToken,
                    'last_verified_at' => $cred->last_verified_at,
                    'auto_sync_enabled' => $cred->auto_sync_enabled,
                ];
            })->values(),
            'availablePlatforms' => $availablePlatforms,
            'hasExtensionToken' => $request->user()->tokens()->where('name', 'like', 'extension-%')->exists(),
            'productCount' => Product::where('organization_id', $organization->id)->count(),
            'showEbayImportPrompt' => session('show_ebay_import_prompt', false),
            'permissions' => [
                'can_edit' => $user->hasPermission('organization.edit'),
                'can_manage_billing' => $user->hasPermission('billing.manage'),
                'can_manage_integrations' => $user->hasPermission('integrations.manage'),
            ],
        ]);
    }

    /**
     * Update organization settings
     */
    public function updateSettings(Request $request)
    {
        $user = $request->user();
        $organization = $user->organization;

        $this->authorize('manageOrganization', $organization);

        $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'company_description' => ['nullable', 'string', 'max:1000'],
            'company_website' => ['nullable', 'url', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:20'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'company_address' => ['array'],
            'company_address.street' => ['nullable', 'string', 'max:255'],
            'company_address.city' => ['nullable', 'string', 'max:100'],
            'company_address.state' => ['nullable', 'string', 'max:100'],
            'company_address.zip' => ['nullable', 'string', 'max:20'],
            'company_address.country' => ['required', 'string', 'size:2'],
            'preferences' => ['array'],
            'preferences.timezone' => ['required', 'string'],
            'preferences.date_format' => ['required', 'string'],
            'preferences.time_format' => ['required', 'in:12,24'],
            'preferences.currency' => ['required', 'string', 'size:3'],
            'preferences.language' => ['required', 'string', 'size:2'],
            'notifications' => ['array'],
            'security' => ['array'],
            'integrations' => ['array'],
        ]);

        try {
            DB::transaction(function () use ($request, $organization, $user) {
                // Update organization basic info
                $organization->update([
                    'name' => $request->company_name,
                    'description' => $request->company_description,
                    'website' => $request->company_website,
                    'phone' => $request->company_phone,
                    'email' => $request->company_email,
                    'address' => $request->company_address,
                ]);

                // Settings to update
                $settingsToUpdate = [
                    'company_name' => $request->company_name,
                    'company_description' => $request->company_description,
                    'company_website' => $request->company_website,
                    'company_phone' => $request->company_phone,
                    'company_email' => $request->company_email,
                    'company_address' => $request->company_address,
                    'preferences' => $request->preferences,
                    'notifications' => $request->notifications,
                    'security' => $request->security,
                    'integrations' => $request->integrations,
                ];

                // Update or create settings
                foreach ($settingsToUpdate as $key => $value) {
                    OrganizationSettings::updateOrCreate(
                        [
                            'organization_id' => $organization->id,
                            'key' => $key,
                        ],
                        [
                            'value' => $value,
                            'type' => 'general',
                        ]
                    );
                }

                // Sync company address to InventoryLocation
                $companyAddress = $request->company_address;
                if (! empty($companyAddress['street']) || ! empty($companyAddress['city'])) {
                    $locationData = [
                        'name' => $request->company_name.' - Main Office',
                        'type' => 'warehouse',
                        'address_line1' => $companyAddress['street'] ?? null,
                        'city' => $companyAddress['city'] ?? null,
                        'state' => $companyAddress['state'] ?? null,
                        'postal_code' => $companyAddress['zip'] ?? null,
                        'country' => $companyAddress['country'] ?? 'US',
                        'is_active' => true,
                    ];

                    $defaultLocation = InventoryLocation::where('organization_id', $organization->id)
                        ->where('is_default', true)
                        ->first();

                    $existingHQ = InventoryLocation::where('organization_id', $organization->id)
                        ->where('code', 'HQ')
                        ->first();

                    if ($existingHQ) {
                        // Update existing HQ location
                        $existingHQ->update($locationData);
                    } elseif (! $defaultLocation) {
                        // No default exists - create as default
                        InventoryLocation::create(array_merge($locationData, [
                            'organization_id' => $organization->id,
                            'code' => 'HQ',
                            'is_default' => true,
                        ]));
                    } else {
                        // Default exists but no HQ - create as additional non-default
                        InventoryLocation::create(array_merge($locationData, [
                            'organization_id' => $organization->id,
                            'code' => 'HQ',
                            'is_default' => false,
                        ]));
                    }
                }

                // Log activity
                $user->logActivity('organization_settings_updated', 'Updated organization settings', [
                    'organization_id' => $organization->id,
                    'updated_fields' => array_keys($settingsToUpdate),
                ]);
            });

            return back()->with('success', 'Organization settings updated successfully!');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to update settings. Please try again.']);
        }
    }

    /**
     * Organization branding settings
     */
    public function branding(Request $request)
    {
        $user = $request->user();
        $organization = $user->organization;

        $this->authorize('manageOrganization', $organization);

        $brandingSettings = OrganizationSettings::where('organization_id', $organization->id)
            ->where('key', 'branding')
            ->first();

        $branding = $brandingSettings ? $brandingSettings->value : [
            'logo_url' => '',
            'primary_color' => '#3B82F6',
            'secondary_color' => '#10B981',
            'accent_color' => '#F59E0B',
            'favicon_url' => '',
            'login_background_url' => '',
            'email_logo_url' => '',
        ];

        return Inertia::render('Organization/Branding', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'branding' => $branding,
            'can_edit' => $user->hasPermission('organization.edit'),
        ]);
    }

    /**
     * Update organization branding
     */
    public function updateBranding(Request $request)
    {
        $user = $request->user();
        $organization = $user->organization;

        $this->authorize('manageOrganization', $organization);

        $request->validate([
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg', 'max:2048'],
            'favicon' => ['nullable', 'image', 'mimes:png,jpg,jpeg,ico', 'max:1024'],
            'login_background' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:5120'],
            'email_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg', 'max:2048'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        try {
            $brandingData = [
                'primary_color' => $request->primary_color,
                'secondary_color' => $request->secondary_color,
                'accent_color' => $request->accent_color,
            ];

            // Handle file uploads
            $fileUploads = [
                'logo' => 'logo_url',
                'favicon' => 'favicon_url',
                'login_background' => 'login_background_url',
                'email_logo' => 'email_logo_url',
            ];

            foreach ($fileUploads as $inputName => $settingKey) {
                if ($request->hasFile($inputName)) {
                    // Delete old file if exists
                    $existingSetting = OrganizationSettings::where('organization_id', $organization->id)
                        ->where('key', 'branding')
                        ->first();

                    if ($existingSetting && isset($existingSetting->value[$settingKey])) {
                        Storage::disk('public')->delete($existingSetting->value[$settingKey]);
                    }

                    // Store new file
                    $path = $request->file($inputName)->store(
                        "organizations/{$organization->id}/branding",
                        'public'
                    );

                    $brandingData[$settingKey] = $path;
                }
            }

            // Update or create branding settings
            OrganizationSettings::updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'key' => 'branding',
                ],
                [
                    'value' => array_merge(
                        OrganizationSettings::where('organization_id', $organization->id)
                            ->where('key', 'branding')
                            ->first()?->value ?? [],
                        $brandingData
                    ),
                    'type' => 'branding',
                ]
            );

            // Log activity
            $user->logActivity('organization_branding_updated', 'Updated organization branding', [
                'organization_id' => $organization->id,
                'updated_items' => array_keys(array_filter($brandingData)),
            ]);

            return back()->with('success', 'Branding updated successfully!');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to update branding. Please try again.']);
        }
    }

    /**
     * Organization analytics dashboard
     */
    public function analytics(Request $request)
    {
        $user = $request->user();
        $organization = $user->organization;

        $this->authorize('viewOrganizationAnalytics', $organization);

        $dateRange = $request->input('range', '30'); // 7, 30, 90 days
        $endDate = now();
        $startDate = now()->subDays((int) $dateRange);

        // User analytics
        $userAnalytics = [
            'total_users' => User::inOrganization($organization->id)->count(),
            'active_users' => User::inOrganization($organization->id)->active()->count(),
            'new_users_period' => User::inOrganization($organization->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count(),
            'user_growth' => $this->calculateUserGrowth($organization, $startDate, $endDate),
            'users_by_role' => $this->getUsersByRole($organization),
            'users_by_department' => $this->getUsersByDepartment($organization),
            'login_activity' => $this->getLoginActivity($organization, $startDate, $endDate),
        ];

        // Team analytics
        $teamAnalytics = [
            'total_teams' => Team::forOrganization($organization->id)->count(),
            'active_teams' => Team::forOrganization($organization->id)->active()->count(),
            'teams_by_department' => Team::forOrganization($organization->id)
                ->select('department', DB::raw('count(*) as count'))
                ->groupBy('department')
                ->get()
                ->pluck('count', 'department'),
            'average_team_size' => $this->getAverageTeamSize($organization),
            'team_performance' => $this->getTeamPerformanceMetrics($organization),
        ];

        // Activity analytics
        $activityAnalytics = [
            'total_activities' => UserActivityLog::where('organization_id', $organization->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count(),
            'activities_by_type' => UserActivityLog::where('organization_id', $organization->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->select('action', DB::raw('count(*) as count'))
                ->groupBy('action')
                ->get()
                ->pluck('count', 'action'),
            'daily_activity' => $this->getDailyActivity($organization, $startDate, $endDate),
            'most_active_users' => $this->getMostActiveUsers($organization, $startDate, $endDate),
        ];

        return Inertia::render('Organization/Analytics', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'date_range' => $dateRange,
            'analytics' => [
                'users' => $userAnalytics,
                'teams' => $teamAnalytics,
                'activity' => $activityAnalytics,
            ],
            'can_export' => $user->hasPermission('analytics.export'),
        ]);
    }

    /**
     * Organization audit log
     */
    public function auditLog(Request $request)
    {
        $user = $request->user();
        $organization = $user->organization;

        $this->authorize('viewOrganizationAuditLog', $organization);

        $query = UserActivityLog::where('organization_id', $organization->id)
            ->with(['user:id,name,email']);

        // Filters
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $query->where('description', 'like', '%'.$request->search.'%');
        }

        $activities = $query->orderBy('created_at', 'desc')
            ->paginate(50)
            ->withQueryString();

        // Transform data
        $activities->through(function ($activity) {
            return [
                'id' => $activity->id,
                'user' => [
                    'name' => $activity->user->name ?? 'Deleted User',
                    'email' => $activity->user->email ?? 'N/A',
                ],
                'action' => $activity->action,
                'description' => $activity->description,
                'metadata' => $activity->metadata,
                'ip_address' => $activity->ip_address,
                'user_agent' => $activity->user_agent,
                'created_at' => $activity->created_at->format('M j, Y g:i A'),
                'time_ago' => $activity->created_at->diffForHumans(),
            ];
        });

        // Available filters
        $availableActions = UserActivityLog::where('organization_id', $organization->id)
            ->distinct()
            ->pluck('action')
            ->sort()
            ->values();

        $availableUsers = User::inOrganization($organization->id)
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return Inertia::render('Organization/AuditLog', [
            'activities' => $activities,
            'filters' => $request->only(['user_id', 'action', 'date_from', 'date_to', 'search']),
            'available_actions' => $availableActions,
            'available_users' => $availableUsers,
            'can_export' => $user->hasPermission('audit_log.export'),
        ]);
    }

    /**
     * Helper Methods
     */
    private function getTimezoneOptions()
    {
        return [
            ['value' => 'UTC', 'label' => 'UTC'],
            ['value' => 'America/New_York', 'label' => 'Eastern Time (US)'],
            ['value' => 'America/Chicago', 'label' => 'Central Time (US)'],
            ['value' => 'America/Denver', 'label' => 'Mountain Time (US)'],
            ['value' => 'America/Los_Angeles', 'label' => 'Pacific Time (US)'],
            ['value' => 'Europe/London', 'label' => 'London'],
            ['value' => 'Europe/Paris', 'label' => 'Paris'],
            ['value' => 'Europe/Berlin', 'label' => 'Berlin'],
            ['value' => 'Asia/Tokyo', 'label' => 'Tokyo'],
            ['value' => 'Asia/Shanghai', 'label' => 'Shanghai'],
            ['value' => 'Australia/Sydney', 'label' => 'Sydney'],
        ];
    }

    private function getCurrencyOptions()
    {
        return [
            ['value' => 'USD', 'label' => 'US Dollar ($)', 'symbol' => '$'],
            ['value' => 'EUR', 'label' => 'Euro (€)', 'symbol' => '€'],
            ['value' => 'GBP', 'label' => 'British Pound (£)', 'symbol' => '£'],
            ['value' => 'CAD', 'label' => 'Canadian Dollar (C$)', 'symbol' => 'C$'],
            ['value' => 'AUD', 'label' => 'Australian Dollar (A$)', 'symbol' => 'A$'],
            ['value' => 'JPY', 'label' => 'Japanese Yen (¥)', 'symbol' => '¥'],
        ];
    }

    private function getLanguageOptions()
    {
        return [
            ['value' => 'en', 'label' => 'English'],
            ['value' => 'es', 'label' => 'Spanish'],
            ['value' => 'fr', 'label' => 'French'],
            ['value' => 'de', 'label' => 'German'],
            ['value' => 'it', 'label' => 'Italian'],
            ['value' => 'ja', 'label' => 'Japanese'],
            ['value' => 'zh', 'label' => 'Chinese'],
        ];
    }

    private function calculateUserGrowth($organization, $startDate, $endDate)
    {
        $previousPeriod = now()->subDays(($endDate->diffInDays($startDate)) * 2);
        $currentUsers = User::inOrganization($organization->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
        $previousUsers = User::inOrganization($organization->id)
            ->whereBetween('created_at', [$previousPeriod, $startDate])
            ->count();

        if ($previousUsers === 0) {
            return $currentUsers > 0 ? 100 : 0;
        }

        return round((($currentUsers - $previousUsers) / $previousUsers) * 100, 1);
    }

    private function getUsersByRole($organization)
    {
        return DB::table('users')
            ->join('user_roles', 'users.id', '=', 'user_roles.user_id')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('users.organization_id', $organization->id)
            ->select('roles.name', DB::raw('count(*) as count'))
            ->groupBy('roles.id', 'roles.name')
            ->get()
            ->pluck('count', 'name');
    }

    private function getUsersByDepartment($organization)
    {
        return User::inOrganization($organization->id)
            ->whereNotNull('department')
            ->select('department', DB::raw('count(*) as count'))
            ->groupBy('department')
            ->get()
            ->pluck('count', 'department');
    }

    private function getLoginActivity($organization, $startDate, $endDate)
    {
        return UserActivityLog::where('organization_id', $organization->id)
            ->where('action', 'login')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    private function getAverageTeamSize($organization)
    {
        $teams = Team::forOrganization($organization->id)->withCount('users')->get();

        return $teams->count() > 0 ? round($teams->avg('users_count'), 1) : 0;
    }

    private function getTeamPerformanceMetrics($organization)
    {
        return Team::forOrganization($organization->id)
            ->select('id', 'name', 'performance_metrics')
            ->get()
            ->map(function ($team) {
                return [
                    'name' => $team->name,
                    'score' => $team->getProductivityScore(),
                ];
            });
    }

    private function getDailyActivity($organization, $startDate, $endDate)
    {
        return UserActivityLog::where('organization_id', $organization->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    private function getMostActiveUsers($organization, $startDate, $endDate)
    {
        return UserActivityLog::where('organization_id', $organization->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('user_id', DB::raw('count(*) as activity_count'))
            ->with('user:id,name,email')
            ->groupBy('user_id')
            ->orderBy('activity_count', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'user' => [
                        'name' => $item->user->name ?? 'Deleted User',
                        'email' => $item->user->email ?? 'N/A',
                    ],
                    'activity_count' => $item->activity_count,
                ];
            });
    }
}
