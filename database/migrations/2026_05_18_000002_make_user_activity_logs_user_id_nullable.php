<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_activity_logs', function (Blueprint $table) {
            // user_id was NOT NULL from the original Jetstream migration. Webhook handlers, cron jobs,
            // failed-login events, and any non-authenticated mutations have no actor, so the column
            // must allow NULL or the audit insert breaks the host operation.
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('user_activity_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
