<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_item_specifics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('product_listings')->cascadeOnDelete();

            // Item Specific Key-Value
            $table->string('name'); // e.g., "Brand", "Model", "Color", "Size"
            $table->text('value'); // e.g., "Epson", "Mobilink P20", "Black", "Large"

            // Metadata
            $table->string('source', 50)->default('manual'); // 'manual', 'ai', 'template', 'api'
            $table->boolean('required')->default(false); // Is this required by platform?
            $table->integer('sort_order')->default(0);

            // Platform Specific
            $table->string('platform_field_id')->nullable(); // eBay's aspect ID, Amazon's attribute ID
            $table->json('allowed_values')->nullable(); // Dropdown options if restricted

            $table->timestamps();

            // Indexes
            $table->index(['listing_id', 'name']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_item_specifics');
    }
};
