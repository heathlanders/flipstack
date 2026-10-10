<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\User\Role;
use App\Models\User\User;
use App\Models\User\UserActivityLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class UserManagementController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
    }

    /**
     * Display users index
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $organization = $user->organization;

        $this->authorize('manageUsers', $organization);

        $query = User::with(['roles', 'userTeams.team', 'organization'])
            ->inOrganization($organization->id);

        // Filters
        if ($request->filled('role')) {
            $query->withRole($request->role);
        }

        if ($request->filled('team')) {
            $query->inTeam($request->team);
        }

        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->active();
            } else {
                $query->where('is_active', false);
            }
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('email', 'like', '%'.$request->search.'%')
                    ->orWhere('employee_id', 'like', '%'.$request->search.'%');
            });
        }

        $users = $query->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        // Transform users data
        $users->through(function ($userItem) use ($user) {
            $primaryRole = $userItem->getPrimaryRole();
            $teams = $userItem->activeTeams;

            return [
                'id' => $userItem->id,
                'name' => $userItem->name,
                'email' => $userItem->email,
                'employee_id' => $userItem->employee_id,
                'avatar' => $userItem->profile_photo_url,
                'is_active' => $userItem->is_active,
                'primary_role' => [
                    'slug' => $primaryRole,
                    'display_name' => $this->getRoleDisplayName($primaryRole),
                ],
                'teams' => $teams->map(function ($team) {
                    return [
                        'id' => $team->id,
                        'name' => $team->name,
                        'department' => $team->department,
                        'department_color' => $team->getDepartmentColor(),
                    ];
                }),
                'teams_count' => $teams->count(),
                'last_login' => $userItem->last_login_at?->diffForHumans() ?? 'Never',
                'hire_date' => $userItem->hire_date?->format('M j, Y'),
                'created_at' => $userItem->created_at->diffForHumans(),
                'can_manage' => $user->hasPermission('users.edit') || $user->id === $userItem->id,
                'can_delete' => $user->hasPermission('users.delete') && $user->id !== $userItem->id,
                'is_current_user' => $user->id === $userItem->id,
                'stats' => [
                    'products_created' => $userItem->createdProducts()->count(),
                    'items_processed' => $userItem->processedIntakeItems()->count(),
                    'teams_led' => $userItem->managedTeams()->count(),
                ],
            ];
        });

        // Summary statistics
        $stats = [
            'total_users' => User::inOrganization($organization->id)->count(),
            'active_users' => User::inOrganization($organization->id)->active()->count(),
            'users_online' => User::inOrganization($organization->id)
                ->where('last_login_at', '>=', now()->subMinutes(15))
                ->count(),
            'new_this_month' => User::inOrganization($organization->id)
                ->whereBetween('created_at', [now()->startOfMonth(), now()])
                ->count(),
            'roles_distribution' => $this->getRolesDistribution($organization),
            'departments' => $this->getDepartmentDistribution($organization),
        ];

        return Inertia::render('Users/Index', [
            'users' => $users,
            'stats' => $stats,
            'filters' => $request->only(['role', 'team', 'status', 'search']),
            'available_roles' => Role::active()->get(['id', 'name', 'slug']),
            'available_teams' => Team::forOrganization($organization->id)
                ->active()
                ->get(['id', 'name', 'department']),
            'can_create_user' => $user->hasPermission('users.create'),
            'can_bulk_actions' => $user->hasPermission('users.bulk_actions'),
        ]);
    }

    /**
     * Show user details
     */
    public function show(User $user)
    {
        $this->authorize('viewUser', $user);

        $user->load(['roles', 'userTeams.team', 'managedTeams', 'createdProducts', 'activityLogs' => function ($query) {
            $query->latest()->limit(20);
        }]);

        // User teams with roles
        $teams = $user->userTeams->map(function ($team) use ($user) {
            $role = $user->getTeamRole($team);

            return [
                'id' => $team->id,
                'name' => $team->name,
                'department' => $team->department,
                'department_color' => $team->getDepartmentColor(),
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                ],
                'joined_at' => $user->userTeams()->where('team_id', $team->id)->first()?->pivot?->joined_at?->diffForHumans(),
                'is_active' => $user->isActiveInTeam($team),
                'is_team_lead' => $team->team_lead_id === $user->id,
            ];
        });

        // Recent activity
        $recentActivity = $user->activityLogs->map(function ($log) {
            return [
                'id' => $log->id,
                'action' => $log->action,
                'description' => $log->description,
                'created_at' => $log->created_at->diffForHumans(),
                'metadata' => $log->metadata,
            ];
        });

        // Performance metrics
        $metrics = [
            'products_created' => $user->createdProducts()->count(),
            'items_processed' => $user->processedIntakeItems()->count(),
            'teams_count' => $user->activeTeams()->count(),
            'teams_led' => $user->managedTeams()->count(),
            'login_streak' => $this->calculateLoginStreak($user),
            'avg_session_duration' => '45 minutes', // Calculate from actual data
            'last_activity' => $user->last_login_at?->diffForHumans() ?? 'Never',
        ];

        return Inertia::render('Users/Show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'employee_id' => $user->employee_id,
                'phone' => $user->phone,
                'department' => $user->department,
                'avatar' => $user->profile_photo_url,
                'is_active' => $user->is_active,
                'hire_date' => $user->hire_date?->format('M j, Y'),
                'last_login_at' => $user->last_login_at?->format('M j, Y g:i A'),
                'bio' => $user->bio,
                'timezone' => $user->timezone,
                'preferences' => $user->preferences ?? [],
                'primary_role' => [
                    'slug' => $user->getPrimaryRole(),
                    'display_name' => $this->getRoleDisplayName($user->getPrimaryRole()),
                ],
                'created_at' => $user->created_at->format('M j, Y'),
                'updated_at' => $user->updated_at->diffForHumans(),
            ],
            'teams' => $teams,
            'recent_activity' => $recentActivity,
            'metrics' => $metrics,
            'permissions' => [
                'can_edit' => auth()->user()->hasPermission('users.edit') || auth()->user()->id === $user->id,
                'can_delete' => auth()->user()->hasPermission('users.delete') && auth()->user()->id !== $user->id,
                'can_manage_teams' => auth()->user()->hasPermission('teams.manage'),
                'can_view_activity' => auth()->user()->hasPermission('users.view_activity'),
                'can_impersonate' => auth()->user()->hasPermission('users.impersonate') && auth()->user()->id !== $user->id,
            ],
            'available_teams' => Team::forOrganization($user->organization_id)
                ->active()
                ->whereNotIn('id', $teams->pluck('id'))
                ->get(['id', 'name', 'department']),
            'available_roles' => Role::active()->get(['id', 'name', 'slug']),
        ]);
    }

    /**
     * Show user edit form
     */
    public function edit(User $user)
    {
        $this->authorize('editUser', $user);

        return Inertia::render('Users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'employee_id' => $user->employee_id,
                'phone' => $user->phone,
                'department' => $user->department,
                'hire_date' => $user->hire_date?->format('Y-m-d'),
                'bio' => $user->bio,
                'timezone' => $user->timezone,
                'is_active' => $user->is_active,
                'permissions' => $user->permissions ?? [],
                'current_role_id' => $user->roles()->first()?->id,
            ],
            'available_roles' => Role::active()->get(['id', 'name', 'slug', 'description']),
            'departments' => $this->getDepartmentOptions(),
            'timezones' => $this->getTimezoneOptions(),
            'available_permissions' => $this->getAvailablePermissions(),
            'user_teams' => $user->userTeams->map(function ($team) use ($user) {
                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'role' => $user->getTeamRole($team)?->name,
                    'can_remove' => true,
                ];
            }),
        ]);
    }

    /**
     * Update user
     */
    public function update(Request $request, User $user)
    {
        $this->authorize('editUser', $user);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,'.$user->id],
            'employee_id' => ['nullable', 'string', 'unique:users,employee_id,'.$user->id],
            'phone' => ['nullable', 'string', 'max:20'],
            'department' => ['nullable', 'string'],
            'hire_date' => ['nullable', 'date'],
            'bio' => ['nullable', 'string', 'max:500'],
            'timezone' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'permissions' => ['array'],
            'role_id' => ['nullable', 'exists:roles,id'],
        ]);

        try {
            DB::transaction(function () use ($request, $user) {
                $oldData = [
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->getPrimaryRole(),
                ];

                // Update user data
                $user->update([
                    'name' => $request->name,
                    'email' => $request->email,
                    'employee_id' => $request->employee_id,
                    'phone' => $request->phone,
                    'department' => $request->department,
                    'hire_date' => $request->hire_date ? Carbon::parse($request->hire_date) : null,
                    'bio' => $request->bio,
                    'timezone' => $request->timezone ?? 'UTC',
                    'is_active' => $request->is_active ?? true,
                    'permissions' => $request->permissions ?? [],
                ]);

                // Update primary role if changed
                if ($request->filled('role_id')) {
                    $newRole = Role::find($request->role_id);
                    $currentRole = $user->roles()->first();

                    if (! $currentRole || $currentRole->id !== $newRole->id) {
                        // Remove current role
                        if ($currentRole) {
                            $user->roles()->detach($currentRole->id);
                        }

                        // Add new role
                        $user->roles()->attach($newRole->id, [
                            'organization_id' => $user->organization_id,
                            'team_id' => null,
                        ]);
                    }
                }

                // Log activity
                $changes = array_diff_assoc([
                    'name' => $request->name,
                    'email' => $request->email,
                    'role' => $user->getPrimaryRole(),
                ], $oldData);

                if (! empty($changes)) {
                    $request->user()->logActivity('user_updated', "Updated user profile for {$user->name}", [
                        'user_id' => $user->id,
                        'changes' => $changes,
                    ]);
                }
            });

            return redirect()->route('users.show', $user)
                ->with('success', 'User updated successfully!');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to update user. Please try again.']);
        }
    }

    /**
     * Deactivate user
     */
    public function deactivate(Request $request, User $user)
    {
        $this->authorize('deactivateUser', $user);

        try {
            $user->update(['is_active' => false]);

            // Deactivate from all teams
            $user->userTeams()->updateExistingPivot($user->userTeams->pluck('id'), [
                'is_active' => false,
            ]);

            // Log activity
            $request->user()->logActivity('user_deactivated', "Deactivated user: {$user->name}", [
                'user_id' => $user->id,
            ]);

            return back()->with('success', 'User deactivated successfully.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to deactivate user.']);
        }
    }

    /**
     * Reactivate user
     */
    public function reactivate(Request $request, User $user)
    {
        $this->authorize('reactivateUser', $user);

        try {
            $user->update(['is_active' => true]);

            // Log activity
            $request->user()->logActivity('user_reactivated', "Reactivated user: {$user->name}", [
                'user_id' => $user->id,
            ]);

            return back()->with('success', 'User reactivated successfully.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to reactivate user.']);
        }
    }

    /**
     * Reset user password
     */
    public function resetPassword(Request $request, User $user)
    {
        $this->authorize('resetUserPassword', $user);

        $request->validate([
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        try {
            $user->update([
                'password' => Hash::make($request->password),
            ]);

            // Log activity
            $request->user()->logActivity('password_reset', "Reset password for user: {$user->name}", [
                'user_id' => $user->id,
            ]);

            return back()->with('success', 'Password reset successfully.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to reset password.']);
        }
    }

    /**
     * Add user to team
     */
    public function addToTeam(Request $request, User $user)
    {
        $this->authorize('manageUserTeams', $user);

        $request->validate([
            'team_id' => ['required', 'exists:teams,id'],
            'role_id' => ['required', 'exists:roles,id'],
        ]);

        try {
            $team = Team::findOrFail($request->team_id);
            $role = Role::findOrFail($request->role_id);

            // Verify team belongs to same organization
            if ($team->organization_id !== $user->organization_id) {
                return back()->withErrors(['error' => 'Team does not belong to the same organization.']);
            }

            // Check if user is already a member
            if ($user->isMemberOfTeam($team)) {
                return back()->withErrors(['error' => 'User is already a member of this team.']);
            }

            $team->addUser($user, $role);

            // Log activity
            $request->user()->logActivity('user_added_to_team', "Added {$user->name} to team: {$team->name}", [
                'user_id' => $user->id,
                'team_id' => $team->id,
                'role_id' => $role->id,
            ]);

            return back()->with('success', "Added {$user->name} to {$team->name}!");

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to add user to team.']);
        }
    }

    /**
     * Remove user from team
     */
    public function removeFromTeam(Request $request, User $user, Team $team)
    {
        $this->authorize('manageUserTeams', $user);

        try {
            if (! $user->isMemberOfTeam($team)) {
                return back()->withErrors(['error' => 'User is not a member of this team.']);
            }

            $team->removeUser($user);

            // Log activity
            $request->user()->logActivity('user_removed_from_team', "Removed {$user->name} from team: {$team->name}", [
                'user_id' => $user->id,
                'team_id' => $team->id,
            ]);

            return back()->with('success', "Removed {$user->name} from {$team->name}.");

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to remove user from team.']);
        }
    }

    /**
     * User activity log
     */
    public function activity(User $user)
    {
        $this->authorize('viewUserActivity', $user);

        $activities = UserActivityLog::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return Inertia::render('Users/Activity', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar' => $user->profile_photo_url,
            ],
            'activities' => $activities->through(function ($activity) {
                return [
                    'id' => $activity->id,
                    'action' => $activity->action,
                    'description' => $activity->description,
                    'metadata' => $activity->metadata,
                    'ip_address' => $activity->ip_address,
                    'user_agent' => $activity->user_agent,
                    'created_at' => $activity->created_at->format('M j, Y g:i A'),
                    'time_ago' => $activity->created_at->diffForHumans(),
                ];
            }),
        ]);
    }

    /**
     * Bulk actions on users
     */
    public function bulkAction(Request $request)
    {
        $this->authorize('bulkUserActions', auth()->user()->organization);

        $request->validate([
            'action' => ['required', 'in:activate,deactivate,delete'],
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['exists:users,id'],
        ]);

        try {
            $users = User::whereIn('id', $request->user_ids)
                ->inOrganization(auth()->user()->organization_id)
                ->get();

            $count = 0;

            DB::transaction(function () use ($users, $request, &$count) {
                foreach ($users as $user) {
                    // Don't allow bulk actions on self
                    if ($user->id === auth()->id()) {
                        continue;
                    }

                    switch ($request->action) {
                        case 'activate':
                            $user->update(['is_active' => true]);
                            break;
                        case 'deactivate':
                            $user->update(['is_active' => false]);
                            break;
                        case 'delete':
                            $user->delete(); // Soft delete
                            break;
                    }

                    $count++;
                }

                // Log bulk action
                auth()->user()->logActivity('bulk_user_action', "Performed bulk action: {$request->action} on {$count} users", [
                    'action' => $request->action,
                    'user_count' => $count,
                    'user_ids' => $request->user_ids,
                ]);
            });

            return back()->with('success', "Successfully {$request->action}d {$count} users.");

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to perform bulk action.']);
        }
    }

    /**
     * Helper Methods
     */
    private function getRoleDisplayName($roleSlug)
    {
        return match ($roleSlug) {
            'organization_owner' => 'Organization Owner',
            'admin' => 'Administrator',
            'buyer' => 'Buyer',
            'sourcing_specialist' => 'Sourcing Specialist',
            'lister' => 'Lister',
            'listing_specialist' => 'Listing Specialist',
            'fulfillment_manager' => 'Fulfillment Manager',
            'shipper' => 'Shipper',
            'support_agent' => 'Support Agent',
            default => 'Basic User'
        };
    }

    private function getRolesDistribution($organization)
    {
        return DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('user_roles.organization_id', $organization->id)
            ->select('roles.name', DB::raw('count(*) as count'))
            ->groupBy('roles.id', 'roles.name')
            ->get()
            ->pluck('count', 'name')
            ->toArray();
    }

    private function getDepartmentDistribution($organization)
    {
        return User::inOrganization($organization->id)
            ->whereNotNull('department')
            ->select('department', DB::raw('count(*) as count'))
            ->groupBy('department')
            ->get()
            ->pluck('count', 'department')
            ->toArray();
    }

    private function getDepartmentOptions()
    {
        return [
            ['value' => 'sourcing', 'label' => 'Sourcing & Buying'],
            ['value' => 'listing', 'label' => 'Product Listing'],
            ['value' => 'fulfillment', 'label' => 'Order Fulfillment'],
            ['value' => 'management', 'label' => 'Management'],
            ['value' => 'support', 'label' => 'Customer Support'],
            ['value' => 'general', 'label' => 'General'],
        ];
    }

    private function getTimezoneOptions()
    {
        return [
            ['value' => 'UTC', 'label' => 'UTC'],
            ['value' => 'America/New_York', 'label' => 'Eastern Time'],
            ['value' => 'America/Chicago', 'label' => 'Central Time'],
            ['value' => 'America/Denver', 'label' => 'Mountain Time'],
            ['value' => 'America/Los_Angeles', 'label' => 'Pacific Time'],
            ['value' => 'Europe/London', 'label' => 'London'],
            ['value' => 'Europe/Berlin', 'label' => 'Berlin'],
            ['value' => 'Asia/Tokyo', 'label' => 'Tokyo'],
            ['value' => 'Australia/Sydney', 'label' => 'Sydney'],
        ];
    }

    private function getAvailablePermissions()
    {
        return [
            'users.view' => 'View Users',
            'users.create' => 'Create Users',
            'users.edit' => 'Edit Users',
            'users.delete' => 'Delete Users',
            'teams.view' => 'View Teams',
            'teams.create' => 'Create Teams',
            'teams.edit' => 'Edit Teams',
            'teams.delete' => 'Delete Teams',
            'teams.invite' => 'Invite Team Members',
            'inventory.view' => 'View Inventory',
            'inventory.edit' => 'Edit Inventory',
            'orders.view' => 'View Orders',
            'orders.process' => 'Process Orders',
            'analytics.view' => 'View Analytics',
            'settings.view' => 'View Settings',
            'settings.edit' => 'Edit Settings',
        ];
    }

    private function calculateLoginStreak(User $user)
    {
        // Simplified - calculate consecutive days of login
        // In production, you'd query actual login records
        return $user->last_login_at && $user->last_login_at->isToday() ? 5 : 0;
    }
}
