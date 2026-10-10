<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('integration_credentials', 'auto_sync_enabled')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE integration_credentials ALTER COLUMN auto_sync_enabled SET DEFAULT true');
        }

        DB::table('integration_credentials')
            ->where('platform', 'ebay')
            ->where('is_active', true)
            ->update(['auto_sync_enabled' => true]);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql' && Schema::hasColumn('integration_credentials', 'auto_sync_enabled')) {
            DB::statement('ALTER TABLE integration_credentials ALTER COLUMN auto_sync_enabled SET DEFAULT false');
        }
    }
};
