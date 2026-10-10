<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('integration_credentials', function (Blueprint $table) {
            if (! Schema::hasColumn('integration_credentials', 'organization_id')) {
                $table->unsignedBigInteger('organization_id')->nullable()->after('id');
                $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            }
        });
    }

    public function down()
    {
        Schema::table('integration_credentials', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });
    }
};
