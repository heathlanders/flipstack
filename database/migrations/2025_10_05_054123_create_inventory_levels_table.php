<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('inventory_levels', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->unsignedBigInteger('organization_id'); // Changed from uuid to bigint
            $table->uuid('product_id');
            $table->uuid('product_variation_id')->nullable();
            $table->uuid('location_id');

            // Stock levels
            $table->integer('quantity_on_hand')->default(0);
            $table->integer('quantity_reserved')->default(0);
            // quantity_available computed as (quantity_on_hand - quantity_reserved)

            // Reorder management
            $table->integer('reorder_point')->default(0);
            $table->integer('reorder_quantity')->default(0);
            $table->integer('max_stock_level')->nullable();

            // Costing
            $table->decimal('average_cost', 15, 2)->default(0);
            // total_cost computed as (quantity_on_hand * average_cost)

            // Tracking
            $table->timestamp('last_movement_at')->nullable();
            $table->timestamp('last_count_at')->nullable();

            $table->integer('version')->default(1);
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('product_variation_id')->references('id')->on('product_variations')->onDelete('cascade');
            $table->foreign('location_id')->references('id')->on('inventory_locations')->onDelete('cascade');
        });

        // Add check constraints using raw SQL
        DB::statement('ALTER TABLE inventory_levels ADD CONSTRAINT non_negative_on_hand CHECK (quantity_on_hand >= 0)');
        DB::statement('ALTER TABLE inventory_levels ADD CONSTRAINT non_negative_reserved CHECK (quantity_reserved >= 0)');
        DB::statement('ALTER TABLE inventory_levels ADD CONSTRAINT reserved_not_exceeding_on_hand CHECK (quantity_reserved <= quantity_on_hand)');

        // Add computed columns
        DB::statement('ALTER TABLE inventory_levels ADD COLUMN quantity_available INTEGER GENERATED ALWAYS AS (quantity_on_hand - quantity_reserved) STORED');
        DB::statement('ALTER TABLE inventory_levels ADD COLUMN total_cost DECIMAL(15,2) GENERATED ALWAYS AS (quantity_on_hand * average_cost) STORED');

        // Create unique constraint handling null variation_id
        DB::statement('CREATE UNIQUE INDEX idx_inventory_levels_unique ON inventory_levels (product_id, COALESCE(product_variation_id, \'00000000-0000-0000-0000-000000000000\'::uuid), location_id)');

        // Performance indexes
        DB::statement('CREATE INDEX idx_inventory_levels_organization ON inventory_levels (organization_id)');
        DB::statement('CREATE INDEX idx_inventory_levels_low_stock ON inventory_levels (organization_id) WHERE quantity_available <= reorder_point');
    }

    public function down()
    {
        Schema::dropIfExists('inventory_levels');
    }
};
