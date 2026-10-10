<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ebay_categories', function (Blueprint $table) {
            $table->id();
            $table->string('category_id', 20)->unique()->index(); // eBay's category ID
            $table->string('name'); // Category name
            $table->string('parent_id', 20)->nullable()->index(); // Parent category ID
            $table->integer('level')->default(1); // Tree level (1 = root)
            $table->boolean('is_leaf')->default(false)->index(); // Can list items in this category
            $table->text('category_path')->nullable(); // Full path like "Electronics > Computers > Memory"
            $table->text('keywords')->nullable(); // Searchable keywords
            $table->integer('item_count')->nullable(); // Number of items in category (from eBay)
            $table->json('metadata')->nullable(); // Additional eBay data
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['parent_id', 'is_leaf']);
            $table->index(['is_leaf', 'item_count']);
            $table->fullText(['name', 'keywords', 'category_path']); // For search
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebay_categories');
    }
};
