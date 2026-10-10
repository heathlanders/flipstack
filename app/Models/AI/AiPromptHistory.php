<?php

namespace App\Models\AI;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiPromptHistory extends Model
{
    use HasUuids;

    protected $table = 'ai_prompt_history';

    protected $fillable = [
        'prompt_id',
        'action',
        'previous_content',
        'new_content',
        'changed_fields',
        'changed_by',
        'change_reason',
    ];

    protected $casts = [
        'changed_fields' => 'array',
    ];

    /**
     * Valid actions
     */
    public const ACTIONS = [
        'created' => 'Created',
        'updated' => 'Updated',
        'activated' => 'Activated',
        'deactivated' => 'Deactivated',
        'duplicated' => 'Duplicated',
        'deleted' => 'Deleted',
        'restored' => 'Restored',
    ];

    /**
     * Relationships
     */
    public function prompt(): BelongsTo
    {
        return $this->belongsTo(AiCategoryPrompt::class, 'prompt_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Scopes
     */
    public function scopeForPrompt(Builder $query, string $promptId): Builder
    {
        return $query->where('prompt_id', $promptId);
    }

    public function scopeByAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('changed_by', $userId);
    }

    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Create a history entry for a prompt
     */
    public static function recordChange(
        AiCategoryPrompt $prompt,
        string $action,
        ?int $userId = null,
        ?string $previousContent = null,
        ?string $newContent = null,
        ?array $changedFields = null,
        ?string $reason = null
    ): self {
        return self::create([
            'prompt_id' => $prompt->id,
            'action' => $action,
            'previous_content' => $previousContent,
            'new_content' => $newContent,
            'changed_fields' => $changedFields,
            'changed_by' => $userId,
            'change_reason' => $reason,
        ]);
    }

    /**
     * Get action label
     */
    public function getActionLabel(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }

    /**
     * Check if content changed
     */
    public function hasContentChange(): bool
    {
        return $this->previous_content !== null || $this->new_content !== null;
    }

    /**
     * Get diff summary of changes
     */
    public function getChangeSummary(): string
    {
        if (! $this->changed_fields) {
            return $this->getActionLabel();
        }

        $fields = is_array($this->changed_fields) ? $this->changed_fields : [];
        $count = count($fields);

        if ($count === 0) {
            return $this->getActionLabel();
        }

        return $this->getActionLabel().' ('.implode(', ', array_slice($fields, 0, 3))
            .($count > 3 ? ' +'.($count - 3).' more' : '').')';
    }
}
