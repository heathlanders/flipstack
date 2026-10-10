<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('warehouse_location_id')->nullable()->constrained('warehouse_locations')->onDelete('set null');
            $table->boolean('auto_print_labels')->default(true);
            $table->integer('labels_printed_count')->default(0);
            $table->timestamp('last_label_printed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['warehouse_location_id']);
            $table->dropColumn([
                'warehouse_location_id',
                'auto_print_labels',
                'labels_printed_count',
                'last_label_printed_at',
            ]);
        });
    }
};
