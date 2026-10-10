<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_zones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('organization_id');
            $table->uuid('location_id');
            $table->string('name');
            $table->string('code', 10);
            $table->string('type', 30)->default('storage');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('location_id')->references('id')->on('inventory_locations')->onDelete('cascade');
            $table->index(['organization_id', 'location_id']);
            $table->unique(['location_id', 'code']);
        });

        Schema::create('warehouse_bins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('zone_id');
            $table->string('code', 30);
            $table->string('label')->nullable();
            $table->string('aisle', 10)->nullable();
            $table->string('rack', 10)->nullable();
            $table->string('shelf', 10)->nullable();
            $table->string('bin_position', 10)->nullable();
            $table->integer('max_capacity')->nullable();
            $table->integer('current_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('barcode')->nullable();
            $table->timestamps();

            $table->foreign('zone_id')->references('id')->on('warehouse_zones')->onDelete('cascade');
            $table->unique(['zone_id', 'code']);
            $table->index(['barcode']);
        });

        Schema::table('inventory_levels', function (Blueprint $table) {
            $table->uuid('bin_id')->nullable()->after('location_id');
            $table->foreign('bin_id')->references('id')->on('warehouse_bins')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_levels', function (Blueprint $table) {
            $table->dropForeign(['bin_id']);
            $table->dropColumn('bin_id');
        });
        Schema::dropIfExists('warehouse_bins');
        Schema::dropIfExists('warehouse_zones');
    }
};
