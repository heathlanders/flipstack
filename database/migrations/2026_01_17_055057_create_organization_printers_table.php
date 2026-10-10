<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('organization_printers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('printer_type', 20); // network, usb, cups
            $table->string('connection_string');
            $table->string('driver', 30); // zebra_zpl, dymo_label, brother_ptouch, generic_escp, pdf
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedBigInteger('default_template_id')->nullable();
            $table->json('print_settings')->nullable();
            $table->string('status', 20)->default('offline'); // online, offline, error
            $table->timestamp('last_used_at')->nullable();
            $table->integer('total_jobs')->default(0);
            $table->integer('successful_jobs')->default(0);
            $table->integer('failed_jobs')->default(0);
            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
            $table->index(['organization_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_printers');
    }
};
