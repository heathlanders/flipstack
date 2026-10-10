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
        Schema::create('supplier_payment_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('supplier_id');
            $table->unsignedBigInteger('organization_id');

            // Payment Method Type
            $table->enum('type', [
                'ach',
                'check',
                'paypal',
                'wire',
                'venmo',
                'zelle',
                'other',
            ]);

            $table->string('nickname', 100)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_verified')->default(false);

            // Bank Account (encrypted storage recommended)
            $table->string('bank_name', 255)->nullable();
            $table->text('account_holder_name')->nullable();
            $table->text('account_number_encrypted')->nullable();
            $table->string('account_number_last4', 4)->nullable();
            $table->text('routing_number_encrypted')->nullable();
            $table->enum('account_type', ['checking', 'savings'])->nullable();

            // PayPal/Venmo/Zelle
            $table->string('email_address', 255)->nullable();
            $table->string('phone_number', 20)->nullable();
            $table->string('username', 100)->nullable();

            // Check Mailing
            $table->string('payee_name', 255)->nullable();
            $table->text('mailing_address')->nullable();

            // Wire Transfer
            $table->string('swift_code', 11)->nullable();
            $table->string('iban', 34)->nullable();
            $table->text('bank_address')->nullable();
            $table->text('intermediary_bank')->nullable();

            // Verification
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->string('verification_method', 50)->nullable();

            // Status
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('supplier_id')
                ->references('id')
                ->on('suppliers')
                ->onDelete('cascade');

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->foreign('verified_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index(['supplier_id', 'is_default']);
            $table->index(['supplier_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_payment_methods');
    }
};
