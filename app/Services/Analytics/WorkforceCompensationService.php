<?php

namespace App\Services\Analytics;

use App\Services\Analytics\Concerns\AnalyticsQueryTrait;
use Illuminate\Support\Facades\DB;

/**
 * Compensation rollup analytics: turn the supplier-keyed settlements
 * stack into a per-user view (KPI #21), commission attribution by
 * structure (KPI #22), and tier-based bonus breakdown (KPI #23).
 *
 * Settlements join to users through suppliers (suppliers.user_id).
 */
class WorkforceCompensationService
{
    use AnalyticsQueryTrait;

    /**
     * Settlement-ready per-user roll-up across the date range (KPI #21).
     */
    public function getUserSettlementSummary(): array
    {
        return $this->cached('comp_user_settlement_summary', 900, function () {
            return DB::table('settlements')
                ->join('suppliers', 'settlements.supplier_id', '=', 'suppliers.id')
                ->leftJoin('users', 'suppliers.user_id', '=', 'users.id')
                ->leftJoin('supplier_tiers', 'suppliers.current_tier_id', '=', 'supplier_tiers.id')
                ->where('settlements.organization_id', $this->organizationId)
                ->whereBetween('settlements.settlement_period_start', [$this->startDate, $this->endDate])
                ->whereNull('settlements.deleted_at')
                ->select([
                    'users.id as user_id',
                    'users.name as user_name',
                    'users.email as user_email',
                    'suppliers.id as supplier_id',
                    'suppliers.name as supplier_name',
                    'supplier_tiers.tier_name as tier_name',
                    'supplier_tiers.color_code as tier_color',
                    DB::raw('COUNT(settlements.id) as settlement_count'),
                    DB::raw('COALESCE(SUM(settlements.total_items_sold), 0) as total_items_sold'),
                    DB::raw('COALESCE(SUM(settlements.gross_sales_amount), 0) as gross_sales'),
                    DB::raw('COALESCE(SUM(settlements.commission_amount), 0) as commission'),
                    DB::raw('COALESCE(SUM(settlements.bonuses), 0) as bonuses'),
                    DB::raw('COALESCE(SUM(settlements.adjustments), 0) as adjustments'),
                    DB::raw('COALESCE(SUM(settlements.fees_deducted), 0) as fees'),
                    DB::raw('COALESCE(SUM(settlements.net_amount), 0) as net_payout'),
                    DB::raw("COUNT(*) FILTER (WHERE settlements.status IN ('pending', 'approved')) as pending_settlements"),
                    DB::raw("COUNT(*) FILTER (WHERE settlements.status = 'paid') as paid_settlements"),
                ])
                ->groupBy('users.id', 'users.name', 'users.email', 'suppliers.id', 'suppliers.name', 'supplier_tiers.tier_name', 'supplier_tiers.color_code')
                ->orderByDesc('net_payout')
                ->get()
                ->map(fn ($row) => [
                    'user_id' => $row->user_id,
                    'user_name' => $row->user_name ?? '— (no user link)',
                    'user_email' => $row->user_email,
                    'supplier_id' => $row->supplier_id,
                    'supplier_name' => $row->supplier_name,
                    'tier_name' => $row->tier_name,
                    'tier_color' => $row->tier_color,
                    'settlement_count' => (int) $row->settlement_count,
                    'total_items_sold' => (int) $row->total_items_sold,
                    'gross_sales' => round((float) $row->gross_sales, 2),
                    'commission' => round((float) $row->commission, 2),
                    'bonuses' => round((float) $row->bonuses, 2),
                    'adjustments' => round((float) $row->adjustments, 2),
                    'fees' => round((float) $row->fees, 2),
                    'net_payout' => round((float) $row->net_payout, 2),
                    'pending_settlements' => (int) $row->pending_settlements,
                    'paid_settlements' => (int) $row->paid_settlements,
                ])
                ->toArray();
        });
    }

    /**
     * Commission attribution: which commission structures are paying out
     * the most across the period (KPI #22).
     */
    public function getCommissionAttribution(): array
    {
        return $this->cached('comp_commission_attribution', 900, function () {
            return DB::table('settlement_items')
                ->join('settlements', 'settlement_items.settlement_id', '=', 'settlements.id')
                ->leftJoin('commission_structures', 'settlement_items.commission_structure_id', '=', 'commission_structures.id')
                ->where('settlements.organization_id', $this->organizationId)
                ->whereBetween('settlements.settlement_period_start', [$this->startDate, $this->endDate])
                ->whereNull('settlements.deleted_at')
                ->select([
                    'commission_structures.id as structure_id',
                    'commission_structures.name as structure_name',
                    'commission_structures.type as structure_type',
                    'commission_structures.base_percentage',
                    'commission_structures.flat_fee_amount',
                    DB::raw('COUNT(settlement_items.id) as item_count'),
                    DB::raw('COALESCE(SUM(settlement_items.commission_amount), 0) as total_commission'),
                    DB::raw('COALESCE(SUM(settlement_items.tier_adjustment), 0) as total_tier_adjustments'),
                    DB::raw('COALESCE(SUM(settlement_items.bonus_amount), 0) as total_bonuses'),
                    DB::raw('COALESCE(SUM(settlement_items.final_commission), 0) as total_final_commission'),
                    DB::raw('COALESCE(AVG(settlement_items.commission_rate), 0) as avg_rate'),
                ])
                ->groupBy(
                    'commission_structures.id',
                    'commission_structures.name',
                    'commission_structures.type',
                    'commission_structures.base_percentage',
                    'commission_structures.flat_fee_amount'
                )
                ->orderByDesc('total_final_commission')
                ->get()
                ->map(fn ($row) => [
                    'structure_id' => $row->structure_id,
                    'structure_name' => $row->structure_name ?? '— (no structure)',
                    'structure_type' => $row->structure_type,
                    'base_percentage' => $row->base_percentage !== null ? round((float) $row->base_percentage, 2) : null,
                    'flat_fee_amount' => $row->flat_fee_amount !== null ? round((float) $row->flat_fee_amount, 2) : null,
                    'item_count' => (int) $row->item_count,
                    'total_commission' => round((float) $row->total_commission, 2),
                    'total_tier_adjustments' => round((float) $row->total_tier_adjustments, 2),
                    'total_bonuses' => round((float) $row->total_bonuses, 2),
                    'total_final_commission' => round((float) $row->total_final_commission, 2),
                    'avg_rate' => round((float) $row->avg_rate, 4),
                ])
                ->toArray();
        });
    }

    /**
     * Tier-based bonus breakdown — which tiers pay out the most in
     * adjustments and bonuses (KPI #23).
     */
    public function getTierBonusBreakdown(): array
    {
        return $this->cached('comp_tier_bonus_breakdown', 900, function () {
            return DB::table('settlement_items')
                ->join('settlements', 'settlement_items.settlement_id', '=', 'settlements.id')
                ->join('suppliers', 'settlements.supplier_id', '=', 'suppliers.id')
                ->leftJoin('supplier_tiers', 'suppliers.current_tier_id', '=', 'supplier_tiers.id')
                ->where('settlements.organization_id', $this->organizationId)
                ->whereBetween('settlements.settlement_period_start', [$this->startDate, $this->endDate])
                ->whereNull('settlements.deleted_at')
                ->select([
                    'supplier_tiers.id as tier_id',
                    'supplier_tiers.tier_name',
                    'supplier_tiers.tier_code',
                    'supplier_tiers.commission_adjustment as tier_commission_adjustment',
                    'supplier_tiers.color_code',
                    DB::raw('COUNT(DISTINCT suppliers.id) as supplier_count'),
                    DB::raw('COUNT(settlement_items.id) as item_count'),
                    DB::raw('COALESCE(SUM(settlement_items.tier_adjustment), 0) as total_tier_adjustments'),
                    DB::raw('COALESCE(SUM(settlement_items.bonus_amount), 0) as total_bonuses'),
                    DB::raw('COALESCE(SUM(settlement_items.final_commission), 0) as total_final_commission'),
                    DB::raw('COALESCE(AVG(settlement_items.tier_adjustment), 0) as avg_tier_adjustment'),
                ])
                ->groupBy(
                    'supplier_tiers.id',
                    'supplier_tiers.tier_name',
                    'supplier_tiers.tier_code',
                    'supplier_tiers.commission_adjustment',
                    'supplier_tiers.color_code'
                )
                ->orderByDesc('total_final_commission')
                ->get()
                ->map(fn ($row) => [
                    'tier_id' => $row->tier_id,
                    'tier_name' => $row->tier_name ?? '— (no tier)',
                    'tier_code' => $row->tier_code,
                    'tier_commission_adjustment' => $row->tier_commission_adjustment !== null ? round((float) $row->tier_commission_adjustment, 2) : null,
                    'color_code' => $row->color_code,
                    'supplier_count' => (int) $row->supplier_count,
                    'item_count' => (int) $row->item_count,
                    'total_tier_adjustments' => round((float) $row->total_tier_adjustments, 2),
                    'total_bonuses' => round((float) $row->total_bonuses, 2),
                    'total_final_commission' => round((float) $row->total_final_commission, 2),
                    'avg_tier_adjustment' => round((float) $row->avg_tier_adjustment, 2),
                ])
                ->toArray();
        });
    }

    /**
     * Top-line org-wide totals for the comp dashboard header.
     */
    public function getOverview(): array
    {
        return $this->cached('comp_overview', 900, function () {
            $row = DB::table('settlements')
                ->where('organization_id', $this->organizationId)
                ->whereBetween('settlement_period_start', [$this->startDate, $this->endDate])
                ->whereNull('deleted_at')
                ->select([
                    DB::raw('COUNT(*) as settlement_count'),
                    DB::raw('COUNT(DISTINCT supplier_id) as recipient_count'),
                    DB::raw('COALESCE(SUM(gross_sales_amount), 0) as gross_sales'),
                    DB::raw('COALESCE(SUM(commission_amount), 0) as total_commission'),
                    DB::raw('COALESCE(SUM(bonuses), 0) as total_bonuses'),
                    DB::raw('COALESCE(SUM(net_amount), 0) as total_payout'),
                    DB::raw("COALESCE(SUM(net_amount) FILTER (WHERE status = 'paid'), 0) as paid_payout"),
                    DB::raw("COALESCE(SUM(net_amount) FILTER (WHERE status IN ('pending','approved')), 0) as pending_payout"),
                ])
                ->first();

            return [
                'settlement_count' => (int) $row->settlement_count,
                'recipient_count' => (int) $row->recipient_count,
                'gross_sales' => round((float) $row->gross_sales, 2),
                'total_commission' => round((float) $row->total_commission, 2),
                'total_bonuses' => round((float) $row->total_bonuses, 2),
                'total_payout' => round((float) $row->total_payout, 2),
                'paid_payout' => round((float) $row->paid_payout, 2),
                'pending_payout' => round((float) $row->pending_payout, 2),
            ];
        });
    }
}
