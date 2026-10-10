<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repricing_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('organization_id');
            $table->string('name');
            $table->string('marketplace', 30)->default('*');
            $table->string('scope_type', 20)->default('all');
            $table->jsonb('scope_ids')->nullable();
            $table->string('strategy', 30);
            $table->jsonb('parameters')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->index(['organization_id', 'is_active', 'priority']);
        });

        Schema::create('repricing_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('repricing_rule_id');
            $table->uuid('product_id');
            $table->string('listing_type', 50)->nullable();
            $table->uuid('listing_id')->nullable();
            $table->decimal('old_price', 12, 2);
            $table->decimal('new_price', 12, 2);
            $table->decimal('competitor_price', 12, 2)->nullable();
            $table->string('reason')->nullable();
            $table->timestamp('applied_at');
            $table->timestamps();

            $table->foreign('repricing_rule_id')->references('id')->on('repricing_rules')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->index(['repricing_rule_id', 'applied_at']);
            $table->index(['product_id', 'applied_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repricing_logs');
        Schema::dropIfExists('repricing_rules');
    }
};
