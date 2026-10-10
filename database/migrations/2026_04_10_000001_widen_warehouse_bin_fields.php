<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_zones', function (Blueprint $table) {
            $table->string('code', 20)->change();
        });

        Schema::table('warehouse_bins', function (Blueprint $table) {
            $table->string('code', 60)->change();
            $table->string('aisle', 20)->nullable()->change();
            $table->string('rack', 30)->nullable()->change();
            $table->string('bin_position', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_zones', function (Blueprint $table) {
            $table->string('code', 10)->change();
        });

        Schema::table('warehouse_bins', function (Blueprint $table) {
            $table->string('code', 30)->change();
            $table->string('aisle', 10)->nullable()->change();
            $table->string('rack', 10)->nullable()->change();
            $table->string('bin_position', 10)->nullable()->change();
        });
    }
};
