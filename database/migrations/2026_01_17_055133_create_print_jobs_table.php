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
        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('printer_id')->constrained('organization_printers')->cascadeOnDelete();
            $table->foreignId('template_id')->constrained('label_templates')->cascadeOnDelete();
            $table->uuid('product_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('job_number', 30)->unique();
            $table->string('job_type', 20)->default('on_demand'); // auto_sku, on_demand, batch
            $table->string('status', 20)->default('pending'); // pending, processing, completed, failed, cancelled
            $table->string('priority', 10)->default('normal'); // low, normal, high, urgent
            $table->integer('quantity')->default(1);
            $table->integer('labels_printed')->default(0);
            $table->json('label_data')->nullable();
            $table->json('print_settings')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'created_at']);
            $table->index('printer_id');
        });

        // Add FK for default_template_id now that label_templates exists
        Schema::table('organization_printers', function (Blueprint $table) {
            $table->foreign('default_template_id')->references('id')->on('label_templates')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_printers', function (Blueprint $table) {
            $table->dropForeign(['default_template_id']);
        });
        Schema::dropIfExists('print_jobs');
    }
};
