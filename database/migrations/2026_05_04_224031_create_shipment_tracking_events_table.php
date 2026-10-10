<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_tracking_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('status', 64);
            $table->string('description', 500)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 50)->nullable();
            $table->string('source', 32);
            $table->string('source_event_id', 128)->nullable();
            $table->timestamp('occurred_at');
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->index(['shipment_id', 'occurred_at']);
            $table->unique(['shipment_id', 'source', 'source_event_id'], 'sh_track_unique_source_event');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->timestamp('last_tracking_synced_at')->nullable()->after('actual_delivery_date');
            $table->string('tracking_provider', 32)->nullable()->after('last_tracking_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['last_tracking_synced_at', 'tracking_provider']);
        });
        Schema::dropIfExists('shipment_tracking_events');
    }
};
