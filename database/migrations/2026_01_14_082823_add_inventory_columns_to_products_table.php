<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Add quantity and inventory-related columns
            if (! Schema::hasColumn('products', 'quantity')) {
                $table->integer('quantity')->default(0);
            }

            if (! Schema::hasColumn('products', 'reserved_quantity')) {
                $table->integer('reserved_quantity')->default(0);
            }

            if (! Schema::hasColumn('products', 'available_quantity')) {
                $table->integer('available_quantity')->default(0);
            }

            if (! Schema::hasColumn('products', 'minimum_quantity')) {
                $table->integer('minimum_quantity')->default(5);
            }

            if (! Schema::hasColumn('products', 'maximum_quantity')) {
                $table->integer('maximum_quantity')->nullable();
            }

            if (! Schema::hasColumn('products', 'reorder_point')) {
                $table->integer('reorder_point')->default(10);
            }

            if (! Schema::hasColumn('products', 'reorder_quantity')) {
                $table->integer('reorder_quantity')->default(50);
            }

            if (! Schema::hasColumn('products', 'cost_per_unit')) {
                $table->decimal('cost_per_unit', 10, 2)->nullable();
            }

            if (! Schema::hasColumn('products', 'last_stock_update')) {
                $table->timestamp('last_stock_update')->nullable();
            }

            if (! Schema::hasColumn('products', 'stock_status')) {
                $table->enum('stock_status', ['in_stock', 'low_stock', 'out_of_stock', 'discontinued'])->default('in_stock');
            }

            // Add indexes for performance
            $table->index(['organization_id', 'quantity']);
            $table->index(['organization_id', 'stock_status']);
            $table->index('reorder_point');
        });

        // Update available_quantity to match quantity initially
        DB::table('products')->update(['available_quantity' => DB::raw('quantity')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'quantity',
                'reserved_quantity',
                'available_quantity',
                'minimum_quantity',
                'maximum_quantity',
                'reorder_point',
                'reorder_quantity',
                'cost_per_unit',
                'last_stock_update',
                'stock_status',
            ]);
        });
    }
};
