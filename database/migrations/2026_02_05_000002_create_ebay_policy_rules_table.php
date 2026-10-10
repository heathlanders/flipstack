<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ebay_policy_rules')) {
            return;
        }

        Schema::create('ebay_policy_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('integration_credential_id')->constrained()->cascadeOnDelete();

            // Rule identification
            $table->string('name');
            $table->text('description')->nullable();

            // Priority: higher number = evaluated first
            $table->integer('priority')->default(0);

            // Conditions as JSONB array
            // Format: [{"field": "price", "operator": ">=", "value": 100}, ...]
            $table->jsonb('conditions')->default('[]');

            // How to combine conditions: AND (all must match) or OR (any must match)
            $table->enum('condition_logic', ['AND', 'OR'])->default('AND');

            // Policy selections (nullable FKs to ebay_policies)
            $table->foreignId('fulfillment_policy_id')
                ->nullable()
                ->constrained('ebay_policies')
                ->nullOnDelete();

            $table->foreignId('payment_policy_id')
                ->nullable()
                ->constrained('ebay_policies')
                ->nullOnDelete();

            $table->foreignId('return_policy_id')
                ->nullable()
                ->constrained('ebay_policies')
                ->nullOnDelete();

            // Active flag
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Index for rule evaluation (active rules ordered by priority)
            $table->index(['integration_credential_id', 'is_active', 'priority'], 'ebay_policy_rules_evaluation');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebay_policy_rules');
    }
};
