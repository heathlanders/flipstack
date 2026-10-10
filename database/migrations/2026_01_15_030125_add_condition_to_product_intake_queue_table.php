<?php

// database/migrations/[timestamp]_add_condition_to_product_intake_queue_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('product_intake_queue', function (Blueprint $table) {
            $table->enum('condition', [
                'NEW',
                'NEW_OTHER',
                'NEW_WITH_DEFECTS',
                'MANUFACTURER_REFURBISHED',
                'CERTIFIED_REFURBISHED',
                'USED_EXCELLENT',
                'USED_VERY_GOOD',
                'USED_GOOD',
                'USED_ACCEPTABLE',
                'FOR_PARTS_OR_NOT_WORKING',
            ])->default('NEW')->after('brand_id');

            $table->text('condition_description')->nullable()->after('condition');
        });
    }

    public function down()
    {
        Schema::table('product_intake_queue', function (Blueprint $table) {
            $table->dropColumn(['condition', 'condition_description']);
        });
    }
};
