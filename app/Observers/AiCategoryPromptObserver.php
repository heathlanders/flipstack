<?php

namespace App\Observers;

use App\Models\AI\AiCategoryPrompt;
use App\Models\AI\AiPromptHistory;
use App\Services\AI\PromptService;
use Illuminate\Support\Facades\Log;

class AiCategoryPromptObserver
{
    protected PromptService $promptService;

    public function __construct(PromptService $promptService)
    {
        $this->promptService = $promptService;
    }

    /**
     * Handle the AiCategoryPrompt "created" event.
     */
    public function created(AiCategoryPrompt $prompt): void
    {
        // History is typically recorded in the controller for more context
        // But we can log here for audit purposes
        try {
            Log::debug('AiCategoryPrompt created', [
                'prompt_id' => $prompt->id,
                'category' => $prompt->category_slug,
                'type' => $prompt->prompt_type,
                'is_system' => $prompt->is_system,
            ]);
        } catch (\Exception $e) {
            // Silently ignore logging failures (e.g., during tests)
        }
    }

    /**
     * Handle the AiCategoryPrompt "updated" event.
     */
    public function updated(AiCategoryPrompt $prompt): void
    {
        $changedAttributes = $prompt->getChanges();
        $originalAttributes = $prompt->getOriginal();

        try {
            Log::debug('AiCategoryPrompt updated', [
                'prompt_id' => $prompt->id,
                'changed_fields' => array_keys($changedAttributes),
            ]);
        } catch (\Exception $e) {
            // Silently ignore logging failures
        }

        // Clear cache when prompt is updated
        try {
            $this->promptService->clearCache(
                $prompt->organization_id,
                $prompt->category_slug
            );

            // Also clear for original category if it changed
            if (isset($originalAttributes['category_slug']) &&
                $originalAttributes['category_slug'] !== $prompt->category_slug) {
                $this->promptService->clearCache(
                    $prompt->organization_id,
                    $originalAttributes['category_slug']
                );
            }
        } catch (\Exception $e) {
            Log::warning('Failed to clear prompt cache', [
                'prompt_id' => $prompt->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Auto-record history if not already recorded by controller
        // Check if history was recently recorded (within last 5 seconds)
        $recentHistory = AiPromptHistory::where('prompt_id', $prompt->id)
            ->where('created_at', '>=', now()->subSeconds(5))
            ->exists();

        if (! $recentHistory && isset($changedAttributes['prompt_content'])) {
            // Record automatic history for content changes
            AiPromptHistory::recordChange(
                $prompt,
                'updated',
                $prompt->updated_by,
                $originalAttributes['prompt_content'] ?? null,
                $prompt->prompt_content,
                array_keys($changedAttributes)
            );
        }
    }

    /**
     * Handle the AiCategoryPrompt "deleted" event.
     */
    public function deleted(AiCategoryPrompt $prompt): void
    {
        try {
            Log::info('AiCategoryPrompt deleted', [
                'prompt_id' => $prompt->id,
                'category' => $prompt->category_slug,
                'type' => $prompt->prompt_type,
            ]);
        } catch (\Exception $e) {
            // Silently ignore logging failures
        }

        // Clear cache when prompt is deleted
        try {
            $this->promptService->clearCache(
                $prompt->organization_id,
                $prompt->category_slug
            );
        } catch (\Exception $e) {
            Log::warning('Failed to clear prompt cache after delete', [
                'prompt_id' => $prompt->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the AiCategoryPrompt "restored" event.
     */
    public function restored(AiCategoryPrompt $prompt): void
    {
        try {
            Log::info('AiCategoryPrompt restored', [
                'prompt_id' => $prompt->id,
                'category' => $prompt->category_slug,
            ]);
        } catch (\Exception $e) {
            // Silently ignore logging failures
        }

        // Record history for restore
        AiPromptHistory::recordChange(
            $prompt,
            'restored',
            auth()->id()
        );

        // Clear cache when prompt is restored
        try {
            $this->promptService->clearCache(
                $prompt->organization_id,
                $prompt->category_slug
            );
        } catch (\Exception $e) {
            Log::warning('Failed to clear prompt cache after restore', [
                'prompt_id' => $prompt->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the AiCategoryPrompt "force deleted" event.
     */
    public function forceDeleted(AiCategoryPrompt $prompt): void
    {
        try {
            Log::info('AiCategoryPrompt force deleted', [
                'prompt_id' => $prompt->id,
                'category' => $prompt->category_slug,
            ]);
        } catch (\Exception $e) {
            // Silently ignore logging failures
        }

        // History will be cascade deleted with the prompt
        // Just clear the cache
        try {
            $this->promptService->clearCache(
                $prompt->organization_id,
                $prompt->category_slug
            );
        } catch (\Exception $e) {
            Log::warning('Failed to clear prompt cache after force delete', [
                'prompt_id' => $prompt->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
