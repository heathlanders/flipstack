<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('product_listings')->cascadeOnDelete();

            // Category Information
            $table->string('platform', 50); // 'ebay', 'amazon', etc.
            $table->string('category_id'); // Platform's category ID
            $table->string('category_name');
            $table->string('category_path')->nullable(); // Full path: "Electronics > Printers > Receipt Printers"

            // Priority
            $table->boolean('is_primary')->default(false); // Primary category
            $table->integer('sort_order')->default(0);

            // Category Metadata
            $table->json('required_specifics')->nullable(); // What item specifics this category requires
            $table->json('recommended_specifics')->nullable();
            $table->decimal('category_fee_percentage', 5, 2)->nullable(); // Platform fees for this category

            $table->timestamps();

            // Indexes
            $table->index(['listing_id', 'platform']);
            $table->index(['platform', 'category_id']);
            $table->index('is_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_categories');
    }
};
