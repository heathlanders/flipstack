<?php

namespace App\Jobs\Currency;

use App\Services\Currency\ExchangeRateProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncExchangeRates implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 300;

    public int $timeout = 60;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(ExchangeRateProvider $provider): void
    {
        $count = $provider->syncRates('USD');

        Log::info('SyncExchangeRates: completed rate sync.', [
            'rates_synced' => $count,
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return ['exchange-rates'];
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncExchangeRates: failed to sync exchange rates.', [
            'error' => $exception->getMessage(),
        ]);
    }
}
