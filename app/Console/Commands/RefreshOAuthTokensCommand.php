<?php

namespace App\Console\Commands;

use App\Jobs\RefreshOAuthTokens;
use Illuminate\Console\Command;

class RefreshOAuthTokensCommand extends Command
{
    protected $signature = 'oauth:refresh-tokens';

    protected $description = 'Refresh OAuth tokens that are expiring soon';

    public function handle(): int
    {
        $this->info('Dispatching OAuth token refresh job...');

        RefreshOAuthTokens::dispatch();

        $this->info('OAuth token refresh job dispatched successfully');

        return Command::SUCCESS;
    }
}
