<?php

namespace App\Models\Organization;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationSettings extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'key',
        'value',
        'type',
        'is_encrypted',
        'metadata',
    ];

    protected $casts = [
        'value' => 'array',
        'metadata' => 'array',
        'is_encrypted' => 'boolean',
    ];

    /**
     * Relationships
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Scopes
     */
    public function scopeForOrganization($query, $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByKey($query, $key)
    {
        return $query->where('key', $key);
    }

    /**
     * Helper Methods
     */
    public static function get(int $organizationId, string $key, $default = null)
    {
        $setting = static::forOrganization($organizationId)
            ->where('key', $key)
            ->first();

        return $setting ? $setting->value : $default;
    }

    public static function set(int $organizationId, string $key, $value, string $type = 'general'): self
    {
        return static::updateOrCreate(
            [
                'organization_id' => $organizationId,
                'key' => $key,
            ],
            [
                'value' => $value,
                'type' => $type,
            ]
        );
    }

    public static function getByType(int $organizationId, string $type): \Illuminate\Support\Collection
    {
        return static::forOrganization($organizationId)
            ->ofType($type)
            ->get()
            ->pluck('value', 'key');
    }

    public static function getAllForOrganization(int $organizationId): \Illuminate\Support\Collection
    {
        return static::forOrganization($organizationId)
            ->get()
            ->groupBy('type')
            ->map(function ($settings) {
                return $settings->pluck('value', 'key');
            });
    }

    public static function setMultiple(int $organizationId, array $settings, string $type = 'general'): void
    {
        foreach ($settings as $key => $value) {
            static::set($organizationId, $key, $value, $type);
        }
    }

    public static function deleteSetting(int $organizationId, string $key): bool
    {
        return static::forOrganization($organizationId)
            ->where('key', $key)
            ->delete();
    }

    public static function deleteByType(int $organizationId, string $type): int
    {
        return static::forOrganization($organizationId)
            ->ofType($type)
            ->delete();
    }

    public function encrypt(): self
    {
        if (! $this->is_encrypted && ! empty($this->value)) {
            $this->update([
                'value' => encrypt($this->value),
                'is_encrypted' => true,
            ]);
        }

        return $this;
    }

    public function decrypt(): self
    {
        if ($this->is_encrypted && ! empty($this->value)) {
            try {
                $decryptedValue = decrypt($this->value);
                $this->update([
                    'value' => $decryptedValue,
                    'is_encrypted' => false,
                ]);
            } catch (\Exception $e) {
                // Handle decryption failure
                \Log::warning('Failed to decrypt organization setting', [
                    'setting_id' => $this->id,
                    'organization_id' => $this->organization_id,
                    'key' => $this->key,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this;
    }

    public function getValue()
    {
        if ($this->is_encrypted && ! empty($this->value)) {
            try {
                return decrypt($this->value);
            } catch (\Exception $e) {
                \Log::warning('Failed to decrypt organization setting value', [
                    'setting_id' => $this->id,
                    'key' => $this->key,
                ]);

                return null;
            }
        }

        return $this->value;
    }

    public function setValue($value, bool $encrypt = false): self
    {
        $this->update([
            'value' => $encrypt ? encrypt($value) : $value,
            'is_encrypted' => $encrypt,
        ]);

        return $this;
    }

    /**
     * Common setting methods for frequently accessed settings
     */
    public static function getBranding(int $organizationId): array
    {
        return static::get($organizationId, 'branding', [
            'logo_url' => '',
            'primary_color' => '#3B82F6',
            'secondary_color' => '#10B981',
            'accent_color' => '#F59E0B',
            'favicon_url' => '',
            'login_background_url' => '',
            'email_logo_url' => '',
        ]);
    }

    public static function setBranding(int $organizationId, array $branding): void
    {
        static::set($organizationId, 'branding', $branding, 'branding');
    }

    public static function getPreferences(int $organizationId): array
    {
        return static::get($organizationId, 'preferences', [
            'timezone' => 'UTC',
            'date_format' => 'M j, Y',
            'time_format' => '12',
            'currency' => 'USD',
            'language' => 'en',
        ]);
    }

    public static function setPreferences(int $organizationId, array $preferences): void
    {
        static::set($organizationId, 'preferences', $preferences, 'preferences');
    }

    public static function getNotifications(int $organizationId): array
    {
        return static::get($organizationId, 'notifications', [
            'email_notifications' => true,
            'slack_notifications' => false,
            'sms_notifications' => false,
            'notification_frequency' => 'real_time',
        ]);
    }

    public static function setNotifications(int $organizationId, array $notifications): void
    {
        static::set($organizationId, 'notifications', $notifications, 'notifications');
    }

    public static function getSecurity(int $organizationId): array
    {
        return static::get($organizationId, 'security', [
            'require_2fa' => false,
            'password_expiry_days' => 90,
            'session_timeout_minutes' => 480,
            'allowed_domains' => [],
        ]);
    }

    public static function setSecurity(int $organizationId, array $security): void
    {
        static::set($organizationId, 'security', $security, 'security');
    }

    public static function getIntegrations(int $organizationId): array
    {
        return static::get($organizationId, 'integrations', [
            'slack_webhook' => '',
            'email_provider' => 'default',
            'backup_provider' => 'local',
        ]);
    }

    public static function setIntegrations(int $organizationId, array $integrations): void
    {
        // Encrypt sensitive integration data
        static::updateOrCreate(
            [
                'organization_id' => $organizationId,
                'key' => 'integrations',
            ],
            [
                'value' => encrypt($integrations),
                'type' => 'integrations',
                'is_encrypted' => true,
            ]
        );
    }

    /**
     * Schema for validation
     */
    public static function getSettingsSchema(): array
    {
        return [
            'branding' => [
                'logo_url' => 'nullable|url',
                'primary_color' => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
                'secondary_color' => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
                'accent_color' => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
                'favicon_url' => 'nullable|url',
                'login_background_url' => 'nullable|url',
                'email_logo_url' => 'nullable|url',
            ],
            'preferences' => [
                'timezone' => 'required|string',
                'date_format' => 'required|string',
                'time_format' => 'required|in:12,24',
                'currency' => 'required|string|size:3',
                'language' => 'required|string|size:2',
            ],
            'notifications' => [
                'email_notifications' => 'boolean',
                'slack_notifications' => 'boolean',
                'sms_notifications' => 'boolean',
                'notification_frequency' => 'required|in:real_time,hourly,daily,weekly',
            ],
            'security' => [
                'require_2fa' => 'boolean',
                'password_expiry_days' => 'integer|min:0|max:365',
                'session_timeout_minutes' => 'integer|min:15|max:1440',
                'allowed_domains' => 'array',
            ],
        ];
    }

    public static function validateSettings(string $type, array $data): array
    {
        $schema = static::getSettingsSchema();
        $rules = $schema[$type] ?? [];

        return \Validator::make($data, $rules)->validate();
    }
}
