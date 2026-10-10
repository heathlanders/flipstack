<?php

namespace App\Console\Commands;

use Database\Seeders\DevelopTestingSeeder;
use Illuminate\Console\Command;

class SeedDevelopTesting extends Command
{
    protected $signature = 'flipstack:seed-testing
                            {--reset : Tear down existing FlipStack Testing org before seeding}
                            {--force : Allow running in production}';

    protected $description = 'Seed the FlipStack Testing org + role-scoped QA users on develop.flipstack.app';

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Refusing to run in production. Pass --force to override.');

            return Command::FAILURE;
        }

        if ($this->option('reset')) {
            $this->warn('Resetting existing FlipStack Testing org...');
            DevelopTestingSeeder::reset();
            $this->info('Reset complete.');
            $this->newLine();
        }

        $this->call('db:seed', [
            '--class' => DevelopTestingSeeder::class,
            '--force' => true,
        ]);

        $this->newLine();
        $this->info('Login at: https://develop.flipstack.app/login');
        $this->info('Shared password: '.DevelopTestingSeeder::SHARED_PASSWORD);

        return Command::SUCCESS;
    }
}
