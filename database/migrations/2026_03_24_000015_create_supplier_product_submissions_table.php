<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_product_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('organization_id');
            $table->uuid('supplier_id');
            $table->unsignedBigInteger('intake_queue_id')->nullable();
            $table->string('status', 20)->default('pending_review');
            $table->jsonb('submitted_data');
            $table->text('review_notes')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();
            $table->foreign('intake_queue_id')->references('id')->on('product_intake_queue')->nullOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['organization_id', 'status']);
            $table->index(['supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_product_submissions');
    }
};
