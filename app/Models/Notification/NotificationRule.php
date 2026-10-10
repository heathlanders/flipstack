<?php

namespace App\Models\Notification;

use App\Models\Organization\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationRule extends Model
{
    protected $fillable = [
        'organization_id',
        'event_key',
        'active',
        'priority_override',
        'channel_overrides',
        'escalation_minutes',
        'dedup_minutes',
        'description',
    ];

    protected $casts = [
        'active' => 'boolean',
        'channel_overrides' => 'array',
        'escalation_minutes' => 'integer',
        'dedup_minutes' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(NotificationRuleRecipient::class, 'rule_id');
    }

    public function recipientsAtStep(int $step): HasMany
    {
        return $this->hasMany(NotificationRuleRecipient::class, 'rule_id')
            ->where('escalation_step', $step);
    }
}
