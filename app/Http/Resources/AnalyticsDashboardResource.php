<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnalyticsDashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'inventory_value' => $this->formatInventoryValue($this['inventory_value'] ?? []),
            'total_skus' => $this['total_skus'] ?? 0,
            'active_skus' => $this['active_skus'] ?? 0,
            'low_stock_count' => $this['low_stock_count'] ?? 0,
            'out_of_stock_count' => $this['out_of_stock_count'] ?? 0,
            'stock_health' => $this->formatStockHealth($this['stock_health'] ?? []),
            'movement_summary' => $this['movement_summary'] ?? [],
            'top_performers' => $this['top_performers'] ?? [],
            'recent_movements' => $this['recent_movements'] ?? [],
            'category_distribution' => $this['category_distribution'] ?? [],
            'brand_distribution' => $this['brand_distribution'] ?? [],
            'condition_distribution' => $this['condition_distribution'] ?? [],
            'aging_analysis' => $this['aging_analysis'] ?? [],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Format inventory value data
     */
    protected function formatInventoryValue(array $data): array
    {
        return [
            'cost_value' => [
                'amount' => $data['cost_value'] ?? 0,
                'formatted' => $this->formatCurrency($data['cost_value'] ?? 0),
            ],
            'retail_value' => [
                'amount' => $data['retail_value'] ?? 0,
                'formatted' => $this->formatCurrency($data['retail_value'] ?? 0),
            ],
            'potential_profit' => [
                'amount' => $data['potential_profit'] ?? 0,
                'formatted' => $this->formatCurrency($data['potential_profit'] ?? 0),
            ],
            'profit_margin' => $data['profit_margin'] ?? 0,
            'total_units' => $data['total_units'] ?? 0,
            'change_percentage' => $data['change_percentage'] ?? 0,
            'trend' => ($data['change_percentage'] ?? 0) >= 0 ? 'up' : 'down',
        ];
    }

    /**
     * Format stock health data
     */
    protected function formatStockHealth(array $data): array
    {
        $statuses = ['healthy', 'low', 'critical', 'out_of_stock', 'overstocked'];
        $colors = [
            'healthy' => '#10B981',      // Green
            'low' => '#F59E0B',          // Yellow
            'critical' => '#EF4444',     // Red
            'out_of_stock' => '#6B7280', // Gray
            'overstocked' => '#3B82F6',  // Blue
        ];

        $formatted = [];
        foreach ($statuses as $status) {
            $formatted[$status] = [
                'count' => $data[$status]['count'] ?? 0,
                'percentage' => $data[$status]['percentage'] ?? 0,
                'color' => $colors[$status],
                'label' => ucwords(str_replace('_', ' ', $status)),
            ];
        }

        return $formatted;
    }

    /**
     * Format currency value
     */
    protected function formatCurrency(float $amount): string
    {
        return '$'.number_format($amount, 2);
    }
}
