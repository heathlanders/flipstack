<?php

namespace App\Jobs\Analytics;

use App\Services\Analytics\DemandForecastingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateDemandForecasts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        protected string $organizationId
    ) {}

    public function handle(DemandForecastingService $service): void
    {
        $service->generateForOrganization($this->organizationId);
    }

    public function tags(): array
    {
        return ['demand-forecasts', 'org:'.$this->organizationId];
    }
}
