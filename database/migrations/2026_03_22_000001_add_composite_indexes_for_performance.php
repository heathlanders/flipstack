<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Products: composite index for dashboard stats queries (status + stock filtering)
        Schema::table('products', function (Blueprint $table) {
            $table->index(
                ['organization_id', 'status', 'stock_quantity'],
                'idx_products_org_status_stock'
            );
        });

        // Product intake queue: composite indexes for common filtering/sorting patterns
        Schema::table('product_intake_queue', function (Blueprint $table) {
            $table->index(
                ['team_id', 'processing_status', 'created_at'],
                'idx_piq_team_status_created'
            );
            $table->index(
                ['captured_by', 'team_id', 'created_at'],
                'idx_piq_user_team_created'
            );
        });

        // Orders: indexes for marketplace order lookup and status filtering
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (! $this->indexExists('orders', 'idx_orders_marketplace_order_id')) {
                    $table->index(
                        ['marketplace', 'marketplace_order_id'],
                        'idx_orders_marketplace_order_id'
                    );
                }
                if (! $this->indexExists('orders', 'idx_orders_org_status')) {
                    $table->index(
                        ['organization_id', 'status', 'created_at'],
                        'idx_orders_org_status'
                    );
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_products_org_status_stock');
        });

        Schema::table('product_intake_queue', function (Blueprint $table) {
            $table->dropIndex('idx_piq_team_status_created');
            $table->dropIndex('idx_piq_user_team_created');
        });

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if ($this->indexExists('orders', 'idx_orders_marketplace_order_id')) {
                    $table->dropIndex('idx_orders_marketplace_order_id');
                }
                if ($this->indexExists('orders', 'idx_orders_org_status')) {
                    $table->dropIndex('idx_orders_org_status');
                }
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return DB::select(
            'SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?',
            [$table, $indexName]
        ) !== [];
    }
};
