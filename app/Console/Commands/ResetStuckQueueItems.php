<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetStuckQueueItems extends Command
{
    protected $signature = 'queue:reset-stuck {--minutes=10 : Minutes before an item is considered stuck}';

    protected $description = 'Reset intake queue items stuck in "processing" status back to "pending"';

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');

        $affected = DB::table('product_intake_queue')
            ->where('ai_analysis_status', 'processing')
            ->where('ai_started_at', '<', now()->subMinutes($minutes))
            ->update([
                'ai_analysis_status' => 'pending',
                'processing_status' => 'pending',
                'updated_at' => now(),
            ]);

        $this->info("Reset {$affected} stuck queue items back to pending.");

        return self::SUCCESS;
    }
}
