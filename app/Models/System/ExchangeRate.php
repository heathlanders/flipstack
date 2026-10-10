<?php

namespace App\Models\System;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'base_currency',
        'target_currency',
        'rate',
        'source',
        'fetched_at',
    ];

    protected $casts = [
        'rate' => 'decimal:8',
        'fetched_at' => 'datetime',
    ];

    /**
     * Scope to filter by currency pair.
     */
    public function scopeForPair(Builder $query, string $base, string $target): Builder
    {
        return $query->where('base_currency', $base)
            ->where('target_currency', $target);
    }

    /**
     * Scope to order by most recent fetched_at.
     */
    public function scopeLatest(Builder $query): Builder
    {
        return $query->orderBy('fetched_at', 'desc');
    }

    /**
     * Get the exchange rate for a currency pair, optionally closest to a given date.
     */
    public static function getRate(string $from, string $to, ?Carbon $date = null): ?float
    {
        if ($from === $to) {
            return 1.0;
        }

        $query = static::forPair($from, $to);

        if ($date) {
            $query->where('fetched_at', '<=', $date)
                ->orderBy('fetched_at', 'desc');
        } else {
            $query->orderBy('fetched_at', 'desc');
        }

        $rate = $query->first();

        if ($rate) {
            return (float) $rate->rate;
        }

        // Try inverse pair
        $inverseQuery = static::forPair($to, $from);

        if ($date) {
            $inverseQuery->where('fetched_at', '<=', $date)
                ->orderBy('fetched_at', 'desc');
        } else {
            $inverseQuery->orderBy('fetched_at', 'desc');
        }

        $inverseRate = $inverseQuery->first();

        if ($inverseRate && (float) $inverseRate->rate > 0) {
            return 1.0 / (float) $inverseRate->rate;
        }

        return null;
    }
}
