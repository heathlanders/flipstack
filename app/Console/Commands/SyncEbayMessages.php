<?php

namespace App\Console\Commands;

use App\Models\Communication\MarketplaceMessage;
use App\Models\Integration\IntegrationCredential;
use App\Models\Organization\Organization;
use App\Services\Communication\EbayMessageService;
use App\Services\Communication\MessageSyncService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncEbayMessages extends Command
{
    protected $signature = 'ebay:sync-messages
                            {organization? : Organization ID (omit for all)}
                            {--since= : Fetch messages since this date (e.g. "30 days ago", "2026-04-01")}
                            {--dry-run : Fetch and display messages without saving}
                            {--clean-html : Strip HTML from existing stored message bodies}';

    protected $description = 'Sync eBay messages for an organization (or all organizations)';

    public function handle(): int
    {
        if ($this->option('clean-html')) {
            return $this->cleanHtmlBodies();
        }

        $orgId = $this->argument('organization');

        if ($orgId) {
            $org = Organization::find($orgId);
            if (! $org) {
                $this->error("Organization {$orgId} not found.");

                return self::FAILURE;
            }
            $orgs = collect([$org]);
        } else {
            $orgs = Organization::all();
        }

        $isDryRun = $this->option('dry-run');
        $sinceOption = $this->option('since');

        foreach ($orgs as $org) {
            $this->info("Processing organization: {$org->name} ({$org->id})");

            $credentials = IntegrationCredential::where('organization_id', $org->id)
                ->where('platform', 'ebay')
                ->where('is_active', true)
                ->get();

            if ($credentials->isEmpty()) {
                $this->warn('  No active eBay credentials found.');

                continue;
            }

            foreach ($credentials as $credential) {
                $this->info("  Credential: {$credential->id}");

                if ($isDryRun) {
                    $this->dryRun($credential, $sinceOption);
                } else {
                    $service = new MessageSyncService;
                    $since = $sinceOption ? Carbon::parse($sinceOption) : null;

                    // If custom since date, temporarily override the config
                    if ($since) {
                        $config = $credential->config ?? [];
                        $originalSync = $config['last_message_sync_at'] ?? null;
                        $config['last_message_sync_at'] = $since->toIso8601String();
                        $credential->update(['config' => $config]);
                    }

                    $synced = $service->syncEbayMessages($credential, $org->id);
                    $this->info("  Synced {$synced} new messages.");

                    // Restore original sync time if we overrode it
                    if ($since && isset($originalSync)) {
                        // Don't restore - let it update to now() as normal
                    }
                }
            }
        }

        return self::SUCCESS;
    }

    protected function dryRun(IntegrationCredential $credential, ?string $sinceOption): void
    {
        $service = new EbayMessageService($credential);

        $since = $sinceOption
            ? Carbon::parse($sinceOption)
            : now()->subDays(30);

        $this->info("  Fetching messages since: {$since->toDateTimeString()}");

        $messages = $service->fetchMessages($since);

        if ($messages->isEmpty()) {
            $this->warn('  No messages returned from eBay.');

            return;
        }

        $this->info("  Found {$messages->count()} messages:");
        $this->newLine();

        $rows = $messages->map(fn ($m) => [
            $m['marketplace_message_id'],
            $m['message_type'] ?? '-',
            $m['direction'],
            mb_substr($m['subject'], 0, 40),
            $m['sender_name'] ?: $m['sender_id'],
            $m['marketplace_item_id'] ?: '-',
            $m['received_at']->format('Y-m-d H:i'),
        ])->toArray();

        $this->table(
            ['Message ID', 'Type', 'Direction', 'Subject', 'Sender', 'Item ID', 'Received'],
            $rows
        );
    }

    protected function cleanHtmlBodies(): int
    {
        $query = MarketplaceMessage::where('marketplace', 'ebay')
            ->where('body', 'like', '%<html%')
            ->orWhere(fn ($q) => $q->where('marketplace', 'ebay')->where('body', 'like', '%<!DOCTYPE%'));

        $total = $query->count();

        if ($total === 0) {
            $this->info('No messages with HTML bodies found.');

            return self::SUCCESS;
        }

        $this->info("Found {$total} messages with HTML bodies. Cleaning...");

        // Use a temporary EbayMessageService instance just for the extraction method
        // We need to call extractMessageBody which is protected, so use reflection
        $cleaned = 0;

        $query->chunk(50, function ($messages) use (&$cleaned) {
            foreach ($messages as $message) {
                $originalBody = $message->body;
                $cleanBody = $this->extractMessageBody($originalBody);

                if ($cleanBody !== $originalBody && ! empty($cleanBody)) {
                    $message->update(['body' => $cleanBody]);
                    $cleaned++;
                    $this->line("  Cleaned: {$message->marketplace_message_id} - ".mb_substr($cleanBody, 0, 80).'...');
                }
            }
        });

        $this->info("Cleaned {$cleaned} of {$total} messages.");

        return self::SUCCESS;
    }

    protected function extractMessageBody(string $html): string
    {
        if (empty($html) || (! str_contains($html, '<') || ! str_contains($html, '>'))) {
            return trim($html);
        }

        // Extract the buyer's actual message from the UserInputtedText div
        if (preg_match('/<div\s+id="UserInputtedText">(.*?)<\/div>/si', $html, $matches)) {
            $body = $matches[1];
            $body = preg_replace('/<br\s*\/?>/i', "\n", $body);
            $body = strip_tags($body);
            $body = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $body = trim($body);

            if (! empty($body)) {
                return $body;
            }
        }

        // Fallback: strip all HTML and clean up
        $body = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $html);
        $body = preg_replace('/<br\s*\/?>/i', "\n", $body);
        $body = strip_tags($body);
        $body = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $body = preg_replace('/[ \t]+/', ' ', $body);
        $body = preg_replace('/\n[ \t]+/', "\n", $body);
        $body = preg_replace('/\n{3,}/', "\n\n", $body);

        return trim($body);
    }
}
