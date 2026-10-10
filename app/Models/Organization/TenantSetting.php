<?php

namespace App\Models\Organization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSetting extends Model
{
    protected $fillable = [
        'team_id',
        'category',
        'key',
        'value',
        'type',
        'description',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the value with proper type casting
     */
    public function getValue(): mixed
    {
        return match ($this->type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $this->value,
            'float' => (float) $this->value,
            'json' => json_decode($this->value, true),
            'encrypted' => decrypt($this->value),
            default => $this->value,
        };
    }

    /**
     * Set the value with proper type handling
     */
    public function setValue(mixed $value): void
    {
        $this->value = match ($this->type) {
            'boolean' => $value ? '1' : '0',
            'json' => json_encode($value),
            'encrypted' => encrypt($value),
            default => (string) $value,
        };
    }

    /**
     * Scope to get settings by category
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to get settings by team
     */
    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * Get a specific setting value
     */
    public static function get(int $teamId, string $category, string $key, mixed $default = null): mixed
    {
        $setting = static::where('team_id', $teamId)
            ->where('category', $category)
            ->where('key', $key)
            ->first();

        return $setting ? $setting->getValue() : $default;
    }

    /**
     * Set a specific setting value
     */
    public static function set(int $teamId, string $category, string $key, mixed $value, string $type = 'string'): self
    {
        $setting = static::updateOrCreate(
            [
                'team_id' => $teamId,
                'category' => $category,
                'key' => $key,
            ],
            [
                'type' => $type,
            ]
        );

        $setting->type = $type;
        $setting->setValue($value);
        $setting->save();

        return $setting;
    }
}
