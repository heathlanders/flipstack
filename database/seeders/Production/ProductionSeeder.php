<?php

namespace Database\Seeders\Production;

use Illuminate\Database\Seeder;

class ProductionSeeder extends Seeder
{
    /**
     * Production Database Seeder
     *
     * Seeds essential system/reference data for production deployments.
     * This seeder creates ONLY system-wide data (organization_id = null),
     * not test organizations, users, or sample products.
     *
     * Run with: php artisan db:seed:production
     */
    public function run(): void
    {
        $this->command->info('Starting production database seeding...');
        $this->command->newLine();

        // Phase 1: System Roles (must be created before admin user to assign super_admin role)
        $this->command->info('Phase 1: Seeding system roles...');
        $this->call(SystemRoleSeeder::class);
        $this->command->newLine();

        // Phase 2: System Admin User
        $this->command->info('Phase 2: Creating system admin user...');
        $this->call(SystemAdminSeeder::class);
        $this->command->newLine();

        // Phase 3: eBay Categories
        $this->command->info('Phase 3: Seeding eBay category reference data...');
        $this->call(SystemEbayCategorySeeder::class);
        $this->command->newLine();

        // Phase 4: AI Category Prompts
        $this->command->info('Phase 4: Seeding AI category prompts...');
        $this->call(SystemAiPromptSeeder::class);
        $this->command->newLine();

        $this->command->info('Production database seeding complete!');
        $this->displaySummary();
    }

    /**
     * Display summary of seeded data.
     */
    protected function displaySummary(): void
    {
        $this->command->newLine();
        $this->command->info('=== Production Seeding Summary ===');

        $adminCount = \App\Models\User\User::whereNull('organization_id')->count();
        $roleCount = \App\Models\User\Role::whereNull('organization_id')->count();
        $categoryCount = \Illuminate\Support\Facades\DB::table('ebay_categories')->count();
        $promptCount = \App\Models\AI\AiCategoryPrompt::whereNull('organization_id')->count();

        $this->command->table(
            ['Data Type', 'Count'],
            [
                ['System Admin Users', $adminCount],
                ['System Roles', $roleCount],
                ['eBay Categories', $categoryCount],
                ['AI Prompts', $promptCount],
            ]
        );

        $this->command->newLine();
        $this->command->info('Next steps:');
        $this->command->line('  1. Log in as the system admin to change the default password');
        $this->command->line('  2. Create your first organization via the registration flow');
        $this->command->line('  3. Configure organization-specific settings');
    }
}
