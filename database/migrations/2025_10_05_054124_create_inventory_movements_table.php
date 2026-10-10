<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->unsignedBigInteger('organization_id'); // Changed from uuid to bigint
            $table->uuid('product_id');
            $table->uuid('product_variation_id')->nullable();
            $table->uuid('location_id');

            // Movement details
            $table->enum('type', ['purchase', 'sale', 'adjustment', 'transfer_in', 'transfer_out', 'return', 'damaged', 'lost', 'found']);
            $table->integer('quantity'); // Can be negative
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->decimal('total_cost', 15, 2)->nullable();

            // Reference
            $table->string('reference_type', 50)->nullable();
            $table->uuid('reference_id')->nullable();
            $table->string('reference_number')->nullable();

            // Details
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();

            // Batch/lot tracking
            $table->string('batch_number')->nullable();
            $table->string('lot_number')->nullable();
            $table->date('expiry_date')->nullable();

            // Audit trail
            $table->unsignedBigInteger('created_by'); // Changed from uuid to bigint
            $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));

            // Balance tracking
            $table->integer('balance_before')->nullable();
            $table->integer('balance_after')->nullable();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('product_variation_id')->references('id')->on('product_variations')->onDelete('cascade');
            $table->foreign('location_id')->references('id')->on('inventory_locations')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users');
        });

        // Performance indexes
        DB::statement('CREATE INDEX idx_inventory_movements_product ON inventory_movements (product_id, created_at DESC)');
        DB::statement('CREATE INDEX idx_inventory_movements_location ON inventory_movements (location_id, created_at DESC)');
        DB::statement('CREATE INDEX idx_inventory_movements_type ON inventory_movements (organization_id, type, created_at DESC)');
        DB::statement('CREATE INDEX idx_inventory_movements_reference ON inventory_movements (reference_type, reference_id)');
    }

    public function down()
    {
        Schema::dropIfExists('inventory_movements');
    }
};
