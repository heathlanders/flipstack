<?php

namespace Database\Seeders;

use App\Models\Catalog\Product;
use App\Models\Inventory\InventoryLevel;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\InventoryMovement;
use App\Models\User\User;
use Illuminate\Database\Seeder;

class InventoryMovementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding inventory movements...');

        // Get a user for created_by
        $user = User::first();
        if (! $user) {
            $this->command->warn('No user found. Skipping seeder.');

            return;
        }

        $organizationId = $user->organization_id ?? $user->current_organization_id ?? $user->currentTeam?->id ?? 1;

        // Get products and locations
        $products = Product::where('organization_id', $organizationId)
            ->where('manage_stock', true)
            ->take(10)
            ->get();

        $locations = InventoryLocation::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->get();

        if ($products->isEmpty()) {
            $this->command->warn('No products found. Skipping seeder.');

            return;
        }

        if ($locations->isEmpty()) {
            $this->command->warn('No locations found. Skipping seeder.');

            return;
        }

        // Create inventory levels for each product at each location
        foreach ($products as $product) {
            foreach ($locations as $location) {
                $onHand = rand(0, 100);
                $reserved = rand(0, min(10, $onHand));

                InventoryLevel::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'location_id' => $location->id,
                    ],
                    [
                        'quantity_on_hand' => $onHand,
                        'quantity_reserved' => $reserved,
                        'last_counted_at' => now()->subDays(rand(1, 30)),
                    ]
                );
            }
        }

        $this->command->info('Created inventory levels.');

        // Create sample movements
        $movementTypes = [
            'adjustment',
            'transfer',
            'reservation',
            'release',
            'sale',
            'return',
            'damaged',
            'recount',
        ];

        $reasonCodes = [
            'recount',
            'damaged',
            'theft',
            'found',
            'received',
            'returned',
            'correction',
            'other',
        ];

        for ($i = 0; $i < 50; $i++) {
            $product = $products->random();
            $movementType = $movementTypes[array_rand($movementTypes)];
            $fromLocation = $locations->random();
            $toLocation = $locations->where('id', '!=', $fromLocation->id)->random();

            $movement = [
                'organization_id' => $organizationId,
                'product_id' => $product->id,
                'movement_type' => $movementType,
                'quantity' => rand(1, 20),
                'created_by' => $user->id,
                'processed_at' => now()->subDays(rand(0, 60)),
                'created_at' => now()->subDays(rand(0, 60)),
            ];

            // Set locations based on movement type
            if ($movementType === 'transfer') {
                $movement['from_location_id'] = $fromLocation->id;
                $movement['to_location_id'] = $toLocation->id;
            } elseif (in_array($movementType, ['reservation', 'sale', 'damaged'])) {
                $movement['from_location_id'] = $fromLocation->id;
            } elseif (in_array($movementType, ['return', 'release'])) {
                $movement['to_location_id'] = $fromLocation->id;
            } elseif ($movementType === 'adjustment') {
                $movement['reason_code'] = $reasonCodes[array_rand($reasonCodes)];
                $movement['quantity'] = rand(-10, 20); // Can be negative
            }

            // Add notes randomly
            if (rand(0, 2) === 0) {
                $notes = [
                    'Monthly inventory recount',
                    'Customer order fulfillment',
                    'Damaged during shipping',
                    'Found extra stock during cleanup',
                    'Restocking from supplier',
                    'Moving to new warehouse section',
                    'Quality control issue',
                    'System correction',
                ];
                $movement['notes'] = $notes[array_rand($notes)];
            }

            InventoryMovement::create($movement);
        }

        $this->command->info('Created 50 sample inventory movements.');
    }
}
