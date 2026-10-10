<?php

namespace App\Models\Communication;

use App\Models\User\User;
use App\Traits\HasUuid;
use App\Traits\LogsAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceMessageReply extends Model
{
    use HasFactory, HasUuid, LogsAudit;

    protected ?string $auditCategory = 'communication';

    protected ?string $auditActionPrefix = 'message.reply';

    protected array $auditExclude = ['body', 'raw_payload'];

    // Status constants
    const STATUS_DRAFT = 'draft';

    const STATUS_SENT = 'sent';

    const STATUS_FAILED = 'failed';

    protected $fillable = [
        'message_id',
        'body',
        'sent_by',
        'sent_at',
        'marketplace_response_id',
        'status',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    // Relations

    public function message(): BelongsTo
    {
        return $this->belongsTo(MarketplaceMessage::class, 'message_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
