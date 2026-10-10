<?php

namespace App\Jobs\Marketplace;

use App\Services\Marketplace\ListingSchedulerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessScheduledListings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function handle(ListingSchedulerService $service): void
    {
        $count = $service->processDueListings();

        Log::info('Processed scheduled listings.', ['count' => $count]);
    }

    public function tags(): array
    {
        return ['scheduled-listings'];
    }
}
