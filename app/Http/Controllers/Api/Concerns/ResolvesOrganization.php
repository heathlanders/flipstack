<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\User\User;

/**
 * Resolves the organization id for the authenticated mobile/API user.
 *
 * Shared by every Api controller so tenant resolution is consistent and safe.
 *
 * Primary source is users.organization_id. Jetstream users whose org is not
 * denormalized onto the user row fall back to their current team's org
 * (teams.organization_id -- NOT the team's own primary key).
 *
 * Returns null when no organization can be resolved. Callers MUST handle null
 * (return empty result / 422) and must NEVER default to a hard-coded org id --
 * defaulting to org 1 leaks another tenant's data to the user.
 */
trait ResolvesOrganization
{
    protected function resolveOrganizationId(?User $user): ?int
    {
        return $user?->organization_id ?? $user?->currentTeam?->organization_id;
    }
}
