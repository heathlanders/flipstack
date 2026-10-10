<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('inventory_locations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->unsignedBigInteger('organization_id'); // Changed from uuid to bigint
            $table->string('name');
            $table->string('code', 50);
            $table->enum('type', ['warehouse', 'store', 'supplier', 'customer', 'virtual'])->default('warehouse');

            // Address
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 100)->nullable();

            // Contact
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);

            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('inventory_locations');
    }
};
