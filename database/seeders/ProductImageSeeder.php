<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductImageSeeder extends Seeder
{
    public function run(): void
    {
        $products = DB::table('products')->get();

        foreach ($products as $product) {
            $imageCount = rand(1, 5);

            for ($i = 0; $i < $imageCount; $i++) {
                $isPrimary = ($i === 0);
                $imageType = $this->getImageType($i);

                DB::table('product_images')->insert([
                    'id' => Str::uuid(),
                    'product_id' => $product->id,
                    'url' => "https://via.placeholder.com/800x600/cccccc/333333?text={$imageType}",
                    'thumbnail_url' => "https://via.placeholder.com/150x150/cccccc/333333?text={$imageType}",
                    'alt_text' => "{$product->name} - {$imageType}",
                    'sort_order' => $i + 1,
                    'is_primary' => $isPrimary,
                    'metadata' => json_encode([
                        'width' => 800,
                        'height' => 600,
                        'format' => 'JPEG',
                        'file_size' => rand(100000, 2000000),
                        'image_type' => $imageType,
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('✅ Created product images for all products');
    }

    private function getImageType(int $index): string
    {
        $types = ['Main+View', 'Front', 'Back', 'Side', 'Detail', 'Label', 'Ports', 'Accessories'];

        return $types[$index] ?? 'View+'.($index + 1);
    }
}
