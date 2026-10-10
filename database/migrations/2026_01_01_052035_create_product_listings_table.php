<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_listings', function (Blueprint $table) {
            $table->id();
            $table->uuid('product_id');
            $table->unsignedBigInteger('organization_id');

            // Platform Configuration
            $table->string('platform', 50); // 'ebay', 'amazon', 'shopify', etc.
            $table->string('marketplace', 50)->nullable(); // 'US', 'UK', 'DE', etc.
            $table->string('listing_id')->nullable(); // Platform's listing ID (after publish)
            $table->string('sku')->nullable(); // Platform-specific SKU

            // Listing Status
            $table->enum('status', ['draft', 'pending', 'active', 'ended', 'sold', 'error'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('ended_at')->nullable();

            // Title & Description
            $table->string('title', 80); // eBay max 80 chars
            $table->string('subtitle', 55)->nullable(); // eBay max 55 chars
            $table->text('description'); // Rich HTML description
            $table->json('features')->nullable(); // Bullet points array

            // Pricing
            $table->decimal('price', 15, 2);
            $table->decimal('compare_at_price', 15, 2)->nullable(); // Original/MSRP price
            $table->decimal('cost', 15, 2)->nullable();
            $table->string('currency', 3)->default('USD');

            // Inventory
            $table->integer('quantity')->default(1);
            $table->boolean('auto_renew')->default(true);
            $table->integer('duration_days')->nullable(); // eBay listing duration

            // Condition
            $table->string('condition', 50); // 'new', 'like_new', 'very_good', etc.
            $table->text('condition_description')->nullable();

            // Shipping
            $table->decimal('shipping_weight_oz', 10, 2)->nullable();
            $table->decimal('package_length_in', 10, 2)->nullable();
            $table->decimal('package_width_in', 10, 2)->nullable();
            $table->decimal('package_height_in', 10, 2)->nullable();
            $table->integer('handling_time_days')->default(1);
            $table->json('shipping_services')->nullable(); // Shipping options
            $table->decimal('shipping_cost', 10, 2)->nullable();
            $table->boolean('free_shipping')->default(false);

            // Returns
            $table->boolean('returns_accepted')->default(true);
            $table->integer('return_period_days')->default(30);
            $table->string('return_shipping_paid_by', 20)->default('buyer'); // 'buyer' or 'seller'

            // Payment
            $table->json('payment_methods')->nullable(); // ['paypal', 'credit_card', etc.]

            // SEO & Marketing
            $table->json('tags')->nullable();
            $table->boolean('promoted')->default(false);
            $table->decimal('promotion_rate', 5, 2)->nullable(); // Ad rate percentage

            // Platform-Specific Data
            $table->json('platform_data')->nullable(); // Store any platform-specific fields

            // Sync Status
            $table->timestamp('last_synced_at')->nullable();
            $table->json('sync_errors')->nullable();
            $table->boolean('auto_sync')->default(true);

            // Templates
            $table->unsignedBigInteger('template_id')->nullable();

            // Audit
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();

            // Indexes
            $table->index(['product_id', 'platform']);
            $table->index(['organization_id', 'status']);
            $table->index('listing_id');
            $table->index('status');
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_listings');
    }
};
