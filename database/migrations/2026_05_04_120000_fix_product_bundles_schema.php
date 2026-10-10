<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_bundles') && ! Schema::hasColumn('product_bundles', 'organization_id')) {
            Schema::dropIfExists('product_bundle_items');
            Schema::dropIfExists('product_bundles');
        }

        if (! Schema::hasTable('product_bundles')) {
            Schema::create('product_bundles', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->unsignedBigInteger('organization_id');
                $table->uuid('product_id')->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('bundle_type', 20)->default('fixed');
                $table->integer('pick_n_quantity')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
                $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
                $table->index(['organization_id']);
            });
        }

        if (! Schema::hasTable('product_bundle_items')) {
            Schema::create('product_bundle_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('bundle_id');
                $table->uuid('component_product_id');
                $table->integer('quantity')->default(1);
                $table->integer('sort_order')->default(0);
                $table->boolean('is_required')->default(true);
                $table->timestamps();

                $table->foreign('bundle_id')->references('id')->on('product_bundles')->onDelete('cascade');
                $table->foreign('component_product_id')->references('id')->on('products')->onDelete('cascade');
                $table->index(['bundle_id']);
            });
        }
    }

    public function down(): void
    {
        // No-op: forward-only fix. Rolling back would drop the working table.
    }
};
