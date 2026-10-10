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
        Schema::create('settlements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('organization_id');
            $table->uuid('supplier_id');
            $table->string('settlement_number', 50)->unique();

            // Period Information
            $table->timestamp('settlement_period_start');
            $table->timestamp('settlement_period_end');
            $table->string('period_type', 50)->default('monthly');

            // Financial Summary
            $table->integer('total_sales_count')->default(0);
            $table->integer('total_items_sold')->default(0);
            $table->decimal('gross_sales_amount', 12, 2)->default(0);
            $table->decimal('returns_amount', 12, 2)->default(0);
            $table->decimal('net_sales_amount', 12, 2)->default(0);
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->decimal('fees_deducted', 12, 2)->default(0);
            $table->decimal('adjustments', 12, 2)->default(0);
            $table->decimal('bonuses', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0)->comment('Final amount payable');

            // Tax Information
            $table->decimal('tax_withheld', 10, 2)->default(0);
            $table->jsonb('tax_documents')->nullable()->comment('1099 data, tax forms, etc.');

            // Status Tracking
            $table->enum('status', [
                'draft',
                'pending_review',
                'approved',
                'pending_payment',
                'processing',
                'paid',
                'partially_paid',
                'failed',
                'cancelled',
                'disputed',
            ])->default('draft');

            // Payment Information
            $table->string('payment_method', 50)->nullable();
            $table->string('payment_reference', 255)->nullable();
            $table->timestamp('payment_date')->nullable();
            $table->timestamp('payment_due_date')->nullable();
            $table->jsonb('payment_details')->nullable();

            // Banking Information (encrypted)
            $table->text('bank_account_last4')->nullable();
            $table->string('payment_destination', 255)->nullable();

            // Review & Approval
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();

            // Dispute Handling
            $table->boolean('is_disputed')->default(false);
            $table->text('dispute_reason')->nullable();
            $table->timestamp('dispute_date')->nullable();
            $table->timestamp('dispute_resolved_at')->nullable();
            $table->text('dispute_resolution')->nullable();

            // Notes & Documentation
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->jsonb('attachments')->nullable();

            // Audit
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->foreign('supplier_id')
                ->references('id')
                ->on('suppliers')
                ->onDelete('cascade');

            $table->foreign('reviewed_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('approved_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('processed_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index(['organization_id', 'status']);
            $table->index(['supplier_id', 'status']);
            $table->index(['settlement_period_start', 'settlement_period_end']);
            $table->index(['payment_due_date']);
            $table->index(['organization_id', 'payment_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
