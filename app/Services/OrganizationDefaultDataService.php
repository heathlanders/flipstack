<?php

namespace App\Services;

use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Organization\Organization;
use Database\Seeders\SupplierTierSeeder;

class OrganizationDefaultDataService
{
    /**
     * Seed all default data for a new organization.
     */
    public function seedDefaultData(Organization $organization): void
    {
        $this->seedSupplierTiers($organization);
        $this->seedDefaultCategories($organization);
        $this->seedDefaultBrands($organization);
    }

    /**
     * Seed default supplier tiers for the organization.
     */
    protected function seedSupplierTiers(Organization $organization): void
    {
        $seeder = new SupplierTierSeeder;
        $seeder->createTiersForOrganization($organization);
    }

    /**
     * Seed default categories for the organization.
     */
    protected function seedDefaultCategories(Organization $organization): void
    {
        $categories = [
            [
                'name' => 'Electronics',
                'slug' => 'electronics',
                'description' => 'Electronic devices and components',
                'sort_order' => 1,
            ],
            [
                'name' => 'Computer Parts',
                'slug' => 'computer-parts',
                'description' => 'Computer hardware and components',
                'sort_order' => 2,
            ],
            [
                'name' => 'Mobile Phones & Accessories',
                'slug' => 'mobile-phones-accessories',
                'description' => 'Smartphones and mobile accessories',
                'sort_order' => 3,
            ],
            [
                'name' => 'General Merchandise',
                'slug' => 'general-merchandise',
                'description' => 'General items and merchandise',
                'sort_order' => 4,
            ],
            [
                'name' => 'Parts & Components',
                'slug' => 'parts-components',
                'description' => 'Replacement parts and components',
                'sort_order' => 5,
            ],
            [
                'name' => 'Uncategorized',
                'slug' => 'uncategorized',
                'description' => 'Items pending categorization',
                'sort_order' => 999,
            ],
        ];

        foreach ($categories as $categoryData) {
            $exists = Category::where('organization_id', $organization->id)
                ->where('slug', $categoryData['slug'])
                ->exists();

            if (! $exists) {
                Category::create([
                    'organization_id' => $organization->id,
                    'parent_id' => null,
                    'is_active' => true,
                    ...$categoryData,
                ]);
            }
        }
    }

    /**
     * Seed default brands for the organization.
     */
    protected function seedDefaultBrands(Organization $organization): void
    {
        $brands = [
            [
                'name' => 'Generic',
                'slug' => 'generic',
                'description' => 'Generic or unbranded items',
                'is_active' => true,
            ],
            [
                'name' => 'Unbranded',
                'slug' => 'unbranded',
                'description' => 'Items without identifiable brand',
                'is_active' => true,
            ],
            [
                'name' => 'Unknown',
                'slug' => 'unknown',
                'description' => 'Brand could not be determined',
                'is_active' => true,
            ],
        ];

        foreach ($brands as $brandData) {
            $exists = Brand::where('organization_id', $organization->id)
                ->where('slug', $brandData['slug'])
                ->exists();

            if (! $exists) {
                Brand::create([
                    'organization_id' => $organization->id,
                    ...$brandData,
                ]);
            }
        }
    }
}
