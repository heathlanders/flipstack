<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('product_variations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('parent_product_id');
            $table->string('sku');
            $table->jsonb('variation_attributes')->default('{}');

            // Pricing overrides
            $table->decimal('cost_price', 15, 2)->nullable();
            $table->decimal('selling_price', 15, 2)->nullable();
            $table->decimal('msrp', 15, 2)->nullable();

            // Physical attribute overrides
            $table->decimal('weight', 10, 3)->nullable();
            $table->decimal('length', 10, 2)->nullable();
            $table->decimal('width', 10, 2)->nullable();
            $table->decimal('height', 10, 2)->nullable();

            // Inventory
            $table->integer('stock_quantity')->default(0);
            $table->integer('low_stock_threshold')->nullable();

            // Media
            $table->string('featured_image', 500)->nullable();
            $table->jsonb('gallery_images')->default('[]');

            // Status
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);

            $table->timestamps();
            $table->integer('version')->default(1);

            $table->unique(['parent_product_id', 'sku']);
            $table->foreign('parent_product_id')->references('id')->on('products')->onDelete('cascade');
        });

        DB::statement('CREATE INDEX idx_variations_parent ON product_variations (parent_product_id)');
        DB::statement('CREATE INDEX idx_variations_attributes ON product_variations USING GIN (variation_attributes)');
        DB::statement('CREATE INDEX idx_variations_active ON product_variations (parent_product_id, is_active)');
    }

    public function down()
    {
        Schema::dropIfExists('product_variations');
    }
};
