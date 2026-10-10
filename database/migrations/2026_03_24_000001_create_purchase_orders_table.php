<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('organization_id');
            $table->uuid('supplier_id')->nullable();
            $table->uuid('location_id')->nullable();
            $table->string('po_number', 50)->unique();
            $table->string('status', 30)->default('draft');
            $table->date('order_date')->nullable();
            $table->date('expected_delivery_date')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('shipping_cost', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('set null');
            $table->foreign('location_id')->references('id')->on('inventory_locations')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'created_at']);
            $table->index(['supplier_id']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('purchase_order_id');
            $table->uuid('product_id')->nullable();
            $table->uuid('supplier_product_id')->nullable();
            $table->string('description')->nullable();
            $table->string('sku')->nullable();
            $table->integer('quantity_ordered')->default(0);
            $table->integer('quantity_received')->default(0);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
            $table->foreign('supplier_product_id')->references('id')->on('supplier_products')->onDelete('set null');

            $table->index(['purchase_order_id']);
        });

        Schema::create('purchase_order_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('purchase_order_id');
            $table->unsignedBigInteger('received_by')->nullable();
            $table->uuid('location_id')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->onDelete('cascade');
            $table->foreign('received_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('location_id')->references('id')->on('inventory_locations')->onDelete('set null');

            $table->index(['purchase_order_id']);
        });

        Schema::create('purchase_order_receipt_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('receipt_id');
            $table->uuid('purchase_order_item_id');
            $table->uuid('product_id')->nullable();
            $table->integer('quantity_received')->default(0);
            $table->string('condition', 30)->default('good');
            $table->text('condition_notes')->nullable();
            $table->timestamps();

            $table->foreign('receipt_id')->references('id')->on('purchase_order_receipts')->onDelete('cascade');
            $table->foreign('purchase_order_item_id')->references('id')->on('purchase_order_items')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');

            $table->index(['receipt_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_receipt_items');
        Schema::dropIfExists('purchase_order_receipts');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
