<?php

namespace App\Jobs\Analytics;

use App\Models\Analytics\UserProductivityDaily;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Nightly rollup of audit action counts into user_productivity_daily.
 *
 * Counts user_activity_logs rows per user/day by action prefix and upserts the result.
 * Complementary to AggregateDailyKPIs (which reads fulfillment_logs for pick/pack timing).
 *
 * See action -> column mapping in docs/AUDIT_SYSTEM.md.
 */
class AggregateAuditDerivedKPIs implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    /**
     * Map column -> [action prefix or exact action match].
     * Each entry counts rows where action exactly matches OR starts with the prefix when ending in '.'.
     */
    public const ACTION_MAP = [
        'products_listed_count' => ['product.created'],
        'pos_created_count' => ['po.created'],
        'pos_approved_count' => ['po.approved'],
        'pos_received_count' => ['po.received'],
        'messages_answered_count' => ['message.replied'],
        'inventory_moves_count' => ['inventory.adjusted', 'inventory.moved', 'inventory.transferred'],
        'listings_published_ebay' => ['listing.ebay.published'],
        'listings_published_shopify' => ['listing.shopify.published'],
        'listings_published_amazon' => ['listing.amazon.published'],
        'customer_records_touched' => ['customer.created', 'customer.updated', 'customer.merged'],
        'pricing_rules_changed' => ['pricing.rule.created', 'pricing.rule.updated', 'pricing.rule.deleted'],
    ];

    public function __construct(
        protected int $organizationId,
        protected string $date,
    ) {
        $this->queue = 'analytics';
    }

    public function handle(): void
    {
        try {
            $userIds = DB::table('user_activity_logs')
                ->where('organization_id', $this->organizationId)
                ->whereDate('created_at', $this->date)
                ->whereNotNull('user_id')
                ->distinct()
                ->pluck('user_id');

            foreach ($userIds as $userId) {
                $this->aggregateForUser((int) $userId);
            }
        } catch (\Throwable $e) {
            Log::error('AggregateAuditDerivedKPIs failed', [
                'org' => $this->organizationId,
                'date' => $this->date,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function aggregateForUser(int $userId): void
    {
        $base = DB::table('user_activity_logs')
            ->where('organization_id', $this->organizationId)
            ->where('user_id', $userId)
            ->whereDate('created_at', $this->date);

        $counts = [];
        foreach (self::ACTION_MAP as $column => $actions) {
            $counts[$column] = (clone $base)->whereIn('action', $actions)->count();
        }

        // Avg response seconds for messages — derived from marketplace_message_replies if reachable.
        $avgResponse = DB::table('marketplace_message_replies')
            ->join('marketplace_messages', 'marketplace_message_replies.message_id', '=', 'marketplace_messages.id')
            ->where('marketplace_message_replies.sent_by', $userId)
            ->whereDate('marketplace_message_replies.sent_at', $this->date)
            ->whereNotNull('marketplace_messages.received_at')
            ->selectRaw('avg(extract(epoch from (marketplace_message_replies.sent_at - marketplace_messages.received_at)))::int as avg_seconds')
            ->value('avg_seconds');

        UserProductivityDaily::updateOrCreate(
            [
                'organization_id' => $this->organizationId,
                'user_id' => $userId,
                'performance_date' => $this->date,
            ],
            array_merge($counts, [
                'avg_response_seconds' => $avgResponse,
            ]),
        );
    }
}
