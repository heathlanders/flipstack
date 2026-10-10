<?php

namespace App\Models\System;

use App\Models\Organization\Organization;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingProgress extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'organization_id',
        'steps_completed',
        'current_step',
        'data',
        'started_at',
        'completed_at',
        'skipped_at',
        'completion_time_minutes',
        'skip_reason',
        'metadata',
    ];

    protected $casts = [
        'steps_completed' => 'array',
        'data' => 'array',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'skipped_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Scopes
     */
    public function scopeCompleted($query)
    {
        return $query->whereNotNull('completed_at');
    }

    public function scopeSkipped($query)
    {
        return $query->whereNotNull('skipped_at');
    }

    public function scopeInProgress($query)
    {
        return $query->whereNull('completed_at')
            ->whereNull('skipped_at');
    }

    public function scopeForOrganization($query, $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeStartedAfter($query, $date)
    {
        return $query->where('started_at', '>=', $date);
    }

    /**
     * Helper Methods
     */
    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function isSkipped(): bool
    {
        return $this->skipped_at !== null;
    }

    public function isInProgress(): bool
    {
        return ! $this->isCompleted() && ! $this->isSkipped();
    }

    public function getProgressPercentage(): int
    {
        $totalSteps = count($this->getAvailableSteps());
        $completedSteps = count($this->steps_completed ?? []);

        if ($totalSteps === 0) {
            return 100;
        }

        return min(100, round(($completedSteps / $totalSteps) * 100));
    }

    public function getAvailableSteps(): array
    {
        return [
            'welcome' => [
                'title' => 'Welcome',
                'description' => 'Introduction to the platform',
                'required' => true,
            ],
            'profile' => [
                'title' => 'Complete Profile',
                'description' => 'Add personal information',
                'required' => true,
            ],
            'teams' => [
                'title' => 'Join Teams',
                'description' => 'Connect with your teams',
                'required' => false,
            ],
            'role_understanding' => [
                'title' => 'Understand Role',
                'description' => 'Learn about your responsibilities',
                'required' => true,
            ],
            'first_action' => [
                'title' => 'First Action',
                'description' => 'Complete your first task',
                'required' => true,
            ],
            'resources' => [
                'title' => 'Explore Resources',
                'description' => 'Discover helpful guides',
                'required' => false,
            ],
        ];
    }

    public function getNextStep(): ?string
    {
        if ($this->isCompleted() || $this->isSkipped()) {
            return null;
        }

        $steps = array_keys($this->getAvailableSteps());
        $completedSteps = $this->steps_completed ?? [];

        foreach ($steps as $step) {
            if (! in_array($step, $completedSteps)) {
                return $step;
            }
        }

        return 'completed';
    }

    public function getPreviousStep(): ?string
    {
        $completedSteps = $this->steps_completed ?? [];

        if (empty($completedSteps)) {
            return null;
        }

        return end($completedSteps);
    }

    public function completeStep(string $step, array $data = []): void
    {
        $stepsCompleted = $this->steps_completed ?? [];

        if (! in_array($step, $stepsCompleted)) {
            $stepsCompleted[] = $step;
        }

        $stepData = $this->data ?? [];
        if (! empty($data)) {
            $stepData[$step] = array_merge($stepData[$step] ?? [], $data);
        }

        $nextStep = $this->getNextStep();
        $isCompleted = $nextStep === 'completed' || $nextStep === null;

        $this->update([
            'steps_completed' => $stepsCompleted,
            'current_step' => $nextStep ?? 'completed',
            'data' => $stepData,
            'completed_at' => $isCompleted ? now() : null,
            'completion_time_minutes' => $isCompleted && $this->started_at
                ? $this->started_at->diffInMinutes(now())
                : null,
        ]);
    }

    public function markCompleted(?string $reason = null): void
    {
        $this->update([
            'current_step' => 'completed',
            'completed_at' => now(),
            'completion_time_minutes' => $this->started_at
                ? $this->started_at->diffInMinutes(now())
                : null,
            'metadata' => array_merge($this->metadata ?? [], [
                'completion_reason' => $reason,
            ]),
        ]);
    }

    public function markSkipped(?string $reason = null): void
    {
        $this->update([
            'current_step' => 'skipped',
            'skipped_at' => now(),
            'skip_reason' => $reason,
            'metadata' => array_merge($this->metadata ?? [], [
                'skip_reason' => $reason,
            ]),
        ]);
    }

    public function restart(): void
    {
        $this->update([
            'steps_completed' => [],
            'current_step' => 'welcome',
            'data' => [],
            'completed_at' => null,
            'skipped_at' => null,
            'completion_time_minutes' => null,
            'skip_reason' => null,
            'started_at' => now(),
        ]);
    }

    public function getStepData(string $step, $default = null)
    {
        $data = $this->data ?? [];

        return $data[$step] ?? $default;
    }

    public function setStepData(string $step, array $data): void
    {
        $currentData = $this->data ?? [];
        $currentData[$step] = array_merge($currentData[$step] ?? [], $data);

        $this->update(['data' => $currentData]);
    }

    public function getTimeToComplete(): ?string
    {
        if (! $this->completion_time_minutes) {
            return null;
        }

        $minutes = $this->completion_time_minutes;

        if ($minutes < 60) {
            return "{$minutes} minutes";
        } elseif ($minutes < 1440) {
            $hours = round($minutes / 60, 1);

            return "{$hours} hours";
        } else {
            $days = round($minutes / 1440, 1);

            return "{$days} days";
        }
    }

    public function getCompletionRate(): float
    {
        $totalSteps = count($this->getAvailableSteps());
        $completedSteps = count($this->steps_completed ?? []);

        if ($totalSteps === 0) {
            return 1.0;
        }

        return round($completedSteps / $totalSteps, 2);
    }

    public function getRemainingSteps(): array
    {
        $allSteps = array_keys($this->getAvailableSteps());
        $completedSteps = $this->steps_completed ?? [];

        return array_diff($allSteps, $completedSteps);
    }

    public function getCompletedStepsWithTimestamps(): array
    {
        $completedSteps = $this->steps_completed ?? [];
        $stepData = $this->data ?? [];
        $result = [];

        foreach ($completedSteps as $step) {
            $result[$step] = [
                'completed_at' => $stepData[$step]['completed_at'] ?? $this->updated_at,
                'data' => $stepData[$step] ?? [],
            ];
        }

        return $result;
    }

    /**
     * Static helper methods
     */
    public static function createForUser(User $user): self
    {
        return static::create([
            'user_id' => $user->id,
            'organization_id' => $user->organization_id,
            'steps_completed' => [],
            'current_step' => 'welcome',
            'data' => [],
            'started_at' => now(),
        ]);
    }

    public static function getOrCreateForUser(User $user): self
    {
        return static::firstOrCreate(
            ['user_id' => $user->id],
            [
                'organization_id' => $user->organization_id,
                'steps_completed' => [],
                'current_step' => 'welcome',
                'data' => [],
                'started_at' => now(),
            ]
        );
    }

    public static function getCompletionStats(int $organizationId, int $days = 30): array
    {
        $startDate = now()->subDays($days);

        $total = static::forOrganization($organizationId)
            ->startedAfter($startDate)
            ->count();

        $completed = static::forOrganization($organizationId)
            ->startedAfter($startDate)
            ->completed()
            ->count();

        $skipped = static::forOrganization($organizationId)
            ->startedAfter($startDate)
            ->skipped()
            ->count();

        $inProgress = $total - $completed - $skipped;

        $avgCompletionTime = static::forOrganization($organizationId)
            ->completed()
            ->whereNotNull('completion_time_minutes')
            ->avg('completion_time_minutes');

        return [
            'total_started' => $total,
            'completed' => $completed,
            'skipped' => $skipped,
            'in_progress' => $inProgress,
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
            'average_completion_time_minutes' => $avgCompletionTime ? round($avgCompletionTime) : null,
            'skip_rate' => $total > 0 ? round(($skipped / $total) * 100, 1) : 0,
        ];
    }

    public static function getStepAnalytics(int $organizationId): array
    {
        $progresses = static::forOrganization($organizationId)->get();
        $stepStats = [];
        $availableSteps = (new static)->getAvailableSteps();

        foreach (array_keys($availableSteps) as $step) {
            $completedCount = $progresses->filter(function ($progress) use ($step) {
                return in_array($step, $progress->steps_completed ?? []);
            })->count();

            $stepStats[$step] = [
                'completed_count' => $completedCount,
                'completion_rate' => $progresses->count() > 0
                    ? round(($completedCount / $progresses->count()) * 100, 1)
                    : 0,
                'title' => $availableSteps[$step]['title'],
                'required' => $availableSteps[$step]['required'],
            ];
        }

        return $stepStats;
    }
}
