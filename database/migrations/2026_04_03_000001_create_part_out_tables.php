<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('part_out_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('organization_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->uuid('source_product_id')->nullable();
            $table->string('cost_allocation_method', 20)->default('market_value');
            $table->boolean('is_active')->default(true);
            $table->integer('times_used')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('source_product_id')->references('id')->on('products')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->unique(['organization_id', 'name']);
        });

        Schema::create('part_out_template_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('template_id');
            $table->uuid('component_product_id')->nullable();
            $table->string('name');
            $table->integer('expected_quantity')->default(1);
            $table->decimal('cost_allocation_pct', 5, 2)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_optional')->default(false);
            $table->string('default_condition', 30)->nullable();
            $table->string('part_number', 100)->nullable();
            $table->string('manufacturer', 100)->nullable();
            $table->string('category', 50)->nullable();
            $table->decimal('suggested_selling_price', 10, 2)->nullable();
            $table->timestamp('market_price_updated_at')->nullable();
            $table->timestamps();

            $table->foreign('template_id')->references('id')->on('part_out_templates')->onDelete('cascade');
            $table->foreign('component_product_id')->references('id')->on('products')->onDelete('set null');
            $table->index(['template_id']);
        });

        Schema::create('part_out_work_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('organization_id');
            $table->uuid('template_id')->nullable();
            $table->string('work_order_number', 50)->unique();
            $table->uuid('source_product_id');
            $table->integer('source_quantity')->default(1);
            $table->decimal('source_unit_cost', 10, 2);
            $table->decimal('source_total_cost', 10, 2);
            $table->decimal('remaining_parent_cost', 10, 2)->nullable();
            $table->string('cost_allocation_method', 20);
            $table->uuid('location_id')->nullable();
            $table->string('status', 20)->default('draft');
            $table->boolean('parent_continues')->default(false);
            $table->string('parent_new_name', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('template_id')->references('id')->on('part_out_templates')->onDelete('set null');
            $table->foreign('source_product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('location_id')->references('id')->on('inventory_locations')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('completed_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['organization_id']);
            $table->index(['source_product_id']);
            $table->index(['status']);
        });

        Schema::create('part_out_work_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('work_order_id');
            $table->uuid('template_item_id')->nullable();
            $table->uuid('component_product_id')->nullable();
            $table->string('name');
            $table->string('part_number', 100)->nullable();
            $table->string('manufacturer', 100)->nullable();
            $table->string('category', 50)->nullable();
            $table->integer('quantity');
            $table->decimal('allocated_cost', 10, 2)->default(0);
            $table->decimal('allocated_cost_per_unit', 10, 2)->default(0);
            $table->decimal('cost_allocation_pct', 5, 2)->default(0);
            $table->decimal('suggested_selling_price', 10, 2)->nullable();
            $table->string('condition', 30)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('work_order_id')->references('id')->on('part_out_work_orders')->onDelete('cascade');
            $table->foreign('template_item_id')->references('id')->on('part_out_template_items')->onDelete('set null');
            $table->foreign('component_product_id')->references('id')->on('products')->onDelete('set null');
            $table->index(['work_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('part_out_work_order_items');
        Schema::dropIfExists('part_out_work_orders');
        Schema::dropIfExists('part_out_template_items');
        Schema::dropIfExists('part_out_templates');
    }
};
