<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('organization_id');
            $table->string('name', 255);
            $table->string('type', 50);
            $table->string('trigger_event', 50);
            $table->integer('trigger_delay_hours')->default(24);
            $table->string('subject_template', 500);
            $table->text('body_template');
            $table->boolean('is_active')->default(false);
            $table->integer('sent_count')->default(0);
            $table->integer('open_count')->default(0);
            $table->integer('click_count')->default(0);
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->index(['organization_id', 'is_active']);
        });

        Schema::create('email_campaign_sends', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('campaign_id');
            $table->uuid('customer_id')->nullable();
            $table->uuid('order_id')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamps();

            $table->foreign('campaign_id')->references('id')->on('email_campaigns')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            $table->index(['campaign_id', 'status']);
            $table->index(['customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_campaign_sends');
        Schema::dropIfExists('email_campaigns');
    }
};
