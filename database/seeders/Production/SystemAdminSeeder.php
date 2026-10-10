<?php

namespace Database\Seeders\Production;

use App\Models\Organization\Organization;
use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SystemAdminSeeder extends Seeder
{
    /**
     * Create the initial system admin user.
     *
     * This user is assigned to the first organization (or creates one if none exists)
     * and is assigned the super_admin role for full system access.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@example.com');
        $name = env('ADMIN_NAME', 'System Admin');

        // Get or create the default organization
        $organization = Organization::first();
        if (! $organization) {
            $organization = Organization::create([
                'name' => 'Default Organization',
                'slug' => 'default',
                'email' => $email,
            ]);
            $this->command->info("Created default organization: {$organization->name}");
        }

        // Check if admin already exists (idempotent)
        $existingAdmin = User::where('email', $email)->first();

        if ($existingAdmin) {
            $this->command->info("System admin already exists: {$email}");

            return;
        }

        // Create the system admin user
        $admin = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('changeme123'),
            'password_change_required' => true,
            'organization_id' => $organization->id,
            'is_active' => true,
            'email_verified_at' => now(),
            'preferences' => [],
            'permissions' => [],
            'notification_settings' => [],
        ]);

        // Set admin as organization owner if not already set
        if (! $organization->owner_id) {
            $organization->update(['owner_id' => $admin->id]);
        }

        // Assign super_admin role
        $superAdminRole = Role::where('slug', 'super_admin')
            ->whereNull('organization_id')
            ->first();

        if ($superAdminRole) {
            DB::table('user_roles')->insert([
                'user_id' => $admin->id,
                'role_id' => $superAdminRole->id,
                'organization_id' => $organization->id,
                'team_id' => null,
                'expires_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->command->info("Created system admin: {$email} with super_admin role in organization: {$organization->name}");
        } else {
            $this->command->warn("Created system admin: {$email} (super_admin role not found - run SystemRoleSeeder first)");
        }

        $this->command->warn('Default password: changeme123');
        $this->command->warn('User must change password on first login.');
    }
}
