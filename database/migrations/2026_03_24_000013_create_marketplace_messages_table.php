<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('organization_id');
            $table->uuid('customer_id')->nullable();
            $table->uuid('order_id')->nullable();
            $table->string('marketplace', 20);
            $table->string('marketplace_message_id', 255)->nullable();
            $table->string('marketplace_thread_id', 255)->nullable();
            $table->string('direction', 10);
            $table->string('subject', 500)->nullable();
            $table->text('body');
            $table->string('sender_name', 255)->nullable();
            $table->string('sender_id', 255)->nullable();
            $table->string('status', 20)->default('unread');
            $table->timestamp('replied_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();

            $table->index(['organization_id', 'status']);
            $table->index(['marketplace_thread_id']);
        });

        Schema::create('marketplace_message_replies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('message_id');
            $table->text('body');
            $table->unsignedBigInteger('sent_by')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('marketplace_response_id', 255)->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();

            $table->foreign('message_id')->references('id')->on('marketplace_messages')->cascadeOnDelete();
            $table->foreign('sent_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_message_replies');
        Schema::dropIfExists('marketplace_messages');
    }
};
