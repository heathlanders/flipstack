<?php

namespace App\Console\Commands;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;

class SeedTestCommand extends Command
{
    protected $signature = 'db:seed:test
                            {--force : Force the operation to run in production}';

    protected $description = 'Seed the database with full test data (organizations, users, products, etc.)';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('This command cannot be run in production! Use db:seed:production instead.');

            return Command::FAILURE;
        }

        $this->info('Running test database seeder...');
        $this->warn('This will create sample organizations, users, and test data.');
        $this->newLine();

        if (! $this->option('force') && ! $this->confirm('Continue?')) {
            $this->info('Seeding cancelled.');

            return Command::SUCCESS;
        }

        $this->call('db:seed', [
            '--class' => DatabaseSeeder::class,
            '--force' => $this->option('force'),
        ]);

        return Command::SUCCESS;
    }
}
