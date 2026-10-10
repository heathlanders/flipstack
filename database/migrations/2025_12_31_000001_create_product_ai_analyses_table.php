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
        Schema::create('product_ai_analyses', function (Blueprint $table) {
            $table->id();
            $table->uuid('product_id')->nullable();
            $table->unsignedBigInteger('queue_item_id')->nullable();

            // Analysis metadata
            $table->string('analysis_type', 50)->default('product_identification');
            $table->json('input_images')->nullable();

            // AI Provider info
            $table->string('ai_provider', 50)->default('local');
            $table->string('model_version', 50)->nullable();

            // Extracted data from AI
            $table->json('extracted_text')->nullable();
            $table->string('detected_category')->nullable();
            $table->string('detected_brand')->nullable();
            $table->string('detected_model')->nullable();
            $table->text('ai_description')->nullable();
            $table->json('ai_specifications')->nullable();

            // User corrections/feedback
            $table->text('user_description')->nullable();
            $table->json('user_corrections')->nullable();

            // Quality & verification
            $table->decimal('confidence_score', 3, 2)->nullable();
            $table->string('verification_status', 50)->default('pending');
            $table->text('reviewer_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();

            // Raw AI response for debugging
            $table->json('raw_response')->nullable();

            $table->timestamps();

            // Foreign key for product (UUID)
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();

            // Indexes
            $table->index('product_id');
            $table->index('verification_status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_ai_analyses');
    }
};
