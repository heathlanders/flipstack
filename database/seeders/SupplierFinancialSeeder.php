<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupplierFinancialSeeder extends Seeder
{
    public function run(): void
    {
        $organizations = DB::table('organizations')->get();

        foreach ($organizations as $organization) {
            $this->seedOrganization($organization);
        }

        $this->command->info('Supplier financial data seeded successfully.');
    }

    private function seedOrganization($organization): void
    {
        $orgId = $organization->id;

        // Ensure tiers exist (SupplierTierSeeder may not have run)
        $tiers = $this->ensureTiers($orgId);

        // a) Commission Structures
        $commissions = $this->seedCommissionStructures($orgId);

        // b) Link suppliers to tiers + default commission + assign per-product buckets
        $suppliers = DB::table('suppliers')->where('organization_id', $orgId)->get();
        $this->linkSuppliers($suppliers, $tiers, $commissions, $orgId);

        // c) Settlements (6 months history + current draft)
        $products = DB::table('products')->where('organization_id', $orgId)->get();
        $this->seedSettlements($suppliers, $commissions, $products, $orgId);

        // d) Performance metrics
        $this->seedPerformanceMetrics($suppliers, $tiers, $orgId);

        // e) Update cached balances
        $this->updateCachedBalances($suppliers, $orgId);
    }

    private function ensureTiers($orgId): array
    {
        $existing = DB::table('supplier_tiers')->where('organization_id', $orgId)->pluck('id', 'tier_code')->toArray();

        if (count($existing) >= 4) {
            return $existing;
        }

        // Seed tiers if missing (call SupplierTierSeeder logic inline)
        app(SupplierTierSeeder::class)->setContainer(app())->setCommand($this->command);
        $org = \App\Models\Organization\Organization::find($orgId);
        if ($org) {
            app(SupplierTierSeeder::class)->createTiersForOrganization($org);
        }

        return DB::table('supplier_tiers')->where('organization_id', $orgId)->pluck('id', 'tier_code')->toArray();
    }

    private function seedCommissionStructures($orgId): array
    {
        $structures = [
            [
                'name' => 'Standard Consignment',
                'type' => 'percentage',
                'base_percentage' => 15.00,
                'description' => 'Default commission rate for most consigned products.',
                'priority' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Premium Consignment',
                'type' => 'percentage',
                'base_percentage' => 10.00,
                'description' => 'Reduced commission for high-value items over $500.',
                'priority' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Liquidation Rate',
                'type' => 'percentage',
                'base_percentage' => 25.00,
                'description' => 'Higher commission for clearance and bulk liquidation items.',
                'priority' => 3,
                'is_active' => true,
            ],
        ];

        $ids = [];

        foreach ($structures as $data) {
            $existing = DB::table('commission_structures')
                ->where('organization_id', $orgId)
                ->where('name', $data['name'])
                ->first();

            if ($existing) {
                $ids[$data['name']] = $existing->id;

                continue;
            }

            $id = Str::uuid()->toString();
            DB::table('commission_structures')->insert([
                'id' => $id,
                'organization_id' => $orgId,
                'name' => $data['name'],
                'description' => $data['description'],
                'type' => $data['type'],
                'base_percentage' => $data['base_percentage'],
                'flat_fee_amount' => 0,
                'minimum_commission' => 1.00,
                'maximum_commission' => null,
                'priority' => $data['priority'],
                'is_active' => $data['is_active'],
                'effective_date' => Carbon::parse('2025-01-01'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $ids[$data['name']] = $id;
        }

        return $ids;
    }

    private function linkSuppliers($suppliers, array $tiers, array $commissions, $orgId): void
    {
        $supplierTierMap = [
            'NCR-AUTH-001' => 'GOLD',
            'POSCOMP-003' => 'SILVER',
            'CEW-004' => 'SILVER',
            'ELIQ-002' => 'BRONZE',
        ];

        $standardId = $commissions['Standard Consignment'] ?? null;
        $premiumId = $commissions['Premium Consignment'] ?? null;
        $liquidationId = $commissions['Liquidation Rate'] ?? null;

        foreach ($suppliers as $supplier) {
            $tierCode = $supplierTierMap[$supplier->code] ?? 'BRONZE';
            $tierId = $tiers[$tierCode] ?? null;

            if ($tierId) {
                DB::table('suppliers')->where('id', $supplier->id)->update([
                    'current_tier_id' => $tierId,
                    'tier_assigned_at' => now()->subMonths(3),
                    'tier_evaluation_due_at' => now()->addMonth(),
                    'commission_structure_id' => $standardId,
                    'settlement_schedule' => $tierCode === 'GOLD' ? 'weekly' : ($tierCode === 'SILVER' ? 'bi_weekly' : 'monthly'),
                    'updated_at' => now(),
                ]);
            }

            // Assign per-product commission buckets to some supplier_products
            $supplierProducts = DB::table('supplier_products')
                ->where('supplier_id', $supplier->id)
                ->get();

            foreach ($supplierProducts as $i => $sp) {
                $commissionId = null;

                // High-cost products get premium rate
                if ($sp->cost_price >= 200 && $premiumId) {
                    $commissionId = $premiumId;
                }

                // Liquidation supplier products get liquidation rate
                if ($supplier->code === 'ELIQ-002' && $liquidationId) {
                    $commissionId = $liquidationId;
                }

                if ($commissionId) {
                    DB::table('supplier_products')
                        ->where('id', $sp->id)
                        ->update(['commission_structure_id' => $commissionId]);
                }
            }
        }
    }

    private function seedSettlements($suppliers, array $commissions, $products, $orgId): void
    {
        $standardId = $commissions['Standard Consignment'] ?? null;
        $standardRate = 15.0;

        foreach ($suppliers as $supplier) {
            // 6 months: July 2025 - Dec 2025, plus Jan 2026 draft
            for ($monthOffset = 6; $monthOffset >= 0; $monthOffset--) {
                $periodStart = Carbon::parse('2026-01-01')->subMonths($monthOffset)->startOfMonth();
                $periodEnd = $periodStart->copy()->endOfMonth();

                $status = match (true) {
                    $monthOffset >= 4 => 'paid',
                    $monthOffset >= 2 => 'approved',
                    $monthOffset === 1 => 'pending_review',
                    default => 'draft',
                };

                $existing = DB::table('settlements')
                    ->where('supplier_id', $supplier->id)
                    ->where('settlement_period_start', $periodStart->toDateString())
                    ->exists();

                if ($existing) {
                    continue;
                }

                $settlementId = Str::uuid()->toString();
                $settlementNumber = 'SET-'.$periodStart->format('Ymd').'-'.strtoupper(substr(md5($supplier->id.$monthOffset), 0, 6));

                $itemCount = rand(5, 15);
                $selectedProducts = $products->count() >= $itemCount
                    ? $products->random($itemCount)
                    : $products;

                $grossSales = 0;
                $returnsAmount = 0;
                $totalCommission = 0;
                $itemsSold = 0;
                $items = [];

                foreach ($selectedProducts as $product) {
                    $salePrice = round(rand(2000, 80000) / 100, 2); // $20 - $800
                    $isReturned = rand(1, 20) === 1; // ~5%
                    $commissionRate = $standardRate;
                    $commissionAmount = round($salePrice * ($commissionRate / 100), 2);
                    $supplierPayout = round($salePrice - $commissionAmount, 2);

                    $grossSales += $salePrice;
                    if ($isReturned) {
                        $returnsAmount += $salePrice;
                    }
                    $totalCommission += $isReturned ? 0 : $commissionAmount;
                    $itemsSold++;

                    $saleDate = $periodStart->copy()->addDays(rand(0, $periodStart->daysInMonth - 1));

                    $items[] = [
                        'id' => Str::uuid()->toString(),
                        'settlement_id' => $settlementId,
                        'product_id' => $product->id,
                        'sale_date' => $saleDate,
                        'sale_reference' => 'ORD-'.strtoupper(Str::random(8)),
                        'marketplace' => collect(['ebay', 'direct', 'shopify'])->random(),
                        'sale_price' => $salePrice,
                        'original_cost' => round($salePrice * (0.4 + rand(0, 20) / 100), 2),
                        'shipping_collected' => round(rand(0, 1500) / 100, 2),
                        'tax_collected' => round($salePrice * 0.07, 2),
                        'marketplace_fees' => round($salePrice * 0.13, 2),
                        'net_sale_amount' => $salePrice,
                        'commission_structure_id' => $standardId,
                        'commission_type' => 'percentage',
                        'commission_rate' => $commissionRate,
                        'commission_amount' => $isReturned ? 0 : $commissionAmount,
                        'tier_adjustment' => 0,
                        'bonus_amount' => 0,
                        'final_commission' => $isReturned ? 0 : $commissionAmount,
                        'supplier_payout' => $isReturned ? 0 : $supplierPayout,
                        'status' => $isReturned ? 'voided' : 'approved',
                        'is_returned' => $isReturned,
                        'return_date' => $isReturned ? $saleDate->copy()->addDays(rand(3, 14)) : null,
                        'refund_amount' => $isReturned ? $salePrice : null,
                        'return_reason' => $isReturned ? collect(['Defective', 'Not as described', 'Changed mind'])->random() : null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                $netSales = $grossSales - $returnsAmount;
                $supplierPayout = $netSales - $totalCommission;
                $paymentDate = $status === 'paid' ? $periodEnd->copy()->addDays(rand(5, 15)) : null;

                DB::table('settlements')->insert([
                    'id' => $settlementId,
                    'organization_id' => $orgId,
                    'supplier_id' => $supplier->id,
                    'settlement_number' => $settlementNumber,
                    'settlement_period_start' => $periodStart,
                    'settlement_period_end' => $periodEnd,
                    'period_type' => 'monthly',
                    'total_sales_count' => $itemsSold,
                    'total_items_sold' => $itemsSold,
                    'gross_sales_amount' => round($grossSales, 2),
                    'returns_amount' => round($returnsAmount, 2),
                    'net_sales_amount' => round($netSales, 2),
                    'commission_amount' => round($totalCommission, 2),
                    'fees_deducted' => 0,
                    'adjustments' => 0,
                    'bonuses' => 0,
                    'net_amount' => round($supplierPayout, 2),
                    'tax_withheld' => 0,
                    'status' => $status,
                    'payment_method' => $status === 'paid' ? 'ach' : null,
                    'payment_reference' => $status === 'paid' ? 'ACH-'.strtoupper(Str::random(10)) : null,
                    'payment_date' => $paymentDate,
                    'payment_due_date' => $periodEnd->copy()->addDays(15),
                    'notes' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Insert settlement items in chunks
                foreach (array_chunk($items, 50) as $chunk) {
                    DB::table('settlement_items')->insert($chunk);
                }
            }
        }
    }

    private function seedPerformanceMetrics($suppliers, array $tiers, $orgId): void
    {
        $supplierScores = [
            'NCR-AUTH-001' => ['base' => 88, 'quality' => 92, 'compliance' => 90],
            'POSCOMP-003' => ['base' => 82, 'quality' => 85, 'compliance' => 80],
            'CEW-004' => ['base' => 78, 'quality' => 80, 'compliance' => 76],
            'ELIQ-002' => ['base' => 70, 'quality' => 68, 'compliance' => 72],
        ];

        foreach ($suppliers as $supplier) {
            $scores = $supplierScores[$supplier->code] ?? ['base' => 75, 'quality' => 75, 'compliance' => 75];

            for ($monthOffset = 5; $monthOffset >= 0; $monthOffset--) {
                $periodStart = Carbon::parse('2025-07-01')->addMonths(5 - $monthOffset)->startOfMonth();
                $periodEnd = $periodStart->copy()->endOfMonth();

                $exists = DB::table('supplier_performance_metrics')
                    ->where('supplier_id', $supplier->id)
                    ->where('evaluation_period_start', $periodStart->toDateString())
                    ->exists();

                if ($exists) {
                    continue;
                }

                // Slight random variation per month
                $variation = rand(-3, 3);
                $qualityScore = min(100, max(0, $scores['quality'] + $variation));
                $complianceScore = min(100, max(0, $scores['compliance'] + rand(-2, 2)));
                $overallScore = min(100, max(0, $scores['base'] + $variation));

                $submissions = rand(15, 60);
                $approved = (int) round($submissions * (0.8 + rand(0, 15) / 100));
                $rejected = $submissions - $approved;
                $itemsListed = $approved;
                $itemsSold = (int) round($itemsListed * (0.5 + rand(0, 30) / 100));
                $totalRevenue = round($itemsSold * rand(5000, 30000) / 100, 2);
                $returns = (int) round($itemsSold * rand(2, 8) / 100);

                $currentTierId = $supplier->current_tier_id;

                DB::table('supplier_performance_metrics')->insert([
                    'id' => Str::uuid()->toString(),
                    'organization_id' => $orgId,
                    'supplier_id' => $supplier->id,
                    'evaluation_period_start' => $periodStart,
                    'evaluation_period_end' => $periodEnd,
                    'period_type' => 'monthly',
                    'total_submissions' => $submissions,
                    'approved_submissions' => $approved,
                    'rejected_submissions' => $rejected,
                    'pending_submissions' => 0,
                    'approval_rate' => round($approved / $submissions * 100, 2),
                    'total_items_listed' => $itemsListed,
                    'total_items_sold' => $itemsSold,
                    'sell_through_rate' => $itemsListed > 0 ? round($itemsSold / $itemsListed * 100, 2) : 0,
                    'total_revenue' => $totalRevenue,
                    'average_sale_price' => $itemsSold > 0 ? round($totalRevenue / $itemsSold, 2) : 0,
                    'average_days_to_sell' => rand(5, 30),
                    'total_returns' => $returns,
                    'return_rate' => $itemsSold > 0 ? round($returns / $itemsSold * 100, 2) : 0,
                    'return_value' => round($returns * rand(3000, 15000) / 100, 2),
                    'quality_score' => $qualityScore,
                    'accuracy_score' => min(100, max(0, $qualityScore + rand(-5, 5))),
                    'condition_accuracy' => min(100, max(0, $qualityScore + rand(-3, 3))),
                    'packaging_score' => min(100, max(0, $qualityScore + rand(-5, 5))),
                    'compliance_score' => $complianceScore,
                    'policy_violations' => rand(0, 2),
                    'late_submissions' => rand(0, 3),
                    'documentation_issues' => rand(0, 2),
                    'customer_rating' => round(3.5 + rand(0, 15) / 10, 1),
                    'customer_complaints' => rand(0, 3),
                    'positive_feedback_count' => rand(5, 30),
                    'negative_feedback_count' => rand(0, 3),
                    'overall_performance_score' => $overallScore,
                    'score_breakdown' => json_encode([
                        'quality' => $qualityScore,
                        'compliance' => $complianceScore,
                        'sales' => min(100, max(0, $overallScore + rand(-5, 5))),
                    ]),
                    'current_tier_id' => $currentTierId,
                    'status' => 'finalized',
                    'calculated_at' => $periodEnd->copy()->addDays(2),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function updateCachedBalances($suppliers, $orgId): void
    {
        foreach ($suppliers as $supplier) {
            $ytdStart = Carbon::parse('2026-01-01');

            $ytdSales = DB::table('settlements')
                ->where('supplier_id', $supplier->id)
                ->where('settlement_period_start', '>=', $ytdStart)
                ->sum('gross_sales_amount');

            $totalCommissions = DB::table('settlements')
                ->where('supplier_id', $supplier->id)
                ->where('status', 'paid')
                ->sum('commission_amount');

            $pendingBalance = DB::table('settlements')
                ->where('supplier_id', $supplier->id)
                ->whereNotIn('status', ['paid', 'cancelled'])
                ->sum('net_amount');

            $allTimeSales = DB::table('settlements')
                ->where('supplier_id', $supplier->id)
                ->sum('gross_sales_amount');

            $latestPerf = DB::table('supplier_performance_metrics')
                ->where('supplier_id', $supplier->id)
                ->orderBy('evaluation_period_end', 'desc')
                ->first();

            DB::table('suppliers')->where('id', $supplier->id)->update([
                'total_sales_ytd' => round($ytdSales, 2),
                'total_sales_all_time' => round($allTimeSales, 2),
                'total_commissions_paid' => round($totalCommissions, 2),
                'pending_balance' => round($pendingBalance, 2),
                'available_balance' => round($pendingBalance * 0.8, 2),
                'cached_performance_score' => $latestPerf ? (int) $latestPerf->overall_performance_score : null,
                'cached_quality_score' => $latestPerf ? (int) $latestPerf->quality_score : null,
                'performance_calculated_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
