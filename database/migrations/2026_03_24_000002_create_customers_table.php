<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('organization_id');
            $table->string('email')->nullable();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('marketplace_source', 30)->nullable();
            $table->string('marketplace_customer_id')->nullable();
            $table->timestamp('first_order_at')->nullable();
            $table->timestamp('last_order_at')->nullable();
            $table->integer('total_orders')->default(0);
            $table->decimal('total_spent', 12, 2)->default(0);
            $table->decimal('average_order_value', 12, 2)->default(0);
            $table->jsonb('tags')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_flagged')->default(false);
            $table->string('flag_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->index(['organization_id', 'email']);
            $table->index(['organization_id', 'name']);
            $table->index(['organization_id', 'last_order_at']);
            $table->index(['marketplace_source', 'marketplace_customer_id']);
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->string('type', 20)->default('shipping');
            $table->boolean('is_default')->default(false);
            $table->string('name')->nullable();
            $table->string('line1')->nullable();
            $table->string('line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 50)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 5)->default('US');
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->index(['customer_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('customer_id')->nullable()->after('organization_id');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customers');
    }
};
