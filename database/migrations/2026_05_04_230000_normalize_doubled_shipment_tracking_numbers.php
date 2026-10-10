<?php

use App\Models\Fulfillment\Shipment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('shipments')
            ->whereNotNull('tracking_number')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $normalized = Shipment::normalizeTrackingNumber($row->tracking_number);
                    if ($normalized !== null && $normalized !== $row->tracking_number) {
                        DB::table('shipments')->where('id', $row->id)->update([
                            'tracking_number' => $normalized,
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Not reversible — original duplicated values weren't preserved.
    }
};
