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
        Schema::create('supplier_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('supplier_id');
            $table->unsignedBigInteger('organization_id');

            // Document Information
            $table->string('name', 255);
            $table->string('document_type', 100);
            $table->text('description')->nullable();

            // File Information
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->string('original_name', 255);

            // Categorization
            $table->enum('category', [
                'business_license',
                'tax_document',
                'insurance',
                'identity',
                'contract',
                'invoice',
                'settlement',
                'tax_form_1099',
                'w9_form',
                'banking',
                'other',
            ])->default('other');

            // Validity
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->boolean('is_expired')->default(false);
            $table->boolean('requires_renewal')->default(false);
            $table->integer('renewal_notice_days')->nullable();

            // Verification
            $table->enum('verification_status', [
                'pending',
                'verified',
                'rejected',
                'expired',
            ])->default('pending');
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_notes')->nullable();

            // Access Control
            $table->boolean('supplier_visible')->default(true);
            $table->boolean('is_confidential')->default(false);

            // Audit
            $table->unsignedBigInteger('uploaded_by')->nullable();
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

            $table->foreign('verified_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('uploaded_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index(['supplier_id', 'category']);
            $table->index(['organization_id', 'document_type']);
            $table->index(['valid_until', 'is_expired']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_documents');
    }
};
