<?php

namespace App\Console\Commands;

use Database\Seeders\Production\ProductionSeeder;
use Illuminate\Console\Command;

class SeedProductionCommand extends Command
{
    protected $signature = 'db:seed:production
                            {--force : Force the operation to run in production}';

    protected $description = 'Seed the database with production system data (roles, categories, prompts)';

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            if (! $this->confirm('You are in production. Are you sure you want to run this seeder?')) {
                $this->info('Seeding cancelled.');

                return Command::SUCCESS;
            }
        }

        $this->info('Running production database seeder...');
        $this->newLine();

        $this->call('db:seed', [
            '--class' => ProductionSeeder::class,
            '--force' => $this->option('force'),
        ]);

        return Command::SUCCESS;
    }
}
