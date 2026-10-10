<?php

namespace App\Models\Ebay;

use App\Models\Integration\IntegrationCredential;
use App\Models\Organization\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EbayPolicy extends Model
{
    protected $table = 'ebay_policies';

    protected $fillable = [
        'organization_id',
        'integration_credential_id',
        'ebay_policy_id',
        'policy_type',
        'name',
        'description',
        'marketplace_id',
        'policy_data',
        'is_default',
        'is_active',
        'last_synced_at',
    ];

    protected $casts = [
        'policy_data' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    const TYPE_FULFILLMENT = 'fulfillment';

    const TYPE_PAYMENT = 'payment';

    const TYPE_RETURN = 'return';

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function integrationCredential(): BelongsTo
    {
        return $this->belongsTo(IntegrationCredential::class);
    }

    public function rulesAsFulfillment(): HasMany
    {
        return $this->hasMany(EbayPolicyRule::class, 'fulfillment_policy_id');
    }

    public function rulesAsPayment(): HasMany
    {
        return $this->hasMany(EbayPolicyRule::class, 'payment_policy_id');
    }

    public function rulesAsReturn(): HasMany
    {
        return $this->hasMany(EbayPolicyRule::class, 'return_policy_id');
    }

    public function scopeForCredential(Builder $query, int $credentialId): Builder
    {
        return $query->where('integration_credential_id', $credentialId);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('policy_type', $type);
    }

    public function scopeFulfillment(Builder $query): Builder
    {
        return $query->where('policy_type', self::TYPE_FULFILLMENT);
    }

    public function scopePayment(Builder $query): Builder
    {
        return $query->where('policy_type', self::TYPE_PAYMENT);
    }

    public function scopeReturn(Builder $query): Builder
    {
        return $query->where('policy_type', self::TYPE_RETURN);
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function markAsDefault(): self
    {
        // Remove default from other policies of same type and credential
        static::where('integration_credential_id', $this->integration_credential_id)
            ->where('policy_type', $this->policy_type)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);

        return $this;
    }

    public function isShippingCalculated(): bool
    {
        if ($this->policy_type !== self::TYPE_FULFILLMENT) {
            return false;
        }

        $policyData = $this->policy_data ?? [];

        // Check for calculated shipping in shippingOptions
        if (isset($policyData['shippingOptions'])) {
            foreach ($policyData['shippingOptions'] as $option) {
                if (isset($option['costType']) && $option['costType'] === 'CALCULATED') {
                    return true;
                }
            }
        }

        return false;
    }

    public function isFreeShipping(): bool
    {
        if ($this->policy_type !== self::TYPE_FULFILLMENT) {
            return false;
        }

        $policyData = $this->policy_data ?? [];

        if (isset($policyData['shippingOptions'])) {
            foreach ($policyData['shippingOptions'] as $option) {
                if (isset($option['costType']) && $option['costType'] === 'FREE') {
                    return true;
                }
            }
        }

        return false;
    }

    public function getHandlingTimeAttribute(): ?int
    {
        if ($this->policy_type !== self::TYPE_FULFILLMENT) {
            return null;
        }

        $policyData = $this->policy_data ?? [];

        return $policyData['handlingTime']['value'] ?? null;
    }

    public static function getTypes(): array
    {
        return [
            self::TYPE_FULFILLMENT => 'Fulfillment',
            self::TYPE_PAYMENT => 'Payment',
            self::TYPE_RETURN => 'Return',
        ];
    }
}
