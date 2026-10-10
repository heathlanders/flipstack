<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_listings', function (Blueprint $table) {
            $table->foreign('template_id')
                ->references('id')
                ->on('listing_templates')
                ->nullOnDelete();

            $table->index('template_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_listings', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
            $table->dropIndex(['template_id']);
        });
    }
};
