<?php

namespace Database\Seeders;

use App\Models\Organization\Organization;
use App\Models\Supplier\SupplierTier;
use Illuminate\Database\Seeder;

class SupplierTierSeeder extends Seeder
{
    public function run(): void
    {
        $organizations = Organization::all();

        if ($organizations->isEmpty()) {
            $this->command->warn('No organizations found. Skipping tier seeding.');

            return;
        }

        foreach ($organizations as $organization) {
            $this->createTiersForOrganization($organization);
        }

        $this->command->info('Supplier tiers seeded successfully!');
    }

    public function createTiersForOrganization(Organization $organization): void
    {
        $tiers = [
            ['tier_name' => 'Platinum', 'tier_code' => 'PLATINUM', 'description' => 'Elite tier for top-performing suppliers.', 'min_performance_score' => 95, 'max_performance_score' => 100, 'commission_adjustment' => -5.00, 'settlement_frequency' => 'per_sale', 'sort_order' => 1, 'is_default' => false, 'is_active' => true, 'benefits' => ['Immediate per-sale settlements', 'Priority support', 'Featured badge'], 'restrictions' => [], 'requirements' => ['6 months at Gold', '<1% return rate'], 'auto_promotion_enabled' => true, 'auto_demotion_enabled' => true, 'color_code' => '#9333EA', 'icon' => 'crown'],
            ['tier_name' => 'Gold', 'tier_code' => 'GOLD', 'description' => 'High-performing suppliers.', 'min_performance_score' => 85, 'max_performance_score' => 94.99, 'commission_adjustment' => -2.50, 'settlement_frequency' => 'weekly', 'sort_order' => 2, 'is_default' => false, 'is_active' => true, 'benefits' => ['Weekly settlements', 'Priority support'], 'restrictions' => [], 'requirements' => ['3 months at Silver'], 'auto_promotion_enabled' => true, 'auto_demotion_enabled' => true, 'color_code' => '#F59E0B', 'icon' => 'award'],
            ['tier_name' => 'Silver', 'tier_code' => 'SILVER', 'description' => 'Established suppliers.', 'min_performance_score' => 75, 'max_performance_score' => 84.99, 'commission_adjustment' => 0.00, 'settlement_frequency' => 'bi_weekly', 'sort_order' => 3, 'is_default' => true, 'is_active' => true, 'benefits' => ['Bi-weekly settlements', 'Standard support'], 'restrictions' => [], 'requirements' => ['1 month at Bronze'], 'auto_promotion_enabled' => true, 'auto_demotion_enabled' => true, 'color_code' => '#6B7280', 'icon' => 'shield'],
            ['tier_name' => 'Bronze', 'tier_code' => 'BRONZE', 'description' => 'New suppliers.', 'min_performance_score' => 65, 'max_performance_score' => 74.99, 'commission_adjustment' => 2.50, 'settlement_frequency' => 'monthly', 'sort_order' => 4, 'is_default' => false, 'is_active' => true, 'benefits' => ['Monthly settlements', 'Onboarding assistance'], 'restrictions' => ['Max 50 listings'], 'requirements' => ['Completed onboarding'], 'auto_promotion_enabled' => true, 'auto_demotion_enabled' => true, 'color_code' => '#D97706', 'icon' => 'badge'],
            ['tier_name' => 'Probation', 'tier_code' => 'PROBATION', 'description' => 'Suppliers needing improvement.', 'min_performance_score' => 0, 'max_performance_score' => 64.99, 'commission_adjustment' => 5.00, 'settlement_frequency' => 'monthly', 'sort_order' => 5, 'is_default' => false, 'is_active' => true, 'benefits' => ['Improvement support'], 'restrictions' => ['Max 25 listings', 'Manual review'], 'requirements' => ['Improve to 65+ in 60 days'], 'auto_promotion_enabled' => true, 'auto_demotion_enabled' => false, 'color_code' => '#EF4444', 'icon' => 'alert-triangle'],
        ];

        foreach ($tiers as $tierData) {
            $exists = SupplierTier::where('organization_id', $organization->id)
                ->where('tier_code', $tierData['tier_code'])
                ->exists();

            if (! $exists) {
                SupplierTier::create([
                    'organization_id' => $organization->id,
                    ...$tierData,
                ]);
            }
        }
    }
}
