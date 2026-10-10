<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_credentials', function (Blueprint $table) {
            $table->string('seller_username')->nullable()->after('is_active');
            $table->string('marketplace_id')->nullable()->after('seller_username');
        });
    }

    public function down(): void
    {
        Schema::table('integration_credentials', function (Blueprint $table) {
            $table->dropColumn(['seller_username', 'marketplace_id']);
        });
    }
};
