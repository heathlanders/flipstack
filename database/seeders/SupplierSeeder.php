<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $organizations = DB::table('organizations')->get();
        $users = DB::table('users')->pluck('id')->toArray();

        foreach ($organizations as $organization) {
            $this->createSuppliersForOrganization($organization, $users);
        }

        $this->command->info('✅ Created suppliers for all organizations');
    }

    private function createSuppliersForOrganization($organization, array $userIds): void
    {
        $suppliers = [
            [
                'name' => 'NCR Authorized Distributor',
                'code' => 'NCR-AUTH-001',
                'email' => 'orders@ncrauth.com',
                'phone' => '+1-555-0191',
                'website' => 'https://www.ncrauth.com',
                'address_line1' => '1234 Industrial Blvd',
                'address_line2' => 'Suite 500',
                'city' => 'Atlanta',
                'state' => 'GA',
                'postal_code' => '30309',
                'country' => 'US',
                'tax_id' => '12-3456789',
                'business_registration' => 'GA-LLC-123456',
                'payment_terms' => 30, // NET 30
                'currency' => 'USD',
                'minimum_order_amount' => 500.00,
                'lead_time_days' => 3,
                'performance_rating' => 4.8,
                'total_orders' => 145,
                'on_time_delivery_rate' => 96.5,
                'is_active' => true,
                'is_preferred' => true,
                'notes' => 'Authorized NCR distributor with excellent service record',
                'meta_data' => [
                    'specialties' => ['NCR Equipment', 'POS Systems', 'Retail Technology'],
                    'certifications' => ['NCR Authorized', 'ISO 9001'],
                    'contact_person' => 'Michael Anderson',
                    'account_manager' => 'Sarah Johnson',
                    'preferred_contact_method' => 'email',
                    'volume_discounts' => true,
                    'same_day_shipping' => false,
                    'quality_rating' => 4.8,
                ],
            ],
            [
                'name' => 'Electronics Liquidation Warehouse',
                'code' => 'ELIQ-002',
                'email' => 'bulk@eliqwarehouse.com',
                'phone' => '+1-555-0267',
                'website' => 'https://www.eliqwarehouse.com',
                'address_line1' => '789 Commerce Park Drive',
                'city' => 'Orlando',
                'state' => 'FL',
                'postal_code' => '32801',
                'country' => 'US',
                'payment_terms' => 0, // COD
                'currency' => 'USD',
                'minimum_order_amount' => 200.00,
                'lead_time_days' => 7,
                'performance_rating' => 4.2,
                'total_orders' => 67,
                'on_time_delivery_rate' => 87.3,
                'is_active' => true,
                'is_preferred' => false,
                'notes' => 'Liquidation source with variable availability',
                'meta_data' => [
                    'specialties' => ['Restaurant Closeouts', 'Used Equipment', 'Bulk Lots'],
                    'contact_person' => 'Sarah Martinez',
                    'volume_discounts' => true,
                    'inspection_period' => 7,
                    'return_policy' => 'as_is',
                    'lot_sizes' => 'variable',
                ],
            ],
            [
                'name' => 'POS Components Specialist',
                'code' => 'POSCOMP-003',
                'email' => 'parts@poscomp.com',
                'phone' => '+1-555-0343',
                'website' => 'https://www.poscomp.com',
                'address_line1' => '456 Technology Way',
                'city' => 'San Jose',
                'state' => 'CA',
                'postal_code' => '95110',
                'country' => 'US',
                'payment_terms' => 15, // NET 15
                'currency' => 'USD',
                'minimum_order_amount' => 100.00,
                'lead_time_days' => 2,
                'performance_rating' => 4.6,
                'total_orders' => 203,
                'on_time_delivery_rate' => 94.8,
                'is_active' => true,
                'is_preferred' => true,
                'notes' => 'Reliable parts supplier with fast shipping',
                'meta_data' => [
                    'specialties' => ['Replacement Parts', 'Refurbishment', 'Technical Support'],
                    'contact_person' => 'David Kim',
                    'same_day_shipping' => true,
                    'technical_support' => true,
                    'warranty_period' => 90,
                    'cross_reference_database' => true,
                ],
            ],
            [
                'name' => 'Consumer Electronics Wholesale',
                'code' => 'CEW-004',
                'email' => 'wholesale@cew.com',
                'phone' => '+1-555-0445',
                'website' => 'https://www.cew.com',
                'address_line1' => '2100 Electronics Parkway',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '78741',
                'country' => 'US',
                'payment_terms' => 30, // NET 30
                'currency' => 'USD',
                'minimum_order_amount' => 1000.00,
                'lead_time_days' => 5,
                'performance_rating' => 4.4,
                'total_orders' => 89,
                'on_time_delivery_rate' => 91.2,
                'is_active' => true,
                'is_preferred' => false,
                'notes' => 'High-volume electronics wholesaler',
                'meta_data' => [
                    'specialties' => ['Consumer Electronics', 'Mobile Devices', 'Tablets'],
                    'authorized_brands' => ['Apple', 'Samsung', 'HP', 'Dell'],
                    'contact_person' => 'Jennifer Lee',
                    'bulk_pricing' => true,
                    'drop_shipping' => false,
                    'minimum_quantity' => 10,
                ],
            ],
        ];

        foreach ($suppliers as $supplierData) {
            $createdBy = ! empty($userIds) ? $userIds[array_rand($userIds)] : null;

            // Find existing supplier or create a new one
            $existing = DB::table('suppliers')
                ->where('organization_id', $organization->id)
                ->where('code', $supplierData['code'])
                ->first();

            if ($existing) {
                $supplierId = $existing->id;
            } else {
                $supplierId = Str::uuid();

                DB::table('suppliers')->insert([
                    'id' => $supplierId,
                    'organization_id' => $organization->id,
                    'name' => $supplierData['name'],
                    'code' => $supplierData['code'],
                    'email' => $supplierData['email'],
                    'phone' => $supplierData['phone'],
                    'website' => $supplierData['website'],
                    'address_line1' => $supplierData['address_line1'],
                    'address_line2' => $supplierData['address_line2'] ?? null,
                    'city' => $supplierData['city'],
                    'state' => $supplierData['state'],
                    'postal_code' => $supplierData['postal_code'],
                    'country' => $supplierData['country'],
                    'tax_id' => $supplierData['tax_id'] ?? null,
                    'business_registration' => $supplierData['business_registration'] ?? null,
                    'payment_terms' => $supplierData['payment_terms'],
                    'currency' => $supplierData['currency'],
                    'minimum_order_amount' => $supplierData['minimum_order_amount'],
                    'lead_time_days' => $supplierData['lead_time_days'],
                    'performance_rating' => $supplierData['performance_rating'],
                    'total_orders' => $supplierData['total_orders'],
                    'on_time_delivery_rate' => $supplierData['on_time_delivery_rate'],
                    'is_active' => $supplierData['is_active'],
                    'is_preferred' => $supplierData['is_preferred'],
                    'notes' => $supplierData['notes'],
                    'meta_data' => json_encode($supplierData['meta_data']),
                    'created_by' => $createdBy,
                    'created_at' => now()->subDays(rand(30, 180)),
                    'updated_at' => now()->subDays(rand(0, 30)),
                ]);
            }

            // Create a linked supplier user account (skip if email already exists)
            $existingUser = DB::table('users')->where('email', $supplierData['email'])->first();

            if (! $existingUser) {
                $supplierUserId = DB::table('users')->insertGetId([
                    'name' => $supplierData['name'],
                    'email' => $supplierData['email'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'organization_id' => $organization->id,
                    'is_active' => true,
                    'is_supplier' => true,
                    'supplier_status' => 'active',
                    'supplier_id' => $supplierId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $supplierUserId = $existingUser->id;

                DB::table('users')->where('id', $supplierUserId)->update([
                    'is_supplier' => true,
                    'supplier_status' => 'active',
                    'supplier_id' => $supplierId,
                ]);
            }

            // Assign supplier_admin role only for the user's own organization
            $userOrgId = DB::table('users')->where('id', $supplierUserId)->value('organization_id');
            $supplierAdminRole = DB::table('roles')->where('slug', 'supplier_admin')->first();
            if ($supplierAdminRole && (int) $userOrgId === (int) $organization->id) {
                $roleExists = DB::table('user_roles')
                    ->where('user_id', $supplierUserId)
                    ->where('role_id', $supplierAdminRole->id)
                    ->where('organization_id', $organization->id)
                    ->exists();

                if (! $roleExists) {
                    DB::table('user_roles')->insert([
                        'user_id' => $supplierUserId,
                        'role_id' => $supplierAdminRole->id,
                        'organization_id' => $organization->id,
                        'team_id' => null,
                        'expires_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Update user metadata with intended role
            DB::table('users')->where('id', $supplierUserId)->update([
                'metadata' => json_encode([
                    'intended_role' => 'supplier_admin',
                    'is_supplier_user' => true,
                ]),
            ]);

            // Link supplier record back to the user
            DB::table('suppliers')
                ->where('id', $supplierId)
                ->update([
                    'user_id' => $supplierUserId,
                    'portal_enabled' => true,
                    'supplier_status' => 'active',
                    'status_changed_at' => now(),
                ]);
        }
    }
}
