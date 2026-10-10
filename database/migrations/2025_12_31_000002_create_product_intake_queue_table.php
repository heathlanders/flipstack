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
        Schema::create('product_intake_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');

            // Capture Info
            $table->foreignId('captured_by')->constrained('users');
            $table->timestamp('captured_at');
            $table->string('capture_method', 50)->nullable();

            // Captured Data
            $table->string('barcode', 13)->nullable();
            $table->json('photos')->nullable();
            $table->text('user_notes')->nullable();

            // Quick Fields
            $table->integer('quick_quantity')->nullable();
            $table->string('quick_location')->nullable();

            // AI Processing
            $table->string('ai_analysis_status', 50)->default('pending');
            $table->foreignId('ai_analysis_id')->nullable()->constrained('product_ai_analyses')->nullOnDelete();
            $table->timestamp('ai_started_at')->nullable();
            $table->timestamp('ai_completed_at')->nullable();
            $table->decimal('ai_confidence_score', 3, 2)->nullable();

            // Enriched Data
            $table->json('enriched_data')->nullable();

            // Queue Status
            $table->string('processing_status', 50)->default('pending');
            $table->foreignId('processed_by')->nullable()->constrained('users');
            $table->timestamp('processed_at')->nullable();

            // Final Product (UUID reference)
            $table->uuid('product_id')->nullable();

            // Priority & Batching
            $table->integer('priority')->default(0);
            $table->uuid('batch_id')->nullable();

            $table->timestamps();

            // Foreign keys
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();

            // Indexes
            $table->index(['team_id', 'processing_status']);
            $table->index('captured_by');
            $table->index('ai_analysis_status');
            $table->index('created_at');
            $table->index('batch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_intake_queue');
    }
};
