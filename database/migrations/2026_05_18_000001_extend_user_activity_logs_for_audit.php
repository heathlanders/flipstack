<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_activity_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('user_activity_logs', 'model_type')) {
                $table->string('model_type')->nullable()->after('session_id');
            }
            if (! Schema::hasColumn('user_activity_logs', 'model_id')) {
                $table->string('model_id')->nullable()->after('model_type');
            }
            if (! Schema::hasColumn('user_activity_logs', 'severity')) {
                $table->string('severity', 20)->default('info')->after('model_id');
            }
            if (! Schema::hasColumn('user_activity_logs', 'category')) {
                $table->string('category', 30)->default('general')->after('severity');
            }
        });

        $existing = collect(DB::select(
            "SELECT indexname FROM pg_indexes WHERE tablename = 'user_activity_logs'"
        ))->pluck('indexname')->all();

        $indexes = [
            'user_activity_logs_org_category_created_idx' => ['organization_id', 'category', 'created_at'],
            'user_activity_logs_org_action_created_idx' => ['organization_id', 'action', 'created_at'],
            'user_activity_logs_model_created_idx' => ['model_type', 'model_id', 'created_at'],
            'user_activity_logs_user_action_created_idx' => ['user_id', 'action', 'created_at'],
        ];

        Schema::table('user_activity_logs', function (Blueprint $table) use ($existing, $indexes) {
            foreach ($indexes as $name => $columns) {
                if (! in_array($name, $existing, true)) {
                    $table->index($columns, $name);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_activity_logs', function (Blueprint $table) {
            foreach ([
                'user_activity_logs_org_category_created_idx',
                'user_activity_logs_org_action_created_idx',
                'user_activity_logs_model_created_idx',
                'user_activity_logs_user_action_created_idx',
            ] as $idx) {
                try {
                    $table->dropIndex($idx);
                } catch (\Throwable $e) {
                    // ignore
                }
            }
            $table->dropColumn(['model_type', 'model_id', 'severity', 'category']);
        });
    }
};
