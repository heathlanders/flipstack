<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('ebay_listing_id')->nullable()->constrained('ebay_listings')->nullOnDelete();
            $table->string('marketplace_item_id')->nullable();
            $table->string('sku')->nullable();
            $table->string('title');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->string('condition', 100)->nullable();
            $table->string('image_url', 500)->nullable();
            $table->foreignUuid('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignUuid('location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->string('pick_status', 20)->default('pending'); // pending, picked, short
            $table->timestamp('picked_at')->nullable();
            $table->foreignId('picked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('weight_oz', 8, 2)->nullable();
            $table->json('marketplace_data')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index('product_id');
            $table->index('supplier_id');
            $table->index('ebay_listing_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
