<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('sku_prefix', 10)->nullable()->unique()->after('subdomain');
            $table->integer('sku_counter')->default(0)->after('sku_prefix');
            $table->boolean('sku_prefix_locked')->default(false)->after('sku_counter');
            $table->timestamp('sku_prefix_set_at')->nullable()->after('sku_prefix_locked');
            $table->bigInteger('sku_prefix_set_by')->nullable()->after('sku_prefix_set_at');

            // Index for faster lookups
            $table->index(['sku_prefix_locked', 'sku_prefix']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'sku_prefix',
                'sku_counter',
                'sku_prefix_locked',
                'sku_prefix_set_at',
                'sku_prefix_set_by',
            ]);
        });
    }
};
