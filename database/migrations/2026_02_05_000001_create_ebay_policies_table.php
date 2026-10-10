<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ebay_policies')) {
            return;
        }

        Schema::create('ebay_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('integration_credential_id')->constrained()->cascadeOnDelete();

            // eBay's policy identifier
            $table->string('ebay_policy_id', 50)->index();

            // Policy type: fulfillment, payment, or return
            $table->enum('policy_type', ['fulfillment', 'payment', 'return']);

            // Policy details
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('marketplace_id', 20)->default('EBAY_US');

            // Full policy configuration from eBay API (JSONB for PostgreSQL)
            $table->jsonb('policy_data')->nullable();

            // Flags
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            // Sync tracking
            $table->timestamp('last_synced_at')->nullable();

            $table->timestamps();

            // Unique constraint: one policy ID per credential
            $table->unique(['integration_credential_id', 'ebay_policy_id'], 'ebay_policies_credential_policy_unique');

            // Index for quick lookups by type
            $table->index(['integration_credential_id', 'policy_type', 'is_active'], 'ebay_policies_type_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebay_policies');
    }
};
