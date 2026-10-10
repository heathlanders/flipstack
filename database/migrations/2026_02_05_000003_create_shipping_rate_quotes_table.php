<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shipping_rate_quotes')) {
            return;
        }

        Schema::create('shipping_rate_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // Link to queue item (bigint)
            $table->unsignedBigInteger('queue_item_id')->nullable()->index();
            $table->foreign('queue_item_id')
                ->references('id')
                ->on('product_intake_queue')
                ->nullOnDelete();

            // Optional link to product (after queue item is converted)
            $table->uuid('product_id')->nullable()->index();
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->nullOnDelete();

            // Carrier and service details
            $table->string('carrier', 50);
            $table->string('service', 100);
            $table->string('service_code', 50)->nullable();

            // Rate information
            $table->decimal('rate', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->integer('estimated_days')->nullable();
            $table->boolean('is_guaranteed')->default(false);

            // Package dimensions used for quote
            $table->decimal('weight_lbs', 8, 2)->nullable();
            $table->decimal('length_inches', 8, 2)->nullable();
            $table->decimal('width_inches', 8, 2)->nullable();
            $table->decimal('height_inches', 8, 2)->nullable();

            // Shipping addresses (postal codes only for privacy)
            $table->string('from_postal_code', 20)->nullable();
            $table->string('to_postal_code', 20)->nullable();

            // Insurance and confirmation options
            $table->boolean('insurance_included')->default(false);
            $table->decimal('insurance_cost', 8, 2)->nullable();
            $table->decimal('insured_value', 10, 2)->nullable();
            $table->string('confirmation_type', 20)->default('none');
            $table->decimal('additional_item_cost', 8, 2)->nullable();

            // Provider that returned this quote
            $table->string('provider', 20)->default('shipstation');

            // Quote validity (rates typically expire in ~1 hour)
            $table->timestamp('expires_at');

            $table->timestamps();

            // Index for finding valid quotes
            $table->index(['queue_item_id', 'expires_at'], 'shipping_rate_quotes_valid');
            $table->index(['product_id', 'expires_at'], 'shipping_rate_quotes_product_valid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rate_quotes');
    }
};
