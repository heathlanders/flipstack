<?php

namespace App\Models\Marketing;

use App\Models\Organization\Organization;
use App\Traits\BelongsToOrganization;
use App\Traits\HasUuid;
use App\Traits\LogsAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailCampaign extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, LogsAudit;

    protected ?string $auditCategory = 'marketing';

    protected ?string $auditActionPrefix = 'campaign';

    protected array $auditExclude = ['sent_count', 'open_count', 'click_count', 'bounce_count', 'unsubscribe_count'];

    public const TYPE_POST_PURCHASE_FOLLOWUP = 'post_purchase_followup';

    public const TYPE_REVIEW_REQUEST = 'review_request';

    public const TYPE_RESTOCK_ALERT = 'restock_alert';

    public const TYPE_WIN_BACK = 'win_back';

    public const TYPES = [
        self::TYPE_POST_PURCHASE_FOLLOWUP => 'Post-Purchase Follow-up',
        self::TYPE_REVIEW_REQUEST => 'Review Request',
        self::TYPE_RESTOCK_ALERT => 'Restock Alert',
        self::TYPE_WIN_BACK => 'Win Back',
    ];

    public const TRIGGER_ORDER_DELIVERED = 'order_delivered';

    public const TRIGGER_ORDER_SHIPPED = 'order_shipped';

    public const TRIGGER_DAYS_AFTER_DELIVERY = 'days_after_delivery';

    public const TRIGGERS = [
        self::TRIGGER_ORDER_DELIVERED => 'Order Delivered',
        self::TRIGGER_ORDER_SHIPPED => 'Order Shipped',
        self::TRIGGER_DAYS_AFTER_DELIVERY => 'Days After Delivery',
    ];

    protected $fillable = [
        'organization_id',
        'name',
        'type',
        'trigger_event',
        'trigger_delay_hours',
        'subject_template',
        'body_template',
        'is_active',
        'sent_count',
        'open_count',
        'click_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'trigger_delay_hours' => 'integer',
        'sent_count' => 'integer',
        'open_count' => 'integer',
        'click_count' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function sends(): HasMany
    {
        return $this->hasMany(EmailCampaignSend::class, 'campaign_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function openRate(): float
    {
        if ($this->sent_count === 0) {
            return 0.0;
        }

        return round(($this->open_count / $this->sent_count) * 100, 2);
    }

    public function clickRate(): float
    {
        if ($this->sent_count === 0) {
            return 0.0;
        }

        return round(($this->click_count / $this->sent_count) * 100, 2);
    }
}
