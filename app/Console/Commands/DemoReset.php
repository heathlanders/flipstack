<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Restores the public demo instance to its seeded state. Scheduled nightly
 * in routes/console.php when APP_DEMO=true.
 */
class DemoReset extends Command
{
    protected $signature = 'demo:reset {--force : Run even when APP_DEMO is not set}';

    protected $description = 'Wipe and reseed the database for the public demo';

    public function handle(): int
    {
        if (! config('app.demo') && ! $this->option('force')) {
            $this->error('Refusing to reset: APP_DEMO is not enabled. Use --force to override.');

            return self::FAILURE;
        }

        $this->info('Resetting demo database...');
        Artisan::call('migrate:fresh', ['--force' => true, '--seed' => true], $this->output);
        $this->info('Demo reset complete.');

        return self::SUCCESS;
    }
}
