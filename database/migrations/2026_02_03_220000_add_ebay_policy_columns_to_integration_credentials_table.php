<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_credentials', function (Blueprint $table) {
            if (! Schema::hasColumn('integration_credentials', 'fulfillment_policy_id')) {
                $table->string('fulfillment_policy_id', 50)->nullable();
            }
            if (! Schema::hasColumn('integration_credentials', 'payment_policy_id')) {
                $table->string('payment_policy_id', 50)->nullable();
            }
            if (! Schema::hasColumn('integration_credentials', 'return_policy_id')) {
                $table->string('return_policy_id', 50)->nullable();
            }
            if (! Schema::hasColumn('integration_credentials', 'merchant_location_key')) {
                $table->string('merchant_location_key', 50)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('integration_credentials', function (Blueprint $table) {
            $table->dropColumn([
                'fulfillment_policy_id',
                'payment_policy_id',
                'return_policy_id',
                'merchant_location_key',
            ]);
        });
    }
};
