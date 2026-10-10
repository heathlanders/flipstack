<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_credentials', function (Blueprint $table) {
            if (! Schema::hasColumn('integration_credentials', 'ebay_site_id')) {
                $table->string('ebay_site_id')->nullable()->after('platform');
            }
            if (! Schema::hasColumn('integration_credentials', 'ebay_marketplace_id')) {
                $table->string('ebay_marketplace_id')->nullable()->after('ebay_site_id');
            }
            if (! Schema::hasColumn('integration_credentials', 'ebay_environment')) {
                $table->string('ebay_environment')->default('sandbox')->after('ebay_marketplace_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('integration_credentials', function (Blueprint $table) {
            $table->dropColumn(['ebay_site_id', 'ebay_marketplace_id', 'ebay_environment']);
        });
    }
};
