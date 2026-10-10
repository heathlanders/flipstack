<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('product_intake_queue', function (Blueprint $table) {
            // Add organization_id as nullable first
            if (! Schema::hasColumn('product_intake_queue', 'organization_id')) {
                $table->unsignedBigInteger('organization_id')->nullable()->after('id');
            }

            // Add ebay_listing_id field
            if (! Schema::hasColumn('product_intake_queue', 'ebay_listing_id')) {
                $table->string('ebay_listing_id')->nullable()->after('condition_description');
            }
        });

        // Update existing records with a default organization_id
        // Get the first organization ID that exists
        $firstOrgId = DB::table('organizations')->value('id');

        if ($firstOrgId) {
            DB::table('product_intake_queue')
                ->whereNull('organization_id')
                ->update(['organization_id' => $firstOrgId]);
        }

        // Now make organization_id NOT NULL and add foreign key
        Schema::table('product_intake_queue', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable(false)->change();
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('product_intake_queue', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn(['organization_id', 'ebay_listing_id']);
        });
    }
};
