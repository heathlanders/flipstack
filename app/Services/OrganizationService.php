<?php

namespace App\Services;

use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrganizationService
{
    public function __construct(
        protected OrganizationDefaultDataService $defaultDataService
    ) {}

    public function createOrganization(array $data, User $owner): Organization
    {
        return DB::transaction(function () use ($data, $owner) {
            // Create organization
            $organization = Organization::create([
                'name' => $data['name'],
                'slug' => $data['slug'] ?? Str::slug($data['name']),
                'subdomain' => $data['subdomain'] ?? Str::slug($data['name']),
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'description' => $data['description'] ?? null,
                'settings' => array_merge(
                    (new Organization)->getDefaultSettings(),
                    $data['settings'] ?? []
                ),
                'branding' => $data['branding'] ?? [],
                'timezone' => $data['timezone'] ?? 'UTC',
                'currency' => $data['currency'] ?? 'USD',
                'status' => $data['status'] ?? 'trial',
                'trial_ends_at' => $data['trial_ends_at'] ?? now()->addDays(14),
            ]);

            // Update owner's organization
            $owner->update(['organization_id' => $organization->id]);

            // Create personal team for owner
            $personalTeam = Team::create([
                'name' => $owner->name."'s Team",
                'organization_id' => $organization->id,
                'user_id' => $owner->id,
                'personal_team' => true,
            ]);

            // Update owner's current team
            $owner->update(['current_team_id' => $personalTeam->id]);

            // Assign organization owner role
            $ownerRole = Role::where('slug', 'organization_owner')->first();
            $owner->assignRole($ownerRole, $organization->id);

            // Seed default organization data (supplier tiers, categories, brands, templates)
            $this->defaultDataService->seedDefaultData($organization);

            return $organization;
        });
    }

    public function updateOrganization(Organization $organization, array $data): Organization
    {
        $organization->update($data);

        return $organization->fresh();
    }

    public function suspendOrganization(Organization $organization, ?string $reason = null): bool
    {
        return $organization->update([
            'status' => 'suspended',
            'suspended_at' => now(),
        ]);
    }

    public function activateOrganization(Organization $organization): bool
    {
        return $organization->update([
            'status' => 'active',
            'suspended_at' => null,
        ]);
    }

    public function deleteOrganization(Organization $organization): bool
    {
        return DB::transaction(function () use ($organization) {
            // Soft delete all related data
            $organization->users()->delete();
            $organization->teams()->delete();
            $organization->invitations()->delete();

            // Delete the organization
            return $organization->delete();
        });
    }

    public function getOrganizationStats(Organization $organization): array
    {
        return [
            'users_count' => $organization->users()->active()->count(),
            'teams_count' => $organization->teams()->where('personal_team', false)->count(),
            'total_logins_today' => $organization->users()
                ->whereDate('last_login_at', today())
                ->count(),
            'active_sessions' => $organization->users()
                ->whereHas('sessions', function ($query) {
                    $query->where('is_current', true);
                })
                ->count(),
        ];
    }
}
