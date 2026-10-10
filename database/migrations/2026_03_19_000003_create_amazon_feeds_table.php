<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amazon_feeds', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('integration_credential_id');
            $table->foreign('integration_credential_id')
                ->references('id')
                ->on('integration_credentials')
                ->onDelete('cascade');

            $table->unsignedBigInteger('team_id');
            $table->foreign('team_id')
                ->references('id')
                ->on('teams')
                ->onDelete('cascade');

            $table->string('feed_id', 50)->unique();
            $table->string('feed_type', 50); // JSON_LISTINGS_FEED, POST_INVENTORY_AVAILABILITY_DATA, etc.
            $table->string('status', 30)->default('submitted'); // submitted, in_progress, done, cancelled, fatal

            // Record counts
            $table->integer('total_records')->default(0);
            $table->integer('processed_records')->default(0);
            $table->integer('successful_records')->default(0);
            $table->integer('failed_records')->default(0);

            // Results
            $table->jsonb('error_summary')->nullable();
            $table->string('result_document_id', 50)->nullable();

            // User who initiated
            $table->unsignedBigInteger('initiated_by')->nullable();
            $table->foreign('initiated_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->timestamp('submitted_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['integration_credential_id', 'status'], 'amazon_feeds_credential_status_idx');
            $table->index(['status', 'created_at'], 'amazon_feeds_status_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amazon_feeds');
    }
};
