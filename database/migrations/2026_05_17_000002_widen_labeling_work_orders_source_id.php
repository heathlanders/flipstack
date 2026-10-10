<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // source_id may reference UUID-keyed sources (intake_queue, part_out, po_receipt)
        // or bigint-keyed BulkOperationJob (csv_import, ebay_import, shopify_import).
        DB::statement('ALTER TABLE labeling_work_orders ALTER COLUMN source_id TYPE varchar(64) USING source_id::text');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE labeling_work_orders ALTER COLUMN source_id TYPE uuid USING source_id::uuid');
    }
};
