<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('product_bundles', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('bundle_product_id');
            $table->uuid('component_product_id');
            $table->uuid('component_variation_id')->nullable();
            $table->integer('quantity')->default(1);
            $table->boolean('is_optional')->default(false);
            $table->integer('sort_order')->default(0);

            $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));

            $table->unique(['bundle_product_id', 'component_product_id', 'component_variation_id']);

            $table->foreign('bundle_product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('component_product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('component_variation_id')->references('id')->on('product_variations')->onDelete('cascade');
        });

        // Add check constraint for positive quantity
        DB::statement('ALTER TABLE product_bundles ADD CONSTRAINT positive_quantity CHECK (quantity > 0)');
    }

    public function down()
    {
        Schema::dropIfExists('product_bundles');
    }
};
