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
        Schema::create('ai_prompt_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('prompt_id');
            $table->foreign('prompt_id')->references('id')->on('ai_category_prompts')->onDelete('cascade');

            // Change Tracking
            $table->string('action', 20);
            $table->longText('previous_content')->nullable();
            $table->longText('new_content')->nullable();
            $table->json('changed_fields')->nullable();

            // Audit
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('change_reason')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['prompt_id', 'created_at']);
            $table->index('changed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_prompt_history');
    }
};
