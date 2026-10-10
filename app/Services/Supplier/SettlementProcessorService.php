<?php

namespace App\Services\Supplier;

use App\Enums\Supplier\SettlementFrequency;
use App\Enums\Supplier\SettlementStatus;
use App\Models\Catalog\Product;
use App\Models\Inventory\Supplier;
use App\Models\Supplier\Settlement;
use App\Models\Supplier\SettlementItem;
use App\Models\Supplier\SupplierNotification;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SettlementProcessorService
{
    protected CommissionCalculatorService $commissionCalculator;

    public function __construct(CommissionCalculatorService $commissionCalculator)
    {
        $this->commissionCalculator = $commissionCalculator;
    }

    /**
     * Generate settlement for a supplier
     */
    public function generateSettlement(
        Supplier $supplier,
        Carbon $periodStart,
        Carbon $periodEnd,
        ?int $createdById = null
    ): Settlement {
        return DB::transaction(function () use ($supplier, $periodStart, $periodEnd) {
            // Check for existing settlement in this period
            $existing = Settlement::where('supplier_id', $supplier->id)
                ->where('settlement_period_start', $periodStart)
                ->where('settlement_period_end', $periodEnd)
                ->first();

            if ($existing) {
                throw new \RuntimeException(
                    "Settlement already exists for this period: {$existing->settlement_number}"
                );
            }

            // Create the settlement
            $settlement = Settlement::createForPeriod(
                $supplier,
                $periodStart,
                $periodEnd
            );

            // Get sold products for this period
            $soldProducts = $this->getSoldProductsForPeriod($supplier, $periodStart, $periodEnd);

            // Add items to settlement
            foreach ($soldProducts as $product) {
                $this->addProductToSettlement($settlement, $product, $supplier);
            }

            // Recalculate totals
            $settlement->recalculateTotals();

            Log::info('Settlement generated', [
                'settlement_id' => $settlement->id,
                'settlement_number' => $settlement->settlement_number,
                'supplier_id' => $supplier->id,
                'period' => "{$periodStart->toDateString()} to {$periodEnd->toDateString()}",
                'items_count' => $soldProducts->count(),
                'net_amount' => $settlement->net_amount,
            ]);

            return $settlement;
        });
    }

    /**
     * Get sold products for settlement period
     */
    protected function getSoldProductsForPeriod(
        Supplier $supplier,
        Carbon $periodStart,
        Carbon $periodEnd
    ): Collection {
        // This would query the actual products/sales table
        // For now, return supplier_products that are marked as sold
        return Product::whereHas('supplierProduct', function ($query) use ($supplier) {
            $query->where('supplier_id', $supplier->id);
        })
            ->where('status', 'sold')
            ->whereNotNull('sold_at')
            ->whereBetween('sold_at', [$periodStart, $periodEnd])
            ->whereDoesntHave('settlementItem') // Not already in a settlement
            ->get();
    }

    /**
     * Add a product to settlement
     */
    protected function addProductToSettlement(
        Settlement $settlement,
        Product $product,
        Supplier $supplier
    ): SettlementItem {
        $salePrice = $product->final_price ?? $product->selling_price;

        // Calculate commission
        $commissionResult = $this->commissionCalculator->calculateCommission(
            $product,
            $supplier,
            $salePrice,
            $product->marketplace ?? null
        );

        return SettlementItem::createFromSale(
            $settlement,
            $product,
            [
                'sale_price' => $salePrice,
                'sale_date' => $product->sold_at,
                'marketplace' => $product->marketplace,
                'order_reference' => $product->order_number,
                'shipping_cost' => $product->shipping_cost ?? 0,
                'marketplace_fees' => $product->marketplace_fees ?? 0,
                'commission_result' => $commissionResult,
            ]
        );
    }

    /**
     * Process settlements for all eligible suppliers
     */
    public function processScheduledSettlements(int $organizationId): array
    {
        $results = [
            'processed' => 0,
            'skipped' => 0,
            'errors' => 0,
            'settlements' => [],
        ];

        // Get suppliers due for settlement
        $suppliers = $this->getSuppliersForSettlement($organizationId);

        foreach ($suppliers as $supplier) {
            try {
                $period = $this->getSettlementPeriod($supplier);

                // Check if there are any sales to settle
                $salesCount = $this->countUnsettledSales($supplier, $period['start'], $period['end']);

                if ($salesCount === 0) {
                    $results['skipped']++;

                    continue;
                }

                $settlement = $this->generateSettlement(
                    $supplier,
                    $period['start'],
                    $period['end']
                );

                // Update supplier's next settlement date
                $supplier->update([
                    'next_settlement_date' => $this->calculateNextSettlementDate($supplier),
                ]);

                $results['processed']++;
                $results['settlements'][] = [
                    'settlement_id' => $settlement->id,
                    'settlement_number' => $settlement->settlement_number,
                    'supplier_id' => $supplier->id,
                    'amount' => $settlement->net_amount,
                ];

            } catch (\Exception $e) {
                $results['errors']++;
                Log::error('Error processing settlement for supplier', [
                    'supplier_id' => $supplier->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Get suppliers due for settlement
     */
    protected function getSuppliersForSettlement(int $organizationId): Collection
    {
        return Supplier::where('organization_id', $organizationId)
            ->where('supplier_status', 'active')
            ->where(function ($query) {
                $query->whereNull('next_settlement_date')
                    ->orWhere('next_settlement_date', '<=', now());
            })
            ->get();
    }

    /**
     * Get settlement period for supplier
     */
    protected function getSettlementPeriod(Supplier $supplier): array
    {
        $frequency = SettlementFrequency::tryFrom($supplier->settlement_schedule)
            ?? SettlementFrequency::MONTHLY;

        $today = now();

        return match ($frequency) {
            SettlementFrequency::PER_SALE => [
                'start' => $today->copy()->startOfDay(),
                'end' => $today->copy()->endOfDay(),
            ],
            SettlementFrequency::DAILY => [
                'start' => $today->copy()->subDay()->startOfDay(),
                'end' => $today->copy()->subDay()->endOfDay(),
            ],
            SettlementFrequency::WEEKLY => [
                'start' => $today->copy()->subWeek()->startOfWeek(),
                'end' => $today->copy()->subWeek()->endOfWeek(),
            ],
            SettlementFrequency::BI_WEEKLY => [
                'start' => $today->copy()->subWeeks(2)->startOfWeek(),
                'end' => $today->copy()->subDay()->endOfDay(),
            ],
            SettlementFrequency::MONTHLY => [
                'start' => $today->copy()->subMonth()->startOfMonth(),
                'end' => $today->copy()->subMonth()->endOfMonth(),
            ],
            SettlementFrequency::QUARTERLY => [
                'start' => $today->copy()->subQuarter()->startOfQuarter(),
                'end' => $today->copy()->subQuarter()->endOfQuarter(),
            ],
            default => [
                'start' => $today->copy()->subMonth()->startOfMonth(),
                'end' => $today->copy()->subMonth()->endOfMonth(),
            ],
        };
    }

    /**
     * Calculate next settlement date
     */
    protected function calculateNextSettlementDate(Supplier $supplier): Carbon
    {
        $frequency = SettlementFrequency::tryFrom($supplier->settlement_schedule)
            ?? SettlementFrequency::MONTHLY;

        return $frequency->getNextSettlementDate(
            now(),
            $supplier->settlement_day ?? 1
        );
    }

    /**
     * Count unsettled sales for period
     */
    protected function countUnsettledSales(
        Supplier $supplier,
        Carbon $periodStart,
        Carbon $periodEnd
    ): int {
        return Product::whereHas('supplierProduct', function ($query) use ($supplier) {
            $query->where('supplier_id', $supplier->id);
        })
            ->where('status', 'sold')
            ->whereNotNull('sold_at')
            ->whereBetween('sold_at', [$periodStart, $periodEnd])
            ->whereDoesntHave('settlementItem')
            ->count();
    }

    /**
     * Submit settlement for review
     */
    public function submitForReview(Settlement $settlement): Settlement
    {
        if (! $settlement->canBeEdited()) {
            throw new \RuntimeException('Settlement cannot be submitted for review in current status');
        }

        $settlement->update([
            'status' => SettlementStatus::PENDING_REVIEW->value,
        ]);

        return $settlement;
    }

    /**
     * Approve settlement
     */
    public function approveSettlement(Settlement $settlement, int $approverId): Settlement
    {
        if ($settlement->status !== SettlementStatus::PENDING_REVIEW->value) {
            throw new \RuntimeException('Settlement must be pending review to approve');
        }

        $settlement->approve($approverId);

        // Notify supplier
        SupplierNotification::settlementReady($settlement->supplier, $settlement);

        return $settlement;
    }

    /**
     * Process payment for settlement
     */
    public function processPayment(
        Settlement $settlement,
        string $paymentMethod,
        array $paymentDetails = []
    ): Settlement {
        if (! in_array($settlement->status, [
            SettlementStatus::APPROVED->value,
            SettlementStatus::PENDING_PAYMENT->value,
        ])) {
            throw new \RuntimeException('Settlement must be approved to process payment');
        }

        return DB::transaction(function () use ($settlement, $paymentMethod, $paymentDetails) {
            $settlement->markAsPendingPayment($paymentMethod);
            $settlement->markAsProcessing();

            // Here you would integrate with actual payment processing
            // For now, simulate successful payment

            try {
                // Simulate payment processing
                $paymentReference = $this->executePayment($settlement, $paymentMethod, $paymentDetails);

                $settlement->markAsPaid($paymentReference, $paymentDetails);

                // Notify supplier
                SupplierNotification::paymentProcessed($settlement->supplier, $settlement);

                // Update supplier's financial cache
                $this->updateSupplierFinancials($settlement->supplier);

                Log::info('Settlement payment processed', [
                    'settlement_id' => $settlement->id,
                    'settlement_number' => $settlement->settlement_number,
                    'payment_method' => $paymentMethod,
                    'payment_reference' => $paymentReference,
                    'amount' => $settlement->net_amount,
                ]);

            } catch (\Exception $e) {
                $settlement->markAsFailed($e->getMessage());
                throw $e;
            }

            return $settlement;
        });
    }

    /**
     * Generate a payment reference ID for manual payment tracking.
     *
     * Payments to suppliers are processed externally (bank ACH, wire, PayPal)
     * and recorded here with the generated reference. The settlement workflow
     * (generate -> review -> approve -> mark paid) handles the full lifecycle;
     * this method provides a tracking reference for the payment record.
     */
    protected function executePayment(
        Settlement $settlement,
        string $paymentMethod,
        array $paymentDetails
    ): string {
        return 'PAY-'.strtoupper(uniqid());
    }

    /**
     * Update supplier's cached financial totals
     */
    protected function updateSupplierFinancials(Supplier $supplier): void
    {
        $yearStart = now()->startOfYear();

        $totals = Settlement::where('supplier_id', $supplier->id)
            ->where('status', SettlementStatus::PAID->value)
            ->where('payment_date', '>=', $yearStart)
            ->selectRaw('
                SUM(gross_sales_amount) as total_sales,
                SUM(commission_amount) as total_commission,
                COUNT(*) as total_settlements
            ')
            ->first();

        $pending = Settlement::where('supplier_id', $supplier->id)
            ->whereIn('status', [
                SettlementStatus::DRAFT->value,
                SettlementStatus::PENDING_REVIEW->value,
                SettlementStatus::APPROVED->value,
                SettlementStatus::PENDING_PAYMENT->value,
            ])
            ->sum('net_amount');

        $supplier->update([
            'total_sales_ytd' => $totals->total_sales ?? 0,
            'total_commission_ytd' => $totals->total_commission ?? 0,
            'total_settlements_ytd' => $totals->total_settlements ?? 0,
            'pending_settlement_amount' => $pending,
        ]);
    }

    /**
     * Get settlement summary for supplier
     */
    public function getSupplierSettlementSummary(
        Supplier $supplier,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): array {
        $query = Settlement::where('supplier_id', $supplier->id);

        if ($startDate) {
            $query->where('settlement_period_start', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('settlement_period_end', '<=', $endDate);
        }

        $settlements = $query->get();

        $paid = $settlements->where('status', SettlementStatus::PAID->value);
        $pending = $settlements->whereIn('status', [
            SettlementStatus::DRAFT->value,
            SettlementStatus::PENDING_REVIEW->value,
            SettlementStatus::APPROVED->value,
            SettlementStatus::PENDING_PAYMENT->value,
        ]);

        return [
            'total_settlements' => $settlements->count(),
            'paid' => [
                'count' => $paid->count(),
                'total_amount' => $paid->sum('net_amount'),
            ],
            'pending' => [
                'count' => $pending->count(),
                'total_amount' => $pending->sum('net_amount'),
            ],
            'total_commission_paid' => $paid->sum('commission_amount'),
            'total_fees_paid' => $paid->sum('fees_deducted'),
            'average_settlement' => $paid->count() > 0
                ? $paid->sum('net_amount') / $paid->count()
                : 0,
        ];
    }

    /**
     * Get pending settlements for organization
     */
    public function getPendingSettlements(int $organizationId): Collection
    {
        return Settlement::where('organization_id', $organizationId)
            ->whereIn('status', SettlementStatus::pendingStatuses())
            ->with(['supplier', 'items'])
            ->orderBy('payment_due_date')
            ->get();
    }

    /**
     * Get overdue settlements
     */
    public function getOverdueSettlements(int $organizationId): Collection
    {
        return Settlement::where('organization_id', $organizationId)
            ->whereIn('status', [
                SettlementStatus::APPROVED->value,
                SettlementStatus::PENDING_PAYMENT->value,
            ])
            ->where('payment_due_date', '<', now())
            ->with(['supplier'])
            ->orderBy('payment_due_date')
            ->get();
    }

    /**
     * Generate settlement preview (without creating)
     */
    public function previewSettlement(
        Supplier $supplier,
        Carbon $periodStart,
        Carbon $periodEnd
    ): array {
        $soldProducts = $this->getSoldProductsForPeriod($supplier, $periodStart, $periodEnd);

        $totalSales = 0;
        $totalCommission = 0;
        $items = [];

        foreach ($soldProducts as $product) {
            $salePrice = $product->final_price ?? $product->selling_price;
            $commissionResult = $this->commissionCalculator->calculateCommission(
                $product,
                $supplier,
                $salePrice
            );

            $totalSales += $salePrice;
            $totalCommission += $commissionResult['commission'];

            $items[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sale_price' => $salePrice,
                'commission' => $commissionResult['commission'],
                'supplier_payout' => $commissionResult['supplier_payout'],
            ];
        }

        return [
            'supplier_id' => $supplier->id,
            'period' => [
                'start' => $periodStart->toDateString(),
                'end' => $periodEnd->toDateString(),
            ],
            'summary' => [
                'total_items' => count($items),
                'gross_sales' => $totalSales,
                'total_commission' => $totalCommission,
                'net_amount' => $totalSales - $totalCommission,
            ],
            'items' => $items,
        ];
    }

    /**
     * Add adjustment to settlement
     */
    public function addAdjustment(
        Settlement $settlement,
        float $amount,
        string $reason,
        int $adjustedById
    ): Settlement {
        if (! $settlement->canBeEdited()) {
            throw new \RuntimeException('Settlement cannot be modified in current status');
        }

        $currentAdjustments = $settlement->adjustments ?? 0;

        $settlement->update([
            'adjustments' => $currentAdjustments + $amount,
            'internal_notes' => $settlement->internal_notes."\n[".now()->toDateTimeString()."] Adjustment: {$amount} - {$reason}",
        ]);

        $settlement->recalculateTotals();

        return $settlement;
    }

    /**
     * Dispute settlement
     */
    public function disputeSettlement(
        Settlement $settlement,
        string $reason,
        ?int $disputedById = null
    ): Settlement {
        if (! $settlement->canBeDisputed()) {
            throw new \RuntimeException('Settlement cannot be disputed in current status');
        }

        $settlement->dispute($reason);

        // Notify organization admins
        Log::info('Settlement disputed', [
            'settlement_id' => $settlement->id,
            'settlement_number' => $settlement->settlement_number,
            'reason' => $reason,
        ]);

        return $settlement;
    }

    /**
     * Resolve settlement dispute
     */
    public function resolveDispute(
        Settlement $settlement,
        string $resolution,
        float $adjustmentAmount,
        int $resolvedById
    ): Settlement {
        if (! $settlement->is_disputed) {
            throw new \RuntimeException('Settlement is not disputed');
        }

        $settlement->resolveDispute($resolution, $adjustmentAmount, $resolvedById);

        return $settlement;
    }

    /**
     * Cancel settlement
     */
    public function cancelSettlement(
        Settlement $settlement,
        string $reason,
        int $cancelledById
    ): Settlement {
        if (! $settlement->canBeCancelled()) {
            throw new \RuntimeException('Settlement cannot be cancelled in current status');
        }

        $settlement->cancel($reason);

        Log::info('Settlement cancelled', [
            'settlement_id' => $settlement->id,
            'settlement_number' => $settlement->settlement_number,
            'reason' => $reason,
            'cancelled_by' => $cancelledById,
        ]);

        return $settlement;
    }

    /**
     * Get settlement analytics for organization
     */
    public function getSettlementAnalytics(
        int $organizationId,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        $settlements = Settlement::where('organization_id', $organizationId)
            ->whereBetween('settlement_period_start', [$startDate, $endDate])
            ->get();

        return [
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'summary' => [
                'total_settlements' => $settlements->count(),
                'total_gross_sales' => $settlements->sum('gross_sales_amount'),
                'total_commission' => $settlements->sum('commission_amount'),
                'total_paid_out' => $settlements->where('status', SettlementStatus::PAID->value)->sum('net_amount'),
            ],
            'by_status' => $settlements->groupBy('status')->map(function ($group, $status) {
                return [
                    'count' => $group->count(),
                    'total_amount' => $group->sum('net_amount'),
                ];
            }),
            'by_payment_method' => $settlements
                ->where('status', SettlementStatus::PAID->value)
                ->groupBy('payment_method')
                ->map(function ($group, $method) {
                    return [
                        'count' => $group->count(),
                        'total_amount' => $group->sum('net_amount'),
                    ];
                }),
        ];
    }
}
