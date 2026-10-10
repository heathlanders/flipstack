<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('supplier_products', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('supplier_id');
            $table->uuid('product_id');
            $table->uuid('product_variation_id')->nullable();

            // Supplier-specific info
            $table->string('supplier_sku')->nullable();
            $table->string('supplier_name', 500)->nullable();
            $table->text('supplier_description')->nullable();

            // Pricing
            $table->decimal('cost_price', 15, 2);
            $table->string('currency', 3)->default('USD');
            $table->integer('minimum_order_quantity')->default(1);

            // Lead times
            $table->integer('lead_time_days')->default(7);
            $table->boolean('is_available')->default(true);
            $table->date('availability_date')->nullable();

            // Quality
            $table->decimal('quality_rating', 3, 2)->default(0);
            $table->boolean('is_preferred')->default(false);

            // Status
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['supplier_id', 'product_id', 'product_variation_id']);

            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('product_variation_id')->references('id')->on('product_variations')->onDelete('cascade');
        });

        // Add check constraint for quality rating
        DB::statement('ALTER TABLE supplier_products ADD CONSTRAINT valid_quality_rating CHECK (quality_rating >= 0 AND quality_rating <= 5)');

        DB::statement('CREATE INDEX idx_supplier_products_supplier ON supplier_products (supplier_id, is_active)');
        DB::statement('CREATE INDEX idx_supplier_products_product ON supplier_products (product_id, is_preferred)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_products');
    }
};
