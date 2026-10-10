<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->unsignedBigInteger('organization_id'); // Changed from uuid to bigint
            $table->string('name');
            $table->string('code', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('website')->nullable();

            // Address
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 100)->nullable();

            // Business info
            $table->string('tax_id', 100)->nullable();
            $table->string('business_registration', 100)->nullable();

            // Terms
            $table->integer('payment_terms')->default(30);
            $table->string('currency', 3)->default('USD');
            $table->decimal('minimum_order_amount', 15, 2)->default(0);
            $table->integer('lead_time_days')->default(7);

            // Performance
            $table->decimal('performance_rating', 3, 2)->default(0);
            $table->integer('total_orders')->default(0);
            $table->decimal('on_time_delivery_rate', 5, 2)->default(0);

            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_preferred')->default(false);
            $table->text('notes')->nullable();
            $table->jsonb('meta_data')->default('{}');

            $table->unsignedBigInteger('created_by')->nullable(); // Changed from uuid to bigint
            $table->timestamps();

            $table->unique(['organization_id', 'code']);

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users');
        });

        // Add check constraint for performance rating
        DB::statement('ALTER TABLE suppliers ADD CONSTRAINT valid_rating CHECK (performance_rating >= 0 AND performance_rating <= 5)');
    }

    public function down()
    {
        Schema::dropIfExists('suppliers');
    }
};
