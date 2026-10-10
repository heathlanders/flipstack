<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add foreign key from ai_analyses to queue after both tables exist
        Schema::table('product_ai_analyses', function (Blueprint $table) {
            $table->foreign('queue_item_id')
                ->references('id')
                ->on('product_intake_queue')
                ->cascadeOnDelete();

            $table->index('queue_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_ai_analyses', function (Blueprint $table) {
            $table->dropForeign(['queue_item_id']);
            $table->dropIndex(['queue_item_id']);
        });
    }
};
