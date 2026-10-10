<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Organization\Organization;
use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class RoleManagementController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
    }

    /**
     * Display roles index
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $organization = $user->organization;

        $this->authorize('viewRoles', $organization);

        $query = Role::with(['users' => function ($q) use ($organization) {
            $q->where('users.organization_id', $organization->id);
        }]);

        // Filter for organization-specific or global roles
        if ($request->filled('scope')) {
            if ($request->scope === 'organization') {
                $query->where('organization_id', $organization->id);
            } else {
                $query->whereNull('organization_id');
            }
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('description', 'like', '%'.$request->search.'%');
            });
        }

        $roles = $query->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        // Transform roles data
        $roles->through(function ($role) use ($organization) {
            $userCount = $role->users()
                ->where('users.organization_id', $organization->id)
                ->count();

            return [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'is_active' => $role->is_active,
                'is_default' => $role->is_default,
                'is_system' => $role->organization_id === null,
                'is_custom' => $role->organization_id === $organization->id,
                'permissions' => $role->permissions ?? [],
                'user_count' => $userCount,
                'created_at' => $role->created_at->diffForHumans(),
                'can_edit' => $role->organization_id === $organization->id || auth()->user()->hasPermission('roles.edit_system'),
                'can_delete' => $role->organization_id === $organization->id && ! $role->is_default && $userCount === 0,
            ];
        });

        // Summary statistics
        $stats = [
            'total_roles' => Role::count(),
            'custom_roles' => Role::where('organization_id', $organization->id)->count(),
            'system_roles' => Role::whereNull('organization_id')->count(),
            'roles_in_use' => Role::whereHas('users', function ($q) use ($organization) {
                $q->where('users.organization_id', $organization->id);  // ← SPECIFIC TABLE
            })->count(),
        ];

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'stats' => $stats,
            'filters' => $request->only(['scope', 'search']),
            'available_permissions' => $this->getAvailablePermissions(),
            'can_create_role' => auth()->user()->hasPermission('roles.create'),
            'can_manage_permissions' => auth()->user()->hasPermission('roles.manage_permissions'),
        ]);
    }

    /**
     * Show role creation form
     */
    public function create()
    {
        $this->authorize('createRole', auth()->user()->organization);

        return Inertia::render('Roles/Create', [
            'available_permissions' => $this->getAvailablePermissions(),
            'permission_categories' => $this->getPermissionCategories(),
            'suggested_roles' => $this->getSuggestedRoleTemplates(),
        ]);
    }

    /**
     * Store new role
     */
    public function store(Request $request)
    {
        $this->authorize('createRole', $request->user()->organization);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'in:'.implode(',', array_keys($this->getAvailablePermissions()))],
            'is_active' => ['boolean'],
            'template' => ['nullable', 'string'], // For role templates
        ]);

        try {
            $user = $request->user();
            $organization = $user->organization;

            $role = Role::create([
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'description' => $request->description,
                'permissions' => $request->permissions ?? [],
                'is_active' => $request->is_active ?? true,
                'is_default' => false,
                'organization_id' => $organization->id,
            ]);

            // Log activity
            $user->logActivity('role_created', "Created role: {$role->name}", [
                'role_id' => $role->id,
                'permissions_count' => count($request->permissions ?? []),
            ]);

            return redirect()->route('roles.index')
                ->with('success', 'Role created successfully!');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to create role. Please try again.']);
        }
    }

    /**
     * Show role details
     */
    public function show(Role $role)
    {
        $this->authorize('viewRole', $role);

        $organization = auth()->user()->organization;

        // Get users with this role in the organization
        $users = User::whereHas('roles', function ($q) use ($role) {
            $q->where('role_id', $role->id);
        })
            ->inOrganization($organization->id)
            ->with(['userTeams.team'])
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->profile_photo_url,
                    'is_active' => $user->is_active,
                    'teams' => $user->userTeams->map(function ($team) {
                        return [
                            'id' => $team->id,
                            'name' => $team->name,
                            'department' => $team->department,
                        ];
                    }),
                    'last_login' => $user->last_login_at?->diffForHumans() ?? 'Never',
                ];
            });

        // Permission analysis
        $permissionAnalysis = $this->analyzePermissions($role->permissions ?? []);

        return Inertia::render('Roles/Show', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'is_active' => $role->is_active,
                'is_default' => $role->is_default,
                'is_system' => $role->organization_id === null,
                'is_custom' => $role->organization_id === $organization->id,
                'permissions' => $role->permissions ?? [],
                'created_at' => $role->created_at->format('M j, Y'),
                'updated_at' => $role->updated_at->diffForHumans(),
            ],
            'users' => $users,
            'permission_analysis' => $permissionAnalysis,
            'available_permissions' => $this->getAvailablePermissions(),
            'permission_categories' => $this->getPermissionCategories(),
            'can_edit' => auth()->user()->can('update', $role),
            'can_delete' => auth()->user()->can('delete', $role),
            'can_assign_users' => auth()->user()->hasPermission('roles.assign_users'),
        ]);
    }

    /**
     * Show role edit form
     */
    public function edit(Role $role)
    {
        $this->authorize('update', $role);

        return Inertia::render('Roles/Edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'permissions' => $role->permissions ?? [],
                'is_active' => $role->is_active,
                'is_system' => $role->organization_id === null,
            ],
            'available_permissions' => $this->getAvailablePermissions(),
            'permission_categories' => $this->getPermissionCategories(),
            'users_with_role' => User::whereHas('roles', function ($q) use ($role) {
                $q->where('role_id', $role->id);
            })->inOrganization(auth()->user()->organization_id)->count(),
        ]);
    }

    /**
     * Update role
     */
    public function update(Request $request, Role $role)
    {
        $this->authorize('update', $role);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'in:'.implode(',', array_keys($this->getAvailablePermissions()))],
            'is_active' => ['boolean'],
        ]);

        try {
            $oldPermissions = $role->permissions ?? [];

            $role->update([
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'description' => $request->description,
                'permissions' => $request->permissions ?? [],
                'is_active' => $request->is_active ?? true,
            ]);

            // Log activity
            $permissionsChanged = array_diff($oldPermissions, $request->permissions ?? []) ||
                                array_diff($request->permissions ?? [], $oldPermissions);

            $request->user()->logActivity('role_updated', "Updated role: {$role->name}", [
                'role_id' => $role->id,
                'permissions_changed' => $permissionsChanged,
                'old_permissions_count' => count($oldPermissions),
                'new_permissions_count' => count($request->permissions ?? []),
            ]);

            return redirect()->route('roles.show', $role)
                ->with('success', 'Role updated successfully!');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to update role. Please try again.']);
        }
    }

    /**
     * Delete role
     */
    public function destroy(Request $request, Role $role)
    {
        $this->authorize('delete', $role);

        try {
            // Check if role is in use
            $userCount = User::whereHas('roles', function ($q) use ($role) {
                $q->where('role_id', $role->id);
            })->count();

            if ($userCount > 0) {
                return back()->withErrors(['error' => 'Cannot delete role that is assigned to users.']);
            }

            // Check if it's a default role
            if ($role->is_default) {
                return back()->withErrors(['error' => 'Cannot delete default role.']);
            }

            $roleName = $role->name;
            $role->delete();

            // Log activity
            $request->user()->logActivity('role_deleted', "Deleted role: {$roleName}", [
                'role_id' => $role->id,
            ]);

            return redirect()->route('roles.index')
                ->with('success', 'Role deleted successfully.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to delete role.']);
        }
    }

    /**
     * Show role permissions management
     */
    public function permissions(Role $role)
    {
        $this->authorize('managePermissions', $role);

        return Inertia::render('Roles/Permissions', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions ?? [],
            ],
            'available_permissions' => $this->getAvailablePermissions(),
            'permission_categories' => $this->getPermissionCategories(),
            'permission_dependencies' => $this->getPermissionDependencies(),
        ]);
    }

    /**
     * Update role permissions
     */
    public function updatePermissions(Request $request, Role $role)
    {
        $this->authorize('managePermissions', $role);

        $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', 'in:'.implode(',', array_keys($this->getAvailablePermissions()))],
        ]);

        try {
            $oldPermissions = $role->permissions ?? [];
            $newPermissions = $request->permissions ?? [];

            // Resolve permission dependencies
            $resolvedPermissions = $this->resolvePermissionDependencies($newPermissions);

            $role->update([
                'permissions' => $resolvedPermissions,
            ]);

            // Log activity
            $added = array_diff($resolvedPermissions, $oldPermissions);
            $removed = array_diff($oldPermissions, $resolvedPermissions);

            $request->user()->logActivity('role_permissions_updated', "Updated permissions for role: {$role->name}", [
                'role_id' => $role->id,
                'added_permissions' => $added,
                'removed_permissions' => $removed,
            ]);

            return back()->with('success', 'Role permissions updated successfully!');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to update permissions.']);
        }
    }

    /**
     * Helper Methods
     */
    private function getAvailablePermissions()
    {
        return [
            // User Management
            'users.view' => 'View Users',
            'users.create' => 'Create Users',
            'users.edit' => 'Edit Users',
            'users.delete' => 'Delete Users',
            'users.manage_status' => 'Manage User Status',
            'users.reset_password' => 'Reset User Passwords',
            'users.view_activity' => 'View User Activity',
            'users.impersonate' => 'Impersonate Users',
            'users.bulk_actions' => 'Bulk User Actions',

            // Team Management
            'teams.view' => 'View Teams',
            'teams.create' => 'Create Teams',
            'teams.edit' => 'Edit Teams',
            'teams.delete' => 'Delete Teams',
            'teams.manage_members' => 'Manage Team Members',
            'teams.invite' => 'Invite Team Members',

            // Role Management
            'roles.view' => 'View Roles',
            'roles.create' => 'Create Roles',
            'roles.edit' => 'Edit Roles',
            'roles.delete' => 'Delete Roles',
            'roles.manage_permissions' => 'Manage Role Permissions',
            'roles.assign_users' => 'Assign Roles to Users',

            // Organization Management
            'organization.view' => 'View Organization',
            'organization.edit' => 'Edit Organization',
            'organization.manage' => 'Manage Organization',

            // Inventory Management
            'inventory.view' => 'View Inventory',
            'inventory.create' => 'Create Inventory Items',
            'inventory.edit' => 'Edit Inventory',
            'inventory.delete' => 'Delete Inventory',
            'inventory.manage_stock' => 'Manage Stock Levels',

            // Product Management
            'products.view' => 'View Products',
            'products.create' => 'Create Products',
            'products.edit' => 'Edit Products',
            'products.delete' => 'Delete Products',
            'products.import' => 'Import Products',
            'products.export' => 'Export Products',

            // Intake Queue Management
            'intake.view' => 'View Intake Queue',
            'intake.process' => 'Process Intake Items',
            'intake.approve' => 'Approve Intake Items',
            'intake.reject' => 'Reject Intake Items',

            // Analytics & Reporting
            'analytics.view' => 'View Analytics',
            'analytics.export' => 'Export Analytics',
            'reports.view' => 'View Reports',
            'reports.create' => 'Create Reports',
            'reports.export' => 'Export Reports',

            // Settings & Configuration
            'settings.view' => 'View Settings',
            'settings.edit' => 'Edit Settings',
            'integrations.view' => 'View Integrations',
            'integrations.manage' => 'Manage Integrations',

            // Audit & Compliance
            'audit_log.view' => 'View Audit Log',
            'audit_log.export' => 'Export Audit Log',

            // Data Management
            'export.data' => 'Export Data',
            'import.data' => 'Import Data',
            'backup.manage' => 'Manage Backups',

            // Billing & Subscriptions
            'billing.view' => 'View Billing',
            'billing.manage' => 'Manage Billing',

            // Administrative
            'admin.access' => 'Administrative Access',
        ];
    }

    private function getPermissionCategories()
    {
        return [
            'User Management' => ['users.view', 'users.create', 'users.edit', 'users.delete', 'users.manage_status', 'users.reset_password', 'users.view_activity', 'users.impersonate', 'users.bulk_actions'],
            'Team Management' => ['teams.view', 'teams.create', 'teams.edit', 'teams.delete', 'teams.manage_members', 'teams.invite'],
            'Role Management' => ['roles.view', 'roles.create', 'roles.edit', 'roles.delete', 'roles.manage_permissions', 'roles.assign_users'],
            'Organization' => ['organization.view', 'organization.edit', 'organization.manage'],
            'Inventory' => ['inventory.view', 'inventory.create', 'inventory.edit', 'inventory.delete', 'inventory.manage_stock'],
            'Products' => ['products.view', 'products.create', 'products.edit', 'products.delete', 'products.import', 'products.export'],
            'Intake Queue' => ['intake.view', 'intake.process', 'intake.approve', 'intake.reject'],
            'Analytics' => ['analytics.view', 'analytics.export', 'reports.view', 'reports.create', 'reports.export'],
            'Settings' => ['settings.view', 'settings.edit', 'integrations.view', 'integrations.manage'],
            'Data' => ['export.data', 'import.data', 'backup.manage'],
            'Compliance' => ['audit_log.view', 'audit_log.export'],
            'Billing' => ['billing.view', 'billing.manage'],
            'Administrative' => ['admin.access'],
        ];
    }

    private function getPermissionDependencies()
    {
        return [
            'users.edit' => ['users.view'],
            'users.delete' => ['users.view'],
            'users.manage_status' => ['users.view'],
            'users.reset_password' => ['users.view'],
            'users.view_activity' => ['users.view'],
            'users.impersonate' => ['users.view'],
            'teams.edit' => ['teams.view'],
            'teams.delete' => ['teams.view'],
            'teams.manage_members' => ['teams.view'],
            'teams.invite' => ['teams.view'],
            'roles.edit' => ['roles.view'],
            'roles.delete' => ['roles.view'],
            'roles.manage_permissions' => ['roles.view'],
            'organization.edit' => ['organization.view'],
            'organization.manage' => ['organization.view'],
            'inventory.edit' => ['inventory.view'],
            'inventory.delete' => ['inventory.view'],
            'products.edit' => ['products.view'],
            'products.delete' => ['products.view'],
            'analytics.export' => ['analytics.view'],
            'reports.export' => ['reports.view'],
        ];
    }

    private function resolvePermissionDependencies($permissions)
    {
        $dependencies = $this->getPermissionDependencies();
        $resolvedPermissions = $permissions;

        foreach ($permissions as $permission) {
            if (isset($dependencies[$permission])) {
                foreach ($dependencies[$permission] as $dependency) {
                    if (! in_array($dependency, $resolvedPermissions)) {
                        $resolvedPermissions[] = $dependency;
                    }
                }
            }
        }

        return array_unique($resolvedPermissions);
    }

    private function analyzePermissions($permissions)
    {
        $categories = $this->getPermissionCategories();
        $analysis = [];

        foreach ($categories as $category => $categoryPermissions) {
            $hasPermissions = array_intersect($permissions, $categoryPermissions);
            $analysis[$category] = [
                'total' => count($categoryPermissions),
                'granted' => count($hasPermissions),
                'percentage' => count($categoryPermissions) > 0 ? round((count($hasPermissions) / count($categoryPermissions)) * 100) : 0,
                'permissions' => $hasPermissions,
            ];
        }

        return $analysis;
    }

    private function getSuggestedRoleTemplates()
    {
        return [
            [
                'name' => 'Team Lead',
                'description' => 'Can manage team members and view team analytics',
                'permissions' => ['teams.view', 'teams.manage_members', 'teams.invite', 'users.view', 'analytics.view'],
            ],
            [
                'name' => 'Inventory Manager',
                'description' => 'Full access to inventory and product management',
                'permissions' => ['inventory.view', 'inventory.create', 'inventory.edit', 'inventory.manage_stock', 'products.view', 'products.create', 'products.edit', 'analytics.view'],
            ],
            [
                'name' => 'Operations Supervisor',
                'description' => 'Oversight of intake queue and operational processes',
                'permissions' => ['intake.view', 'intake.process', 'intake.approve', 'intake.reject', 'inventory.view', 'products.view', 'analytics.view', 'reports.view'],
            ],
            [
                'name' => 'Analyst',
                'description' => 'View-only access with analytics and reporting capabilities',
                'permissions' => ['users.view', 'teams.view', 'inventory.view', 'products.view', 'analytics.view', 'analytics.export', 'reports.view', 'reports.export'],
            ],
        ];
    }
}
