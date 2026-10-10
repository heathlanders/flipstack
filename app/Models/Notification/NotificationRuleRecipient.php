<?php

namespace App\Models\Notification;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationRuleRecipient extends Model
{
    public const TYPE_USER = 'user';

    public const TYPE_ROLE = 'role';

    public const TYPE_EXTERNAL_EMAIL = 'external_email';

    public const TYPE_SUPPLIER = 'supplier';

    public const TYPE_CUSTOMER = 'customer';

    protected $fillable = [
        'rule_id',
        'recipient_type',
        'recipient_value',
        'escalation_step',
    ];

    protected $casts = [
        'escalation_step' => 'integer',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(NotificationRule::class, 'rule_id');
    }
}
