<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryLocationSeeder extends Seeder
{
    public function run(): void
    {
        $organizations = DB::table('organizations')->get();

        foreach ($organizations as $organization) {
            $this->createLocationsForOrganization($organization);
        }

        $this->command->info('✅ Created inventory locations for all organizations');
    }

    private function createLocationsForOrganization($organization): void
    {
        $locations = [
            [
                'name' => 'Main Warehouse',
                'code' => 'WH-MAIN-001',
                'type' => 'warehouse', // ✅ ALLOWED
                'address_line1' => '1000 Industrial Drive',
                'city' => $this->getCityForOrg($organization),
                'state' => $this->getStateForOrg($organization),
                'postal_code' => $this->getPostalCodeForOrg($organization),
                'country' => 'US',
                'contact_person' => 'Warehouse Manager',
                'email' => 'warehouse@'.$this->getDomainForOrg($organization),
                'phone' => '+1-555-'.rand(1000, 9999),
                'is_active' => true,
                'is_default' => true, // Main warehouse is default
            ],
            [
                'name' => 'Secondary Warehouse',
                'code' => 'WH-SEC-002',
                'type' => 'warehouse', // ✅ ALLOWED
                'address_line1' => '1000 Industrial Drive',
                'address_line2' => 'Building B',
                'city' => $this->getCityForOrg($organization),
                'state' => $this->getStateForOrg($organization),
                'postal_code' => $this->getPostalCodeForOrg($organization),
                'country' => 'US',
                'contact_person' => 'Assistant Manager',
                'email' => 'warehouse2@'.$this->getDomainForOrg($organization),
                'phone' => '+1-555-'.rand(1000, 9999),
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'name' => 'Retail Store Location',
                'code' => 'STORE-001',
                'type' => 'store', // ✅ ALLOWED
                'address_line1' => '500 Commerce Street',
                'city' => $this->getCityForOrg($organization),
                'state' => $this->getStateForOrg($organization),
                'postal_code' => $this->getPostalCodeForOrg($organization),
                'country' => 'US',
                'contact_person' => 'Store Manager',
                'email' => 'store@'.$this->getDomainForOrg($organization),
                'phone' => '+1-555-'.rand(1000, 9999),
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'name' => 'Customer Service Center',
                'code' => 'CUST-SVC-001',
                'type' => 'customer', // ✅ ALLOWED
                'address_line1' => '200 Service Plaza',
                'city' => $this->getCityForOrg($organization),
                'state' => $this->getStateForOrg($organization),
                'postal_code' => $this->getPostalCodeForOrg($organization),
                'country' => 'US',
                'contact_person' => 'Customer Service Manager',
                'email' => 'service@'.$this->getDomainForOrg($organization),
                'phone' => '+1-555-'.rand(1000, 9999),
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'name' => 'Virtual Inventory Pool',
                'code' => 'VIRT-001',
                'type' => 'virtual', // ✅ ALLOWED
                'address_line1' => null,
                'address_line2' => null,
                'city' => null,
                'state' => null,
                'postal_code' => null,
                'country' => 'US',
                'contact_person' => 'System Administrator',
                'email' => 'virtual@'.$this->getDomainForOrg($organization),
                'phone' => '+1-555-'.rand(1000, 9999),
                'is_active' => true,
                'is_default' => false,
            ],
        ];

        foreach ($locations as $locationData) {
            // Check if location already exists (idempotent check)
            $existing = DB::table('inventory_locations')
                ->where('organization_id', $organization->id)
                ->where('code', $locationData['code'])
                ->first();

            if ($existing) {
                // Update existing location
                DB::table('inventory_locations')
                    ->where('id', $existing->id)
                    ->update([
                        'name' => $locationData['name'],
                        'type' => $locationData['type'],
                        'address_line1' => $locationData['address_line1'],
                        'address_line2' => $locationData['address_line2'] ?? null,
                        'city' => $locationData['city'],
                        'state' => $locationData['state'],
                        'postal_code' => $locationData['postal_code'],
                        'country' => $locationData['country'],
                        'contact_person' => $locationData['contact_person'],
                        'email' => $locationData['email'],
                        'phone' => $locationData['phone'],
                        'is_active' => $locationData['is_active'],
                        'is_default' => $locationData['is_default'],
                        'updated_at' => now(),
                    ]);

                $this->command->info("   ↻ Updated location: {$locationData['code']} for {$organization->name}");
            } else {
                // Insert new location
                DB::table('inventory_locations')->insert([
                    'id' => Str::uuid(),
                    'organization_id' => $organization->id,
                    'name' => $locationData['name'],
                    'code' => $locationData['code'],
                    'type' => $locationData['type'],
                    'address_line1' => $locationData['address_line1'],
                    'address_line2' => $locationData['address_line2'] ?? null,
                    'city' => $locationData['city'],
                    'state' => $locationData['state'],
                    'postal_code' => $locationData['postal_code'],
                    'country' => $locationData['country'],
                    'contact_person' => $locationData['contact_person'],
                    'email' => $locationData['email'],
                    'phone' => $locationData['phone'],
                    'is_active' => $locationData['is_active'],
                    'is_default' => $locationData['is_default'],
                    'created_at' => now()->subDays(rand(30, 90)),
                    'updated_at' => now()->subDays(rand(0, 15)),
                ]);

                $this->command->info("   ✓ Created location: {$locationData['code']} for {$organization->name}");
            }
        }
    }

    private function getCityForOrg($organization): string
    {
        $cities = [
            'Atlanta',
            'Phoenix',
            'Dallas',
            'Orlando',
            'Denver',
        ];

        $index = abs(crc32($organization->slug)) % count($cities);

        return $cities[$index];
    }

    private function getStateForOrg($organization): string
    {
        $states = [
            'Atlanta' => 'GA',
            'Phoenix' => 'AZ',
            'Dallas' => 'TX',
            'Orlando' => 'FL',
            'Denver' => 'CO',
        ];

        $city = $this->getCityForOrg($organization);

        return $states[$city];
    }

    private function getPostalCodeForOrg($organization): string
    {
        $postalCodes = [
            'Atlanta' => '30309',
            'Phoenix' => '85001',
            'Dallas' => '75201',
            'Orlando' => '32801',
            'Denver' => '80202',
        ];

        $city = $this->getCityForOrg($organization);

        return $postalCodes[$city];
    }

    private function getDomainForOrg($organization): string
    {
        // Extract domain from organization slug
        $slug = $organization->slug ?? 'example';

        return str_replace('-', '', $slug).'.com';
    }
}
