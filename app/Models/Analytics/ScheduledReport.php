<?php

namespace App\Models\Analytics;

use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledReport extends Model
{
    use HasFactory, HasUuids;

    public const FREQ_DAILY = 'daily';

    public const FREQ_WEEKLY = 'weekly';

    public const FREQ_MONTHLY = 'monthly';

    protected $fillable = [
        'organization_id',
        'owner_id',
        'name',
        'view',
        'filters',
        'format',
        'frequency',
        'day_of_period',
        'send_time',
        'recipients',
        'is_active',
        'last_sent_at',
        'next_due_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'recipients' => 'array',
        'is_active' => 'boolean',
        'last_sent_at' => 'datetime',
        'next_due_at' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Compute the next due timestamp from the configuration. Anchored on
     * either the last send or now, whichever is later.
     */
    public function calculateNextDueAt(?Carbon $from = null): Carbon
    {
        $from = ($from ?? $this->last_sent_at ?? now())->copy();
        [$hour, $minute] = array_pad(explode(':', $this->send_time ?? '07:00'), 2, 0);
        $hour = (int) $hour;
        $minute = (int) $minute;

        $next = match ($this->frequency) {
            self::FREQ_DAILY => $from->copy()->addDay()->setTime($hour, $minute),
            self::FREQ_WEEKLY => $from->copy()->addWeek()->next($this->day_of_period ?? Carbon::MONDAY)->setTime($hour, $minute),
            self::FREQ_MONTHLY => $from->copy()->addMonthNoOverflow()->day(min(28, $this->day_of_period ?? 1))->setTime($hour, $minute),
            default => $from->copy()->addWeek()->setTime($hour, $minute),
        };

        // If computed next is still in the past (clock drift / first-time setup), bump forward
        while ($next->lessThan(now())) {
            $next = match ($this->frequency) {
                self::FREQ_DAILY => $next->copy()->addDay(),
                self::FREQ_WEEKLY => $next->copy()->addWeek(),
                self::FREQ_MONTHLY => $next->copy()->addMonthNoOverflow(),
                default => $next->copy()->addWeek(),
            };
        }

        return $next;
    }
}
