<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ebay_promoted_campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->unsignedBigInteger('integration_credential_id');
            $table->foreign('integration_credential_id')
                ->references('id')
                ->on('integration_credentials')
                ->onDelete('cascade');

            $table->string('ebay_campaign_id', 100)->nullable()->unique();
            $table->string('name', 255);
            $table->string('status', 20)->default('active');
            $table->string('campaign_type', 20)->default('standard');
            $table->decimal('budget_daily', 12, 2)->nullable();
            $table->decimal('budget_total', 12, 2)->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('ebay_promoted_listings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('campaign_id');
            $table->foreign('campaign_id')
                ->references('id')
                ->on('ebay_promoted_campaigns')
                ->onDelete('cascade');

            $table->unsignedBigInteger('ebay_listing_id')->nullable();
            $table->foreign('ebay_listing_id')
                ->references('id')
                ->on('ebay_listings')
                ->onDelete('set null');

            $table->uuid('product_id')->nullable();
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('set null');

            $table->decimal('ad_rate_percentage', 5, 2)->default(0);
            $table->string('status', 20)->default('active');
            $table->integer('impressions')->default(0);
            $table->integer('clicks')->default(0);
            $table->integer('sales_count')->default(0);
            $table->decimal('sales_amount', 12, 2)->default(0);
            $table->decimal('ad_fees', 12, 2)->default(0);
            $table->decimal('roas', 8, 2)->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebay_promoted_listings');
        Schema::dropIfExists('ebay_promoted_campaigns');
    }
};
