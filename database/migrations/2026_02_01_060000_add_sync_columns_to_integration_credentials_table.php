<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_credentials', function (Blueprint $table) {
            $table->boolean('auto_sync_enabled')->default(false);
            $table->integer('sync_interval_minutes')->nullable();
            $table->timestamp('last_full_sync_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('integration_credentials', function (Blueprint $table) {
            $table->dropColumn(['auto_sync_enabled', 'sync_interval_minutes', 'last_full_sync_at']);
        });
    }
};
