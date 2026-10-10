<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_productivity_daily', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('user_id');
            $table->date('performance_date');

            // Catalog throughput
            $table->unsignedInteger('products_listed_count')->default(0);

            // Procurement throughput
            $table->unsignedInteger('pos_created_count')->default(0);
            $table->unsignedInteger('pos_approved_count')->default(0);
            $table->unsignedInteger('pos_received_count')->default(0);

            // Communication throughput
            $table->unsignedInteger('messages_answered_count')->default(0);
            $table->unsignedInteger('avg_response_seconds')->nullable();

            // Inventory throughput
            $table->unsignedInteger('inventory_moves_count')->default(0);

            // Marketplace publish throughput
            $table->unsignedInteger('listings_published_ebay')->default(0);
            $table->unsignedInteger('listings_published_shopify')->default(0);
            $table->unsignedInteger('listings_published_amazon')->default(0);

            // Customer + pricing throughput
            $table->unsignedInteger('customer_records_touched')->default(0);
            $table->unsignedInteger('pricing_rules_changed')->default(0);

            $table->timestamps();

            $table->unique(['organization_id', 'user_id', 'performance_date'], 'user_productivity_daily_org_user_date_unique');
            $table->index(['organization_id', 'performance_date']);
            $table->index(['user_id', 'performance_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_productivity_daily');
    }
};
