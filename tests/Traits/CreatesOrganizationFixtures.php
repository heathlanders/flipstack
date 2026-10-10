<?php

namespace Tests\Traits;

use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\System\OnboardingProgress;
use App\Models\User\Role;
use App\Models\User\User;

trait CreatesOrganizationFixtures
{
    protected Organization $organization;

    protected Team $team;

    protected User $user;

    protected Role $adminRole;

    protected function setUpOrganizationFixtures(): void
    {
        $this->organization = Organization::factory()->create([
            'sku_prefix' => 'TEST',
            'sku_prefix_locked' => true,
        ]);

        $this->team = Team::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->adminRole = Role::factory()->create([
            'name' => 'Admin',
            'slug' => 'admin',
            'is_system' => true,
            'permissions' => [
                // Inventory
                'inventory.view',
                'inventory.create',
                'inventory.update',
                'inventory.delete',
                'inventory.transfer',
                'inventory.adjust',
                'inventory.batches',
                // Products
                'products.view',
                'products.create',
                'products.edit',
                'products.delete',
                'products.import',
                'products.export',
                'products.bundles',
                // Fulfillment
                'fulfillment.view',
                'fulfillment.process',
                'fulfillment.ship',
                'fulfillment.returns',
                'fulfillment.routing_rules',
                'fulfillment.shipping_rules',
                // Procurement
                'procurement.view',
                'procurement.create',
                'procurement.edit',
                'procurement.approve',
                'procurement.receive',
                'procurement.delete',
                // Customers
                'customers.view',
                'customers.create',
                'customers.edit',
                'customers.delete',
                // Analytics
                'analytics.view',
                'analytics.export',
                'analytics.forecasting',
                // Pricing
                'pricing.view',
                'pricing.create',
                'pricing.edit',
                'pricing.delete',
                // Marketplace
                'marketplace.view',
                'marketplace.create',
                'marketplace.edit',
                'marketplace.delete',
                'marketplace.schedule',
                'marketplace.cross_list',
                // Communication
                'communication.view',
                'communication.reply',
                'communication.archive',
                // Marketing
                'marketing.view',
                'marketing.create',
                'marketing.edit',
                'marketing.delete',
                // Settings
                'settings.view',
                'settings.edit',
                'settings.currency',
                // Admin / Supplier
                'admin.supplier_submissions',
                'suppliers.view',
                'suppliers.manage',
            ],
        ]);

        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->user->roles()->attach($this->adminRole->id, [
            'organization_id' => $this->organization->id,
            'team_id' => null,
        ]);

        $this->user->current_team_id = $this->team->id;
        $this->user->save();

        $this->team->users()->attach($this->user->id, ['role' => 'admin']);

        OnboardingProgress::create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'started_at' => now()->subHour(),
            'completed_at' => now(),
            'current_step' => 'completed',
            'steps_completed' => ['welcome', 'profile', 'organization', 'team'],
        ]);
    }
}
