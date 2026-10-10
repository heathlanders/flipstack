<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupplierProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = DB::table('products')->get();
        $suppliers = DB::table('suppliers')->where('is_active', true)->get();

        if ($suppliers->isEmpty()) {
            $this->command->warn('No active suppliers found - skipping supplier product seeder');

            return;
        }

        foreach ($products as $product) {
            $this->createSupplierRelationships($product, $suppliers);
        }

        $this->command->info('✅ Created supplier relationships for all products');
    }

    private function createSupplierRelationships($product, $suppliers): void
    {
        $orgSuppliers = $suppliers->where('organization_id', $product->organization_id);

        if ($orgSuppliers->isEmpty()) {
            return;
        }

        $relationshipCount = min(rand(1, 2), $orgSuppliers->count());
        $selectedSuppliers = $orgSuppliers->random($relationshipCount);

        foreach ($selectedSuppliers as $index => $supplier) {
            $this->createSupplierProduct($product, $supplier, $index === 0);
        }
    }

    private function createSupplierProduct($product, $supplier, bool $isPrimary): void
    {
        $exists = DB::table('supplier_products')
            ->where('supplier_id', $supplier->id)
            ->where('product_id', $product->id)
            ->whereNull('product_variation_id')
            ->exists();

        if ($exists) {
            return;
        }

        $supplierPrice = $this->calculateSupplierPrice($product);

        DB::table('supplier_products')->insert([
            'id' => Str::uuid(),
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'product_variation_id' => null,
            'supplier_sku' => $this->generateSupplierSku($product, $supplier),
            'supplier_name' => $product->name,
            'supplier_description' => null,
            'cost_price' => $supplierPrice,
            'currency' => $supplier->currency ?? 'USD',
            'minimum_order_quantity' => rand(1, 5),
            'lead_time_days' => $supplier->lead_time_days ?? rand(3, 14),
            'is_available' => true,
            'availability_date' => null,
            'quality_rating' => round(rand(30, 50) / 10, 2),
            'is_preferred' => $isPrimary,
            'is_active' => true,
            'notes' => null,
            'created_at' => now()->subDays(rand(30, 90)),
            'updated_at' => now()->subDays(rand(0, 15)),
        ]);
    }

    private function calculateSupplierPrice($product): float
    {
        $retailPrice = $product->selling_price ?? $product->cost_price ?? 100;
        $marginFactor = 0.5 + (rand(0, 30) / 100);

        return round($retailPrice * $marginFactor, 2);
    }

    private function generateSupplierSku($product, $supplier): string
    {
        $supplierCode = strtoupper(substr($supplier->code ?? $supplier->name ?? 'SUP', 0, 3));
        $productSku = $product->sku ?? 'ITEM';

        return $supplierCode.'-'.$productSku;
    }
}
