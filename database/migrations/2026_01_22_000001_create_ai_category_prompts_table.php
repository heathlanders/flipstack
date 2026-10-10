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
        Schema::create('ai_category_prompts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();

            // Category and Type
            $table->string('category_slug', 100)->index();
            $table->string('prompt_type', 50)->index();
            $table->string('name', 100);
            $table->text('description')->nullable();

            // Prompt Content
            $table->longText('prompt_content');
            $table->json('variable_definitions')->nullable();

            // Status and Priority
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->integer('priority')->default(0);

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for common queries
            $table->index(['organization_id', 'category_slug', 'prompt_type']);
            $table->index(['is_active', 'is_system']);
            $table->index(['category_slug', 'prompt_type', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_category_prompts');
    }
};
