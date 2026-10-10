<?php

namespace App\Models\Communication;

use App\Models\Catalog\Product;
use App\Models\Customer\Customer;
use App\Models\Fulfillment\Order;
use App\Models\Organization\Organization;
use App\Traits\BelongsToOrganization;
use App\Traits\HasUuid;
use App\Traits\LogsAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceMessage extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, LogsAudit;

    protected ?string $auditCategory = 'communication';

    protected ?string $auditActionPrefix = 'message';

    protected array $auditExclude = ['body', 'raw_payload', 'last_synced_at'];

    // Status constants
    const STATUS_UNREAD = 'unread';

    const STATUS_READ = 'read';

    const STATUS_REPLIED = 'replied';

    const STATUS_ARCHIVED = 'archived';

    // Direction constants
    const DIRECTION_INBOUND = 'inbound';

    const DIRECTION_OUTBOUND = 'outbound';

    // Marketplace constants
    const MARKETPLACE_EBAY = 'ebay';

    const MARKETPLACE_SHOPIFY = 'shopify';

    const MARKETPLACE_AMAZON = 'amazon';

    protected $fillable = [
        'organization_id',
        'customer_id',
        'order_id',
        'product_id',
        'marketplace',
        'marketplace_message_id',
        'marketplace_thread_id',
        'marketplace_item_id',
        'message_type',
        'direction',
        'subject',
        'body',
        'sender_name',
        'sender_id',
        'status',
        'replied_at',
        'read_at',
        'received_at',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'read_at' => 'datetime',
        'replied_at' => 'datetime',
    ];

    // Relations

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(MarketplaceMessageReply::class, 'message_id');
    }

    // Scopes

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_UNREAD);
    }

    public function scopeForThread(Builder $query, string $threadId): Builder
    {
        return $query->where('marketplace_thread_id', $threadId);
    }

    public function scopeInbound(Builder $query): Builder
    {
        return $query->where('direction', self::DIRECTION_INBOUND);
    }
}
