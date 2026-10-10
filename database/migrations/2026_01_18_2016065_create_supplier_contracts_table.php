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
        Schema::create('supplier_contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('supplier_id');
            $table->unsignedBigInteger('organization_id');
            $table->string('contract_number', 50)->unique();
            $table->string('title', 255);
            $table->text('description')->nullable();

            // Contract Period
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->integer('renewal_term_months')->nullable();
            $table->date('renewal_notice_date')->nullable();

            // Terms
            $table->uuid('commission_structure_id')->nullable();
            $table->string('settlement_schedule', 50)->default('monthly');
            $table->integer('payment_terms_days')->default(30);
            $table->decimal('minimum_volume', 12, 2)->nullable();
            $table->decimal('maximum_volume', 12, 2)->nullable();

            // Exclusive Terms
            $table->boolean('is_exclusive')->default(false);
            $table->jsonb('exclusive_categories')->nullable();
            $table->jsonb('exclusive_brands')->nullable();

            // Status
            $table->enum('status', [
                'draft',
                'pending_approval',
                'pending_signature',
                'active',
                'suspended',
                'expired',
                'terminated',
                'renewed',
            ])->default('draft');

            // Signatures
            $table->timestamp('supplier_signed_at')->nullable();
            $table->string('supplier_signature_ip', 45)->nullable();
            $table->timestamp('organization_signed_at')->nullable();
            $table->unsignedBigInteger('organization_signed_by')->nullable();

            // Documents
            $table->string('document_path')->nullable();
            $table->string('signed_document_path')->nullable();
            $table->jsonb('attachments')->nullable();

            // Terms & Conditions
            $table->text('special_terms')->nullable();
            $table->jsonb('custom_clauses')->nullable();

            // Termination
            $table->timestamp('terminated_at')->nullable();
            $table->unsignedBigInteger('terminated_by')->nullable();
            $table->text('termination_reason')->nullable();

            // Audit
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('supplier_id')
                ->references('id')
                ->on('suppliers')
                ->onDelete('cascade');

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->foreign('commission_structure_id')
                ->references('id')
                ->on('commission_structures')
                ->onDelete('set null');

            $table->foreign('organization_signed_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('terminated_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('approved_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index(['supplier_id', 'status']);
            $table->index(['organization_id', 'status']);
            $table->index(['end_date', 'auto_renew']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_contracts');
    }
};
