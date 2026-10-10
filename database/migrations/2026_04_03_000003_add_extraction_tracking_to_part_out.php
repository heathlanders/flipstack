<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('part_out_work_order_items', function (Blueprint $table) {
            $table->string('extraction_status', 20)->default('pending')->after('notes');
            $table->timestamp('extracted_at')->nullable()->after('extraction_status');
            $table->unsignedBigInteger('extracted_by')->nullable()->after('extracted_at');
            $table->text('extraction_notes')->nullable()->after('extracted_by');
            $table->boolean('barcode_verified')->default(false)->after('extraction_notes');
            $table->string('scanned_barcode', 100)->nullable()->after('barcode_verified');

            $table->foreign('extracted_by')->references('id')->on('users')->onDelete('set null');
            $table->index('extraction_status');
        });

        Schema::table('part_out_work_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('assigned_to')->nullable()->after('completed_by');

            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
            $table->index('assigned_to');
        });
    }

    public function down(): void
    {
        Schema::table('part_out_work_order_items', function (Blueprint $table) {
            $table->dropForeign(['extracted_by']);
            $table->dropIndex(['extraction_status']);
            $table->dropColumn([
                'extraction_status',
                'extracted_at',
                'extracted_by',
                'extraction_notes',
                'barcode_verified',
                'scanned_barcode',
            ]);
        });

        Schema::table('part_out_work_orders', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
            $table->dropIndex(['assigned_to']);
            $table->dropColumn('assigned_to');
        });
    }
};
