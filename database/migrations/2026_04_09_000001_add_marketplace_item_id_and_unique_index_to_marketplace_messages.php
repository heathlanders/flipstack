<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_messages', function (Blueprint $table) {
            $table->string('marketplace_item_id')->nullable()->after('marketplace_thread_id');
            $table->unique(
                ['organization_id', 'marketplace', 'marketplace_message_id'],
                'marketplace_messages_org_mkt_msg_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_messages', function (Blueprint $table) {
            $table->dropUnique('marketplace_messages_org_mkt_msg_unique');
            $table->dropColumn('marketplace_item_id');
        });
    }
};
