<?php

namespace App\Policies\AI;

use App\Models\AI\AiCategoryPrompt;
use App\Models\User\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AiCategoryPromptPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any prompts.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('ai_prompts.view');
    }

    /**
     * Determine whether the user can view the prompt.
     */
    public function view(User $user, AiCategoryPrompt $prompt): bool
    {
        if (! $user->hasPermission('ai_prompts.view')) {
            return false;
        }

        // System prompts are viewable by anyone with view permission
        if ($prompt->is_system) {
            return true;
        }

        // Organization prompts require matching organization
        return $prompt->organization_id === null ||
               $prompt->organization_id === $user->organization_id;
    }

    /**
     * Determine whether the user can create prompts.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('ai_prompts.create');
    }

    /**
     * Determine whether the user can update the prompt.
     */
    public function update(User $user, AiCategoryPrompt $prompt): bool
    {
        // System prompts cannot be updated
        if ($prompt->is_system) {
            return false;
        }

        if (! $user->hasPermission('ai_prompts.edit')) {
            return false;
        }

        // Organization prompts require matching organization
        return $prompt->organization_id === $user->organization_id;
    }

    /**
     * Determine whether the user can delete the prompt.
     */
    public function delete(User $user, AiCategoryPrompt $prompt): bool
    {
        // System prompts cannot be deleted
        if ($prompt->is_system) {
            return false;
        }

        if (! $user->hasPermission('ai_prompts.delete')) {
            return false;
        }

        // Organization prompts require matching organization
        return $prompt->organization_id === $user->organization_id;
    }

    /**
     * Determine whether the user can duplicate the prompt.
     */
    public function duplicate(User $user, AiCategoryPrompt $prompt): bool
    {
        // Can duplicate any prompt if user has create permission
        return $user->hasPermission('ai_prompts.create');
    }

    /**
     * Determine whether the user can restore the prompt.
     */
    public function restore(User $user, AiCategoryPrompt $prompt): bool
    {
        // System prompts cannot be restored (shouldn't be soft deleted anyway)
        if ($prompt->is_system) {
            return false;
        }

        if (! $user->hasPermission('ai_prompts.edit')) {
            return false;
        }

        return $prompt->organization_id === $user->organization_id;
    }

    /**
     * Determine whether the user can permanently delete the prompt.
     */
    public function forceDelete(User $user, AiCategoryPrompt $prompt): bool
    {
        // System prompts cannot be force deleted
        if ($prompt->is_system) {
            return false;
        }

        // Only users with delete permission on their own org prompts
        if (! $user->hasPermission('ai_prompts.delete')) {
            return false;
        }

        return $prompt->organization_id === $user->organization_id;
    }

    /**
     * Determine whether the user can test the prompt with API.
     */
    public function test(User $user, AiCategoryPrompt $prompt): bool
    {
        return $this->view($user, $prompt);
    }

    /**
     * Determine whether the user can export prompts.
     */
    public function export(User $user): bool
    {
        return $user->hasPermission('ai_prompts.view');
    }

    /**
     * Determine whether the user can import prompts.
     */
    public function import(User $user): bool
    {
        return $user->hasPermission('ai_prompts.create');
    }

    /**
     * Determine whether the user can bulk update prompts.
     */
    public function bulkUpdate(User $user): bool
    {
        return $user->hasPermission('ai_prompts.edit');
    }
}
