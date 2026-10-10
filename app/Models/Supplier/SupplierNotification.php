<?php

namespace App\Models\Supplier;

use App\Models\Inventory\Supplier;
use App\Models\Organization\Organization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierNotification extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'supplier_id',
        'organization_id',
        'title',
        'message',
        'short_message',
        'type',
        'category',
        'priority',
        'related_type',
        'related_id',
        'action_url',
        'action_text',
        'is_read',
        'read_at',
        'email_sent',
        'email_sent_at',
        'sms_sent',
        'sms_sent_at',
        'push_sent',
        'push_sent_at',
        'scheduled_for',
        'expires_at',
        'data',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'email_sent' => 'boolean',
        'email_sent_at' => 'datetime',
        'sms_sent' => 'boolean',
        'sms_sent_at' => 'datetime',
        'push_sent' => 'boolean',
        'push_sent_at' => 'datetime',
        'scheduled_for' => 'datetime',
        'expires_at' => 'datetime',
        'data' => 'array',
    ];

    // Relationships
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function related(): MorphTo
    {
        return $this->morphTo('related', 'related_type', 'related_id');
    }

    // Scopes
    public function scopeForSupplier($query, $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    public function scopeCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopePriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeUrgent($query)
    {
        return $query->where('priority', 'urgent');
    }

    public function scopeActionRequired($query)
    {
        return $query->where('type', 'action_required');
    }

    public function scopeScheduled($query)
    {
        return $query->whereNotNull('scheduled_for')
            ->where('scheduled_for', '>', now());
    }

    public function scopeReadyToSend($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('scheduled_for')
                ->orWhere('scheduled_for', '<=', now());
        })->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    public function scopeExpired($query)
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    public function scopePendingEmail($query)
    {
        return $query->where('email_sent', false);
    }

    // Methods
    public function markAsRead(): self
    {
        if (! $this->is_read) {
            $this->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }

        return $this;
    }

    public function markAsUnread(): self
    {
        $this->update([
            'is_read' => false,
            'read_at' => null,
        ]);

        return $this;
    }

    public function markEmailSent(): self
    {
        $this->update([
            'email_sent' => true,
            'email_sent_at' => now(),
        ]);

        return $this;
    }

    public function markSmsSent(): self
    {
        $this->update([
            'sms_sent' => true,
            'sms_sent_at' => now(),
        ]);

        return $this;
    }

    public function markPushSent(): self
    {
        $this->update([
            'push_sent' => true,
            'push_sent_at' => now(),
        ]);

        return $this;
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at <= now();
    }

    public function isScheduled(): bool
    {
        return $this->scheduled_for && $this->scheduled_for > now();
    }

    public function getIconAttribute(): string
    {
        return match ($this->type) {
            'info' => 'information-circle',
            'success' => 'check-circle',
            'warning' => 'exclamation-triangle',
            'error' => 'x-circle',
            'action_required' => 'bell',
            default => 'bell',
        };
    }

    public function getColorAttribute(): string
    {
        return match ($this->type) {
            'info' => 'blue',
            'success' => 'green',
            'warning' => 'yellow',
            'error' => 'red',
            'action_required' => 'purple',
            default => 'gray',
        };
    }

    // Static Methods
    public static function createForSupplier(
        Supplier $supplier,
        string $title,
        string $message,
        string $type = 'info',
        string $category = 'system',
        array $options = []
    ): self {
        return self::create(array_merge([
            'supplier_id' => $supplier->id,
            'organization_id' => $supplier->organization_id,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'category' => $category,
            'priority' => 'normal',
        ], $options));
    }

    public static function tierChange(
        Supplier $supplier,
        SupplierTierHistory $tierHistory
    ): self {
        $isPromotion = $tierHistory->isPromotion();
        $title = $isPromotion ? 'Congratulations! Tier Promotion' : 'Tier Status Change';

        $message = $isPromotion
            ? "You've been promoted to {$tierHistory->toTier->tier_name}! This unlocks new benefits including better commission rates and faster settlements."
            : "Your supplier tier has been changed to {$tierHistory->toTier->tier_name}. Please review your performance metrics for improvement opportunities.";

        return self::createForSupplier(
            $supplier,
            $title,
            $message,
            $isPromotion ? 'success' : 'warning',
            'tier_change',
            [
                'priority' => 'high',
                'related_type' => SupplierTierHistory::class,
                'related_id' => $tierHistory->id,
                'action_url' => '/supplier/performance',
                'action_text' => 'View Performance',
                'data' => [
                    'from_tier' => $tierHistory->fromTier?->tier_name,
                    'to_tier' => $tierHistory->toTier->tier_name,
                    'change_type' => $tierHistory->change_type,
                ],
            ]
        );
    }

    public static function settlementReady(
        Supplier $supplier,
        Settlement $settlement
    ): self {
        return self::createForSupplier(
            $supplier,
            'Settlement Ready',
            "Your settlement for {$settlement->period_label} is ready. Amount: $".number_format($settlement->net_amount, 2),
            'success',
            'settlement',
            [
                'priority' => 'high',
                'related_type' => Settlement::class,
                'related_id' => $settlement->id,
                'action_url' => "/supplier/settlements/{$settlement->id}",
                'action_text' => 'View Settlement',
                'data' => [
                    'settlement_number' => $settlement->settlement_number,
                    'amount' => $settlement->net_amount,
                    'period' => $settlement->period_label,
                ],
            ]
        );
    }

    public static function paymentProcessed(
        Supplier $supplier,
        Settlement $settlement
    ): self {
        return self::createForSupplier(
            $supplier,
            'Payment Processed',
            'Payment of $'.number_format($settlement->net_amount, 2)." has been processed via {$settlement->payment_method}.",
            'success',
            'payment',
            [
                'priority' => 'normal',
                'related_type' => Settlement::class,
                'related_id' => $settlement->id,
                'action_url' => "/supplier/settlements/{$settlement->id}",
                'action_text' => 'View Details',
            ]
        );
    }

    public static function submissionApproved(
        Supplier $supplier,
        $submission,
        int $approvedCount
    ): self {
        return self::createForSupplier(
            $supplier,
            'Submission Approved',
            "{$approvedCount} items from your recent submission have been approved and are now listed for sale.",
            'success',
            'approval',
            [
                'related_type' => get_class($submission),
                'related_id' => $submission->id,
                'action_url' => '/supplier/submissions',
                'action_text' => 'View Submissions',
            ]
        );
    }

    public static function submissionRejected(
        Supplier $supplier,
        $submission,
        int $rejectedCount,
        ?string $reason = null
    ): self {
        $message = "{$rejectedCount} items from your recent submission were not approved.";
        if ($reason) {
            $message .= " Reason: {$reason}";
        }

        return self::createForSupplier(
            $supplier,
            'Submission Review Complete',
            $message,
            'warning',
            'rejection',
            [
                'related_type' => get_class($submission),
                'related_id' => $submission->id,
                'action_url' => '/supplier/submissions',
                'action_text' => 'View Details',
            ]
        );
    }

    public static function markAllAsRead(string $supplierId): int
    {
        return self::forSupplier($supplierId)
            ->unread()
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }
}
