<?php

namespace App\Console\Commands;

use App\Jobs\Analytics\AggregateDailyKPIs;
use App\Models\Organization\Organization;
use Carbon\Carbon;
use Illuminate\Console\Command;

class BackfillKPISnapshots extends Command
{
    protected $signature = 'analytics:backfill {--days=90 : Number of days to backfill} {--org= : Specific organization ID}';

    protected $description = 'Backfill daily KPI snapshots, workforce metrics, and marketplace health data from historical records';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $orgId = $this->option('org');

        $organizations = $orgId
            ? Organization::where('id', $orgId)->get()
            : Organization::all();

        if ($organizations->isEmpty()) {
            $this->error('No organizations found.');

            return self::FAILURE;
        }

        $this->info("Backfilling {$days} days of KPI data for {$organizations->count()} organization(s)...");

        $bar = $this->output->createProgressBar($organizations->count() * $days);
        $bar->start();

        foreach ($organizations as $org) {
            for ($i = $days; $i >= 1; $i--) {
                $date = Carbon::now()->subDays($i);

                AggregateDailyKPIs::dispatch($org->id, $date->toDateString());

                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info('Backfill jobs dispatched successfully. Monitor the queue for completion.');

        return self::SUCCESS;
    }
}
