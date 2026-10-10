<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');

            // Template Info
            $table->string('name'); // "Electronics - New Items", "Used Tools - eBay"
            $table->text('description')->nullable();
            $table->string('platform', 50); // 'ebay', 'amazon', 'all'

            // Default Values
            $table->json('default_item_specifics')->nullable(); // Pre-fill common specifics
            $table->json('default_categories')->nullable(); // Default category mappings
            $table->text('description_template')->nullable(); // HTML template with placeholders
            $table->json('shipping_template')->nullable(); // Default shipping settings
            $table->json('return_policy_template')->nullable();
            $table->json('payment_template')->nullable();

            // Pricing Rules
            $table->string('pricing_strategy', 50)->nullable(); // 'fixed_markup', 'percentage', 'competitive'
            $table->decimal('markup_percentage', 5, 2)->nullable();
            $table->decimal('fixed_markup_amount', 10, 2)->nullable();

            // Usage
            $table->integer('times_used')->default(0);
            $table->timestamp('last_used_at')->nullable();

            // Audit
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();

            // Indexes
            $table->index(['organization_id', 'platform']);
            $table->index('platform');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_templates');
    }
};
