<?php

namespace Database\Seeders;

use App\Models\Organization\Organization;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $organizations = [
            [
                'name' => 'Demo Reseller Pro',
                'slug' => 'demo-reseller-pro',
                'email' => 'admin@demoreseller.test',
                'phone' => '+1-555-0123',
                'description' => 'Premium NCR equipment reseller with 15+ years experience. Specializing in POS systems, terminals, and parts.',
                'settings' => [
                    'default_currency' => 'USD',
                    'default_tax_rate' => 8.25,
                    'auto_list_to_ebay' => true,
                    'require_approval' => false,
                    'default_condition' => 'used_excellent',
                    'markup_percentage' => 25.0,
                ],
                'branding' => [
                    'primary_color' => '#1e40af',
                    'logo_url' => 'https://via.placeholder.com/200x80/1e40af/white?text=NCR+Pro',
                    'business_type' => 'electronics_reseller',
                ],
                'timezone' => 'America/New_York',
                'currency' => 'USD',
                'status' => 'active',
            ],
            [
                'name' => 'TechFlow Solutions',
                'slug' => 'techflow-solutions',
                'email' => 'support@techflow.com',
                'phone' => '+1-555-0456',
                'description' => 'High-volume electronics reseller with advanced automation. Processing 500+ items monthly across multiple channels.',
                'settings' => [
                    'default_currency' => 'USD',
                    'default_tax_rate' => 7.75,
                    'auto_list_to_ebay' => true,
                    'require_approval' => true,
                    'default_condition' => 'new',
                    'markup_percentage' => 35.0,
                    'bulk_processing' => true,
                ],
                'branding' => [
                    'primary_color' => '#059669',
                    'logo_url' => 'https://via.placeholder.com/200x80/059669/white?text=TechFlow',
                    'business_type' => 'electronics_wholesaler',
                ],
                'timezone' => 'America/Los_Angeles',
                'currency' => 'USD',
                'status' => 'active',
            ],
            [
                'name' => 'RetailTech Depot',
                'slug' => 'retailtech-depot',
                'email' => 'orders@retailtech.com',
                'phone' => '+1-555-0789',
                'description' => 'Specialized in retail technology equipment. Focus on POS systems, barcode scanners, and payment terminals.',
                'settings' => [
                    'default_currency' => 'USD',
                    'default_tax_rate' => 9.0,
                    'auto_list_to_ebay' => false,
                    'require_approval' => true,
                    'default_condition' => 'refurbished',
                    'markup_percentage' => 30.0,
                    'quality_control' => true,
                ],
                'branding' => [
                    'primary_color' => '#dc2626',
                    'logo_url' => 'https://via.placeholder.com/200x80/dc2626/white?text=RetailTech',
                    'business_type' => 'pos_specialist',
                ],
                'timezone' => 'America/Chicago',
                'currency' => 'USD',
                'status' => 'active',
            ],
            [
                'name' => 'Global Parts Exchange',
                'slug' => 'global-parts-exchange',
                'email' => 'info@globalparts.com',
                'phone' => '+1-555-0321',
                'description' => 'International parts and components marketplace. Serving retailers, repair shops, and system integrators worldwide.',
                'settings' => [
                    'default_currency' => 'USD',
                    'default_tax_rate' => 0.0,
                    'auto_list_to_ebay' => true,
                    'require_approval' => false,
                    'default_condition' => 'used_good',
                    'markup_percentage' => 40.0,
                    'international_shipping' => true,
                ],
                'branding' => [
                    'primary_color' => '#7c3aed',
                    'logo_url' => 'https://via.placeholder.com/200x80/7c3aed/white?text=Global+Parts',
                    'business_type' => 'parts_distributor',
                ],
                'timezone' => 'America/New_York',
                'currency' => 'USD',
                'status' => 'active',
            ],
            [
                'name' => 'Demo Test Org',
                'slug' => 'demo-test-org',
                'email' => 'demo@test.com',
                'phone' => '+1-555-9999',
                'description' => 'Test organization for development and demo purposes. Contains edge cases and experimental configurations.',
                'settings' => [
                    'default_currency' => 'USD',
                    'default_tax_rate' => 5.0,
                    'auto_list_to_ebay' => false,
                    'require_approval' => true,
                    'default_condition' => 'new',
                    'markup_percentage' => 50.0,
                    'test_mode' => true,
                ],
                'branding' => [
                    'primary_color' => '#64748b',
                    'logo_url' => 'https://via.placeholder.com/200x80/64748b/white?text=Demo',
                    'business_type' => 'test_environment',
                ],
                'timezone' => 'UTC',
                'currency' => 'USD',
                'status' => 'trial',
                'trial_ends_at' => now()->addDays(30),
            ],
        ];

        foreach ($organizations as $orgData) {
            Organization::create($orgData);
        }

        $this->command->info('✅ Created '.count($organizations).' test organizations');
    }
}
