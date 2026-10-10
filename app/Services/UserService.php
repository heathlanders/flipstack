<?php

namespace App\Services;

use App\Models\Catalog\Category;
use App\Models\Organization\OrganizationInvitation;
use App\Models\Organization\Team;
use App\Models\System\AuditLog;
use App\Models\User\Role;
use App\Models\User\User;
use App\Notifications\UserInvitationNotification;
use App\Notifications\WelcomeToOrganizationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

class UserService
{
    public function createUser(array $data, ?int $organizationId = null): User
    {
        return DB::transaction(function () use ($data, $organizationId) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'organization_id' => $organizationId,
                'employee_id' => $data['employee_id'] ?? null,
                'phone' => $data['phone'] ?? null,
                'department' => $data['department'] ?? null,
                'job_title' => $data['job_title'] ?? null,
                'hire_date' => $data['hire_date'] ?? now(),
                'preferences' => $data['preferences'] ?? [],
                'notification_settings' => $data['notification_settings'] ?? [],
                'is_active' => $data['is_active'] ?? true,
                'password_expires_at' => $data['password_expires_at'] ?? now()->addDays(90),
            ]);

            // Create personal team
            if ($organizationId) {
                $personalTeam = Team::create([
                    'name' => $user->name."'s Team",
                    'organization_id' => $organizationId,
                    'user_id' => $user->id,
                    'personal_team' => true,
                ]);

                $user->update(['current_team_id' => $personalTeam->id]);
            }

            // Assign default role
            if (isset($data['role_id'])) {
                $role = Role::find($data['role_id']);
                if ($role) {
                    $user->assignRole($role, $organizationId);
                }
            }

            $this->logUserActivity($user, 'user_created', null, $user->toArray());

            return $user;
        });
    }

    public function inviteUser(array $data, User $invitedBy): OrganizationInvitation
    {
        $invitation = DB::transaction(function () use ($data, $invitedBy) {
            $invitation = OrganizationInvitation::create([
                'organization_id' => $invitedBy->organization_id,
                'invited_by_user_id' => $invitedBy->id,
                'email' => $data['email'],
                'roles' => $data['roles'] ?? [],
                'expires_at' => now()->addDays(7),
            ]);

            $this->logUserActivity($invitedBy, 'user_invited', null, [
                'invited_email' => $data['email'],
                'roles' => $data['roles'] ?? [],
            ]);

            return $invitation;
        });

        // Send invitation email outside transaction so mail/queue failures
        // don't roll back the invitation record
        Notification::route('mail', $data['email'])
            ->notify(new UserInvitationNotification($invitation));

        return $invitation;
    }

    public function acceptInvitation(OrganizationInvitation $invitation, array $userData): User
    {
        if ($invitation->isExpired()) {
            throw new \Exception('Invitation has expired');
        }

        if ($invitation->isAccepted()) {
            throw new \Exception('Invitation has already been accepted');
        }

        $user = DB::transaction(function () use ($invitation, $userData) {
            // Create user
            $user = $this->createUser($userData, $invitation->organization_id);

            // Assign invited roles
            foreach ($invitation->roles as $roleId) {
                $role = Role::find($roleId);
                if ($role) {
                    $user->assignRole($role, $invitation->organization_id);
                }
            }

            // Seed default org data if not present
            $orgHasData = Category::where('organization_id', $invitation->organization_id)->exists();
            if (! $orgHasData) {
                $defaultDataService = new OrganizationDefaultDataService;
                $defaultDataService->seedDefaultData($invitation->organization);
            }

            // Mark invitation as accepted
            $invitation->accept();

            $this->logUserActivity($user, 'invitation_accepted', null, [
                'invitation_id' => $invitation->id,
                'invited_by' => $invitation->invitedBy?->name,
            ]);

            return $user;
        });

        // Send welcome email outside transaction so mail/queue failures
        // don't roll back the user creation
        $user->notify(new WelcomeToOrganizationNotification(
            $user,
            $invitation->organization,
            $invitation->roles ?? []
        ));

        return $user;
    }

    public function acceptInvitationAsOrgOwner(OrganizationInvitation $invitation, array $userData, array $orgData): User
    {
        if ($invitation->isExpired()) {
            throw new \Exception('Invitation has expired');
        }

        if ($invitation->isAccepted()) {
            throw new \Exception('Invitation has already been accepted');
        }

        $user = DB::transaction(function () use ($invitation, $userData, $orgData) {
            // Create user without an org initially
            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => Hash::make($userData['password']),
                'is_active' => true,
                'hire_date' => now(),
                'password_expires_at' => now()->addDays(90),
            ]);

            // Create the new organization (handles team, role, and default data seeding)
            $orgService = app(OrganizationService::class);
            $orgService->createOrganization($orgData, $user);

            // Mark invitation as accepted
            $invitation->accept();

            $this->logUserActivity($user, 'invitation_accepted_as_org_owner', null, [
                'invitation_id' => $invitation->id,
                'invited_by' => $invitation->invitedBy?->name,
                'organization_name' => $orgData['name'],
            ]);

            return $user;
        });

        // Send welcome notification with the new org context
        $user = $user->fresh();
        $user->notify(new WelcomeToOrganizationNotification(
            $user,
            $user->organization,
            $invitation->roles ?? []
        ));

        return $user;
    }

    public function updateUser(User $user, array $data): User
    {
        $oldData = $user->toArray();

        $user->update($data);

        $this->logUserActivity($user, 'user_updated', $oldData, $user->fresh()->toArray());

        return $user->fresh();
    }

    public function deactivateUser(User $user, ?string $reason = null): bool
    {
        $result = $user->update(['is_active' => false]);

        if ($result) {
            $this->logUserActivity($user, 'user_deactivated', null, [
                'reason' => $reason,
                'deactivated_by' => auth()->user()?->name,
            ]);
        }

        return $result;
    }

    public function activateUser(User $user): bool
    {
        $result = $user->update(['is_active' => true]);

        if ($result) {
            $this->logUserActivity($user, 'user_activated', null, [
                'activated_by' => auth()->user()?->name,
            ]);
        }

        return $result;
    }

    public function deleteUser(User $user): bool
    {
        return DB::transaction(function () use ($user) {
            $this->logUserActivity($user, 'user_deleted', $user->toArray(), null);

            // Remove from teams
            $user->teams()->detach();

            // Remove roles
            $user->roles()->detach();

            // Soft delete user
            return $user->delete();
        });
    }

    public function assignRole(User $user, Role $role, ?int $organizationId = null, ?int $teamId = null): void
    {
        $user->assignRole($role, $organizationId, $teamId);

        $this->logUserActivity($user, 'role_assigned', null, [
            'role' => $role->name,
            'organization_id' => $organizationId,
            'team_id' => $teamId,
            'assigned_by' => auth()->user()?->name,
        ]);
    }

    public function removeRole(User $user, Role $role, ?int $organizationId = null): void
    {
        $user->removeRole($role, $organizationId);

        $this->logUserActivity($user, 'role_removed', null, [
            'role' => $role->name,
            'organization_id' => $organizationId,
            'removed_by' => auth()->user()?->name,
        ]);
    }

    public function changePassword(User $user, string $newPassword): bool
    {
        $result = $user->update([
            'password' => Hash::make($newPassword),
            'password_expires_at' => now()->addDays(90),
        ]);

        if ($result) {
            $this->logUserActivity($user, 'password_changed', null, [
                'changed_by' => auth()->user()?->name ?? 'self',
            ]);
        }

        return $result;
    }

    public function enableMfa(User $user, string $secret, array $recoveryCodes): bool
    {
        $result = $user->update([
            'mfa_enabled' => true,
            'mfa_secret' => encrypt($secret),
            'mfa_recovery_codes' => encrypt(json_encode($recoveryCodes)),
        ]);

        if ($result) {
            $this->logUserActivity($user, 'mfa_enabled', null, [
                'enabled_by' => auth()->user()?->name ?? 'self',
            ]);
        }

        return $result;
    }

    public function disableMfa(User $user): bool
    {
        $result = $user->update([
            'mfa_enabled' => false,
            'mfa_secret' => null,
            'mfa_recovery_codes' => null,
        ]);

        if ($result) {
            $this->logUserActivity($user, 'mfa_disabled', null, [
                'disabled_by' => auth()->user()?->name ?? 'self',
            ]);
        }

        return $result;
    }

    public function getUserStats(User $user): array
    {
        return [
            'total_logins' => $user->sessions()->count(),
            'last_login' => $user->last_login_at,
            'active_sessions' => $user->sessions()->where('is_current', true)->count(),
            'teams_count' => $user->teams()->count(),
            'roles_count' => $user->roles()->count(),
            'mfa_enabled' => $user->isMfaEnabled(),
            'password_expires_at' => $user->password_expires_at,
        ];
    }

    private function logUserActivity(User $user, string $event, ?array $oldValues, ?array $newValues): void
    {
        AuditLog::create([
            'organization_id' => $user->organization_id,
            'user_id' => auth()->user()?->id ?? $user->id,
            'event' => $event,
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
