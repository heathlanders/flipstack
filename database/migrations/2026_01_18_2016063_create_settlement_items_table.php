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
        Schema::create('settlement_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('settlement_id');
            $table->uuid('product_id');
            $table->uuid('order_id')->nullable();
            $table->uuid('order_item_id')->nullable();

            // Sale Information
            $table->timestamp('sale_date');
            $table->string('sale_reference', 100)->nullable();
            $table->string('marketplace', 50)->nullable()->comment('eBay, Amazon, Direct, etc.');

            // Financial Details
            $table->decimal('sale_price', 10, 2);
            $table->decimal('original_cost', 10, 2)->nullable();
            $table->decimal('shipping_collected', 10, 2)->default(0);
            $table->decimal('tax_collected', 10, 2)->default(0);
            $table->decimal('marketplace_fees', 10, 2)->default(0);
            $table->decimal('net_sale_amount', 10, 2);

            // Commission Calculation
            $table->uuid('commission_structure_id')->nullable();
            $table->string('commission_type', 50);
            $table->decimal('commission_rate', 5, 2);
            $table->decimal('commission_amount', 10, 2);
            $table->decimal('tier_adjustment', 10, 2)->default(0);
            $table->decimal('bonus_amount', 10, 2)->default(0);
            $table->decimal('final_commission', 10, 2);

            // Supplier Payout
            $table->decimal('supplier_payout', 10, 2)->comment('Amount due to supplier');

            // Status
            $table->enum('status', [
                'pending',
                'approved',
                'disputed',
                'adjusted',
                'voided',
            ])->default('pending');

            // Return/Refund Information
            $table->boolean('is_returned')->default(false);
            $table->timestamp('return_date')->nullable();
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->text('return_reason')->nullable();

            // Notes
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->foreign('settlement_id')
                ->references('id')
                ->on('settlements')
                ->onDelete('cascade');

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('cascade');

            $table->foreign('commission_structure_id')
                ->references('id')
                ->on('commission_structures')
                ->onDelete('set null');

            $table->index(['settlement_id', 'status']);
            $table->index(['product_id']);
            $table->index(['sale_date']);
            $table->index(['marketplace']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settlement_items');
    }
};
