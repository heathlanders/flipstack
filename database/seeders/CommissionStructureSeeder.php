<?php

namespace Database\Seeders;

use App\Models\Organization\Organization;
use App\Models\Supplier\CommissionStructure;
use Illuminate\Database\Seeder;

class CommissionStructureSeeder extends Seeder
{
    public function run(): void
    {
        $organizations = Organization::all();

        if ($organizations->isEmpty()) {
            $this->command->warn('No organizations found. Skipping commission structure seeding.');

            return;
        }

        foreach ($organizations as $organization) {
            $this->createStructuresForOrganization($organization);
        }

        $this->command->info('Commission structures seeded successfully!');
    }

    protected function createStructuresForOrganization(Organization $organization): void
    {
        $structures = [
            [
                'name' => 'Standard Percentage',
                'code' => 'STANDARD_PCT',
                'description' => 'Default percentage-based commission for most products.',
                'type' => 'percentage',
                'base_percentage' => 15.00,
                'flat_fee_amount' => null,
                'minimum_commission' => 0.50,
                'maximum_commission' => null,
                'tier_adjustments' => [],
                'category_overrides' => [],
                'volume_bonuses' => [],
                'price_tiers' => [],
                'is_active' => true,
                'priority' => 100,
            ],
            [
                'name' => 'Electronics Commission',
                'code' => 'ELECTRONICS',
                'description' => 'Commission structure for electronics category.',
                'type' => 'percentage',
                'base_percentage' => 12.00,
                'flat_fee_amount' => null,
                'minimum_commission' => 1.00,
                'maximum_commission' => 150.00,
                'tier_adjustments' => [],
                'category_overrides' => [],
                'volume_bonuses' => [
                    ['min_volume' => 5000, 'bonus_percentage' => -1.0],
                    ['min_volume' => 10000, 'bonus_percentage' => -2.0],
                ],
                'price_tiers' => [],
                'is_active' => true,
                'priority' => 90,
            ],
            [
                'name' => 'Tiered Volume',
                'code' => 'TIERED_VOLUME',
                'description' => 'Commission decreases with higher sale price.',
                'type' => 'tiered',
                'base_percentage' => 15.00,
                'flat_fee_amount' => null,
                'minimum_commission' => 0.50,
                'maximum_commission' => null,
                'tier_adjustments' => [],
                'category_overrides' => [],
                'volume_bonuses' => [],
                'price_tiers' => [
                    ['min_price' => 0, 'max_price' => 50, 'percentage' => 18.00],
                    ['min_price' => 50.01, 'max_price' => 200, 'percentage' => 15.00],
                    ['min_price' => 200.01, 'max_price' => 500, 'percentage' => 12.00],
                    ['min_price' => 500.01, 'max_price' => null, 'percentage' => 10.00],
                ],
                'is_active' => true,
                'priority' => 80,
            ],
            [
                'name' => 'Flat Fee Per Item',
                'code' => 'FLAT_FEE',
                'description' => 'Fixed fee per item sold, regardless of price.',
                'type' => 'flat_fee',
                'base_percentage' => null,
                'flat_fee_amount' => 2.50,
                'minimum_commission' => 2.50,
                'maximum_commission' => 2.50,
                'tier_adjustments' => [],
                'category_overrides' => [],
                'volume_bonuses' => [],
                'price_tiers' => [],
                'is_active' => true,
                'priority' => 70,
            ],
            [
                'name' => 'Hybrid Commission',
                'code' => 'HYBRID',
                'description' => 'Combination of percentage plus flat fee.',
                'type' => 'hybrid',
                'base_percentage' => 10.00,
                'flat_fee_amount' => 1.00,
                'minimum_commission' => 1.50,
                'maximum_commission' => null,
                'tier_adjustments' => [],
                'category_overrides' => [],
                'volume_bonuses' => [],
                'price_tiers' => [],
                'is_active' => true,
                'priority' => 60,
            ],
        ];

        foreach ($structures as $structureData) {
            $exists = CommissionStructure::where('organization_id', $organization->id)
                ->where('code', $structureData['code'])
                ->exists();

            if (! $exists) {
                CommissionStructure::create([
                    'organization_id' => $organization->id,
                    ...$structureData,
                ]);
            }
        }
    }
}
