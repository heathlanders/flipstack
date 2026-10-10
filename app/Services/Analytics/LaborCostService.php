<?php

namespace App\Services\Analytics;

use App\Models\Analytics\UserCompensation;
use App\Services\Analytics\Concerns\AnalyticsQueryTrait;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Labor cost analytics for KPIs #13-#16.
 *
 * Pulls effective hourly rates from user_compensations (effective-dated)
 * and combines with workforce_performance_daily totals to derive cost
 * per picked unit, per listed item, per resolved ticket, and cost-to-
 * serve per order.
 *
 * #13 cost per picked unit: (active seconds * hourly rate / 3600) / items_picked
 * #14 cost per listed item: minutes_per_listing * hourly_rate / 60 per product created
 * #15 cost per resolved ticket: minutes_per_ticket * hourly_rate / 60 per reply sent
 * #16 cost-to-serve per order: avg pick + pack seconds per order * hourly rate
 */
class LaborCostService
{
    use AnalyticsQueryTrait;

    /**
     * All effective comp records that overlap the active date range.
     * Keyed by user_id; if a user has multiple records overlapping the
     * range we use the most-recently-effective one.
     */
    protected function compsByUser(): Collection
    {
        return UserCompensation::query()
            ->where('organization_id', $this->organizationId)
            ->whereDate('effective_from', '<=', $this->endDate)
            ->where(function ($q) {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $this->startDate);
            })
            ->orderByDesc('effective_from')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($group) => $group->first());
    }

    /**
     * Per-user labor cost roll-up: rate, total active hours, total labor
     * cost, items picked, items packed, listings created, replies sent,
     * orders contributed to, plus the four derived KPIs.
     */
    public function getCostByUser(): array
    {
        $comps = $this->compsByUser();

        if ($comps->isEmpty()) {
            return [];
        }

        $userIds = $comps->keys()->all();

        $workforce = DB::table('workforce_performance_daily')
            ->where('organization_id', $this->organizationId)
            ->whereIn('user_id', $userIds)
            ->whereBetween('performance_date', [$this->startDate, $this->endDate])
            ->select([
                'user_id',
                DB::raw('COALESCE(SUM(items_picked), 0) as items_picked'),
                DB::raw('COALESCE(SUM(items_packed), 0) as items_packed'),
                DB::raw('COALESCE(SUM(orders_picked), 0) as orders_picked'),
                DB::raw('COALESCE(SUM(orders_packed), 0) as orders_packed'),
                DB::raw('COALESCE(SUM(total_active_seconds), 0) as total_active_seconds'),
                DB::raw('COALESCE(AVG(avg_pick_duration_seconds), 0) as avg_pick_seconds'),
                DB::raw('COALESCE(AVG(avg_pack_duration_seconds), 0) as avg_pack_seconds'),
            ])
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $listings = DB::table('products')
            ->where('organization_id', $this->organizationId)
            ->whereIn('created_by', $userIds)
            ->whereBetween('created_at', [$this->startDate.' 00:00:00', $this->endDate.' 23:59:59'])
            ->select(['created_by', DB::raw('COUNT(*) as listings_created')])
            ->groupBy('created_by')
            ->get()
            ->keyBy('created_by');

        $replies = DB::table('marketplace_message_replies')
            ->join('marketplace_messages', 'marketplace_message_replies.message_id', '=', 'marketplace_messages.id')
            ->where('marketplace_messages.organization_id', $this->organizationId)
            ->whereIn('marketplace_message_replies.sent_by', $userIds)
            ->whereBetween('marketplace_message_replies.sent_at', [$this->startDate.' 00:00:00', $this->endDate.' 23:59:59'])
            ->select(['marketplace_message_replies.sent_by as user_id', DB::raw('COUNT(*) as replies_sent')])
            ->groupBy('marketplace_message_replies.sent_by')
            ->get()
            ->keyBy('user_id');

        $names = DB::table('users')->whereIn('id', $userIds)->pluck('name', 'id');

        $rows = [];
        foreach ($comps as $userId => $comp) {
            $rate = (float) $comp->hourly_rate;
            $w = $workforce->get($userId);
            $lst = $listings->get($userId);
            $rep = $replies->get($userId);

            $activeSeconds = (int) ($w->total_active_seconds ?? 0);
            $itemsPicked = (int) ($w->items_picked ?? 0);
            $itemsPacked = (int) ($w->items_packed ?? 0);
            $ordersPicked = (int) ($w->orders_picked ?? 0);
            $ordersPacked = (int) ($w->orders_packed ?? 0);
            $listingsCreated = (int) ($lst->listings_created ?? 0);
            $repliesSent = (int) ($rep->replies_sent ?? 0);

            $laborCostFromActiveTime = ($activeSeconds / 3600) * $rate;
            $listingCost = ($listingsCreated * $comp->minutes_per_listing / 60) * $rate;
            $ticketCost = ($repliesSent * $comp->minutes_per_ticket / 60) * $rate;
            $totalLaborCost = $laborCostFromActiveTime + $listingCost + $ticketCost;

            $costPerPickedUnit = $itemsPicked > 0 ? round($laborCostFromActiveTime / $itemsPicked, 4) : 0;
            $costPerListedItem = $listingsCreated > 0 ? round($listingCost / $listingsCreated, 4) : 0;
            $costPerTicket = $repliesSent > 0 ? round($ticketCost / $repliesSent, 4) : 0;
            $serviceSeconds = (float) ($w->avg_pick_seconds ?? 0) + (float) ($w->avg_pack_seconds ?? 0);
            $costToServePerOrder = round(($serviceSeconds / 3600) * $rate, 4);

            $rows[] = [
                'user_id' => (int) $userId,
                'user_name' => $names[$userId] ?? "User #{$userId}",
                'hourly_rate' => round($rate, 2),
                'minutes_per_listing' => (int) $comp->minutes_per_listing,
                'minutes_per_ticket' => (int) $comp->minutes_per_ticket,
                'active_hours' => round($activeSeconds / 3600, 2),
                'items_picked' => $itemsPicked,
                'items_packed' => $itemsPacked,
                'orders_picked' => $ordersPicked,
                'orders_packed' => $ordersPacked,
                'listings_created' => $listingsCreated,
                'replies_sent' => $repliesSent,
                'total_labor_cost' => round($totalLaborCost, 2),
                'cost_per_picked_unit' => $costPerPickedUnit,
                'cost_per_listed_item' => $costPerListedItem,
                'cost_per_ticket' => $costPerTicket,
                'cost_to_serve_per_order' => $costToServePerOrder,
            ];
        }

        usort($rows, fn ($a, $b) => $b['total_labor_cost'] <=> $a['total_labor_cost']);

        return $rows;
    }

    /**
     * Single-user roll-up — same shape as one row of getCostByUser().
     */
    public function getCostForUser(int $userId): ?array
    {
        $rows = $this->getCostByUser();
        foreach ($rows as $row) {
            if ($row['user_id'] === $userId) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Org-wide totals + averages for the team header.
     */
    public function getOverview(): array
    {
        $rows = $this->getCostByUser();

        if (empty($rows)) {
            return [
                'users_with_rates' => 0,
                'total_labor_cost' => 0,
                'avg_cost_per_picked_unit' => 0,
                'avg_cost_per_listed_item' => 0,
                'avg_cost_per_ticket' => 0,
                'avg_cost_to_serve_per_order' => 0,
            ];
        }

        $totalCost = array_sum(array_column($rows, 'total_labor_cost'));
        $totalPicked = array_sum(array_column($rows, 'items_picked'));
        $totalListings = array_sum(array_column($rows, 'listings_created'));
        $totalReplies = array_sum(array_column($rows, 'replies_sent'));
        $totalOrders = array_sum(array_column($rows, 'orders_picked')) + array_sum(array_column($rows, 'orders_packed'));

        $sumProduct = fn (string $costKey, string $weightKey) => array_sum(array_map(
            fn ($r) => $r[$costKey] * $r[$weightKey],
            $rows
        ));

        $weightedAvg = fn ($numerator, $denominator) => $denominator > 0 ? round($numerator / $denominator, 4) : 0;

        return [
            'users_with_rates' => count($rows),
            'total_labor_cost' => round($totalCost, 2),
            'avg_cost_per_picked_unit' => $weightedAvg($sumProduct('cost_per_picked_unit', 'items_picked'), $totalPicked),
            'avg_cost_per_listed_item' => $weightedAvg($sumProduct('cost_per_listed_item', 'listings_created'), $totalListings),
            'avg_cost_per_ticket' => $weightedAvg($sumProduct('cost_per_ticket', 'replies_sent'), $totalReplies),
            'avg_cost_to_serve_per_order' => $weightedAvg($sumProduct('cost_to_serve_per_order', 'orders_picked') + $sumProduct('cost_to_serve_per_order', 'orders_packed'), $totalOrders),
        ];
    }
}
