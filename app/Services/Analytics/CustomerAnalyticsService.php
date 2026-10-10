<?php

namespace App\Services\Analytics;

use App\Services\Analytics\Concerns\AnalyticsQueryTrait;
use Illuminate\Support\Facades\DB;

class CustomerAnalyticsService
{
    use AnalyticsQueryTrait;

    public function getOverview(): array
    {
        return $this->cached('customer_overview', 900, function () {
            $total = DB::table('customers')
                ->where('organization_id', $this->organizationId)
                ->count();

            $newCustomers = DB::table('customers')
                ->where('organization_id', $this->organizationId)
                ->whereBetween('first_order_at', [$this->startDate, $this->endDate.' 23:59:59'])
                ->count();

            $repeatCount = DB::table('customers')
                ->where('organization_id', $this->organizationId)
                ->where('total_orders', '>', 1)
                ->count();

            $avgMetrics = DB::table('customers')
                ->where('organization_id', $this->organizationId)
                ->where('total_orders', '>', 0)
                ->select([
                    DB::raw('COALESCE(AVG(total_spent), 0) as avg_ltv'),
                    DB::raw('COALESCE(AVG(average_order_value), 0) as avg_aov'),
                ])
                ->first();

            return [
                'total_customers' => $total,
                'new_this_period' => $newCustomers,
                'repeat_rate' => $total > 0 ? round(($repeatCount / $total) * 100, 1) : 0,
                'avg_ltv' => round((float) $avgMetrics->avg_ltv, 2),
                'avg_aov' => round((float) $avgMetrics->avg_aov, 2),
            ];
        });
    }

    public function getLTVDistribution(): array
    {
        $buckets = [
            ['min' => 0, 'max' => 25, 'label' => '$0-25'],
            ['min' => 25, 'max' => 50, 'label' => '$25-50'],
            ['min' => 50, 'max' => 100, 'label' => '$50-100'],
            ['min' => 100, 'max' => 250, 'label' => '$100-250'],
            ['min' => 250, 'max' => 500, 'label' => '$250-500'],
            ['min' => 500, 'max' => null, 'label' => '$500+'],
        ];

        $results = [];
        foreach ($buckets as $bucket) {
            $query = DB::table('customers')
                ->where('organization_id', $this->organizationId)
                ->where('total_spent', '>=', $bucket['min']);

            if ($bucket['max'] !== null) {
                $query->where('total_spent', '<', $bucket['max']);
            }

            $results[] = [
                'label' => $bucket['label'],
                'count' => $query->count(),
            ];
        }

        return $results;
    }

    public function getRepeatPurchaseRate(): array
    {
        $oneTime = DB::table('customers')
            ->where('organization_id', $this->organizationId)
            ->where('total_orders', '=', 1)
            ->count();

        $repeat = DB::table('customers')
            ->where('organization_id', $this->organizationId)
            ->where('total_orders', '>', 1)
            ->count();

        $total = $oneTime + $repeat;

        return [
            'one_time_count' => $oneTime,
            'repeat_count' => $repeat,
            'rate' => $total > 0 ? round(($repeat / $total) * 100, 1) : 0,
        ];
    }

    public function getAOVTrend(): array
    {
        return DB::table('orders')
            ->where('organization_id', $this->organizationId)
            ->whereBetween('created_at', [$this->startDate, $this->endDate.' 23:59:59'])
            ->whereNotIn('status', ['cancelled'])
            ->select([
                DB::raw("TO_CHAR(created_at, 'YYYY-MM') as month"),
                DB::raw('COALESCE(AVG(total_amount), 0) as avg_order_value'),
                DB::raw('COUNT(*) as order_count'),
            ])
            ->groupBy(DB::raw("TO_CHAR(created_at, 'YYYY-MM')"))
            ->orderBy('month')
            ->get()
            ->map(fn ($row) => [
                'month' => $row->month,
                'avg_order_value' => round((float) $row->avg_order_value, 2),
                'order_count' => (int) $row->order_count,
            ])
            ->toArray();
    }

    public function getAcquisitionByMarketplace(): array
    {
        return DB::table('customers')
            ->where('organization_id', $this->organizationId)
            ->whereBetween('first_order_at', [$this->startDate, $this->endDate.' 23:59:59'])
            ->select([
                DB::raw("COALESCE(marketplace_source, 'Unknown') as marketplace"),
                DB::raw('COUNT(*) as count'),
            ])
            ->groupBy('marketplace_source')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'marketplace' => $row->marketplace,
                'count' => (int) $row->count,
            ])
            ->toArray();
    }

    public function getTopCustomers(int $limit = 10): array
    {
        return DB::table('customers')
            ->where('organization_id', $this->organizationId)
            ->where('total_orders', '>', 0)
            ->select(['name', 'email', 'total_orders', 'total_spent', 'average_order_value', 'first_order_at', 'last_order_at'])
            ->orderByDesc('total_spent')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'email' => $row->email,
                'total_orders' => (int) $row->total_orders,
                'total_spent' => round((float) $row->total_spent, 2),
                'average_order_value' => round((float) $row->average_order_value, 2),
                'first_order_at' => $row->first_order_at,
                'last_order_at' => $row->last_order_at,
            ])
            ->toArray();
    }

    public function getCustomerSegments(): array
    {
        $segments = [
            ['min' => 1, 'max' => 1, 'label' => 'One-Time'],
            ['min' => 2, 'max' => 3, 'label' => 'Occasional'],
            ['min' => 4, 'max' => 9, 'label' => 'Regular'],
            ['min' => 10, 'max' => null, 'label' => 'VIP'],
        ];

        $results = [];
        foreach ($segments as $segment) {
            $query = DB::table('customers')
                ->where('organization_id', $this->organizationId)
                ->where('total_orders', '>=', $segment['min']);

            if ($segment['max'] !== null) {
                $query->where('total_orders', '<=', $segment['max']);
            }

            $data = $query->select([
                DB::raw('COUNT(*) as count'),
                DB::raw('COALESCE(SUM(total_spent), 0) as total_spent'),
            ])->first();

            $results[] = [
                'label' => $segment['label'],
                'count' => (int) $data->count,
                'total_spent' => round((float) $data->total_spent, 2),
            ];
        }

        return $results;
    }
}
