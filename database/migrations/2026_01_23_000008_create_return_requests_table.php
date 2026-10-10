<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('order_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('shipment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('rma_number')->unique();
            $table->string('status', 50)->default('requested'); // requested, approved, shipped_back, received, inspected, refunded, denied, cancelled
            $table->string('reason');
            $table->text('reason_details')->nullable();
            $table->string('source', 50)->default('buyer'); // buyer, internal, marketplace
            $table->string('marketplace_case_id')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->string('refund_method', 50)->nullable(); // original_payment, store_credit
            $table->boolean('restockable')->default(true);
            $table->string('condition_on_return', 100)->nullable();
            $table->text('inspection_notes')->nullable();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('inspected_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('refunded_at')->nullable();
            $table->string('return_tracking_number', 100)->nullable();
            $table->string('return_carrier', 50)->nullable();
            $table->string('return_label_url', 500)->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index('rma_number');
            $table->index('status');
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_requests');
    }
};
