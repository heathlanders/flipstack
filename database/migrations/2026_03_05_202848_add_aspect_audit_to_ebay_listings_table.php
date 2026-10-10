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
        Schema::table('ebay_listings', function (Blueprint $table) {
            $table->jsonb('aspect_audit')->nullable()->after('last_sync_payload');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ebay_listings', function (Blueprint $table) {
            $table->dropColumn('aspect_audit');
        });
    }
};
