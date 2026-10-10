<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('labeling_work_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('organization_id');
            $table->string('work_order_number', 50)->unique();
            $table->string('source_type', 30); // po_receipt, intake_queue, csv_import, part_out, manual
            $table->uuid('source_id')->nullable();
            $table->uuid('location_id')->nullable();
            $table->string('status', 20)->default('open'); // open, in_progress, completed, partially_completed
            $table->string('priority', 10)->default('standard'); // urgent, high, standard
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->text('notes')->nullable();
            $table->integer('total_items')->default(0);
            $table->integer('labeled_items')->default(0);
            $table->integer('shelved_items')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('location_id')->references('id')->on('inventory_locations')->onDelete('set null');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'source_type']);
            $table->index('assigned_to');
        });

        Schema::create('labeling_work_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('work_order_id');
            $table->uuid('product_id');
            $table->uuid('product_variation_id')->nullable();
            $table->integer('quantity')->default(1);
            $table->string('status', 20)->default('pending'); // pending, label_printed, shelved, skipped
            $table->uuid('bin_id')->nullable();
            $table->timestamp('label_printed_at')->nullable();
            $table->unsignedBigInteger('labeled_by')->nullable();
            $table->timestamp('shelved_at')->nullable();
            $table->unsignedBigInteger('shelved_by')->nullable();
            $table->boolean('bin_verified')->default(false);
            $table->string('skip_reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('work_order_id')->references('id')->on('labeling_work_orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('product_variation_id')->references('id')->on('product_variations')->onDelete('cascade');
            $table->foreign('bin_id')->references('id')->on('warehouse_bins')->onDelete('set null');
            $table->foreign('labeled_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('shelved_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['work_order_id', 'status']);
            $table->index('product_id');
            $table->index('bin_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('labeling_work_order_items');
        Schema::dropIfExists('labeling_work_orders');
    }
};
