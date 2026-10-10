<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('organization_id');
            $table->string('batch_number', 50)->unique();
            $table->uuid('supplier_id')->nullable();
            $table->uuid('purchase_order_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('acquisition_date')->nullable();
            $table->decimal('acquisition_cost', 12, 2)->default(0);
            $table->integer('item_count')->default(0);
            $table->decimal('cost_per_item', 12, 2)->default(0);
            $table->string('source_description')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('open');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('set null');
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->onDelete('set null');
            $table->index(['organization_id', 'status']);
        });

        Schema::create('inventory_batch_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('batch_id');
            $table->uuid('product_id');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->timestamps();

            $table->foreign('batch_id')->references('id')->on('inventory_batches')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->index(['batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_batch_items');
        Schema::dropIfExists('inventory_batches');
    }
};
