<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_locations', function (Blueprint $table) {
            $table->string('easypost_address_id')->nullable()->after('is_default');
            $table->boolean('is_ship_from')->default(false)->after('easypost_address_id');
            $table->boolean('ship_from_verified')->default(false)->after('is_ship_from');
            // Default package dimensions for this location
            $table->decimal('default_weight_oz', 8, 2)->nullable()->after('ship_from_verified');
            $table->decimal('default_length', 6, 2)->nullable()->after('default_weight_oz');
            $table->decimal('default_width', 6, 2)->nullable()->after('default_length');
            $table->decimal('default_height', 6, 2)->nullable()->after('default_width');
            // Printer assignments for automated printing
            $table->unsignedBigInteger('shipping_label_printer_id')->nullable()->after('default_height');
            $table->unsignedBigInteger('packing_slip_printer_id')->nullable()->after('shipping_label_printer_id');
            $table->unsignedBigInteger('pick_list_printer_id')->nullable()->after('packing_slip_printer_id');
            $table->boolean('auto_print_shipping_label')->default(false)->after('pick_list_printer_id');
            $table->boolean('auto_print_packing_slip')->default(false)->after('auto_print_shipping_label');
            $table->boolean('auto_print_pick_list')->default(false)->after('auto_print_packing_slip');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_locations', function (Blueprint $table) {
            $table->dropColumn([
                'easypost_address_id',
                'is_ship_from',
                'ship_from_verified',
                'default_weight_oz',
                'default_length',
                'default_width',
                'default_height',
                'shipping_label_printer_id',
                'packing_slip_printer_id',
                'pick_list_printer_id',
                'auto_print_shipping_label',
                'auto_print_packing_slip',
                'auto_print_pick_list',
            ]);
        });
    }
};
