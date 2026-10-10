<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Http\Resources\Supplier\SettlementResource;
use App\Models\Inventory\Supplier;
use App\Models\Supplier\Settlement;
use App\Models\Supplier\SettlementItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SettlementController extends Controller
{
    /**
     * Display settlement history
     */
    public function index(Request $request)
    {
        $supplier = $this->getAuthenticatedSupplier();

        if (! $supplier) {
            return redirect()->route('dashboard')
                ->with('error', 'Supplier account not found.');
        }

        $query = Settlement::forSupplier($supplier->id)
            ->with(['items']);

        // Apply filters
        if ($request->filled('status')) {
            $query->status($request->input('status'));
        }

        if ($request->filled('date_from')) {
            $query->where('settlement_period_start', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('settlement_period_end', '<=', $request->input('date_to'));
        }

        $settlements = $query->orderBy('settlement_period_end', 'desc')
            ->paginate($request->input('per_page', 15));

        // Get summary stats
        $summary = $this->getSettlementSummary($supplier);

        return Inertia::render('Supplier/Settlements', [
            'settlements' => SettlementResource::collection($settlements),
            'summary' => $summary,
            'filters' => $request->only(['status', 'date_from', 'date_to']),
        ]);
    }

    /**
     * Display a specific settlement
     */
    public function show(Request $request, $settlementId)
    {
        $supplier = $this->getAuthenticatedSupplier();

        $settlement = Settlement::where('id', $settlementId)
            ->where('supplier_id', $supplier->id)
            ->with(['items.product', 'reviewedBy', 'approvedBy'])
            ->firstOrFail();

        if ($request->wantsJson()) {
            return response()->json(new SettlementResource($settlement));
        }

        return Inertia::render('Supplier/SettlementDetail', [
            'settlement' => new SettlementResource($settlement),
        ]);
    }

    /**
     * Get settlement items
     */
    public function items(Request $request, $settlementId)
    {
        $supplier = $this->getAuthenticatedSupplier();

        $settlement = Settlement::where('id', $settlementId)
            ->where('supplier_id', $supplier->id)
            ->firstOrFail();

        $query = SettlementItem::where('settlement_id', $settlement->id)
            ->with(['product']);

        // Apply filters
        if ($request->filled('status')) {
            $query->status($request->input('status'));
        }

        if ($request->filled('returned')) {
            $query->where('is_returned', $request->boolean('returned'));
        }

        $items = $query->orderBy('sale_date', 'desc')
            ->paginate($request->input('per_page', 50));

        return response()->json([
            'items' => $items,
            'summary' => [
                'total_items' => $settlement->items()->count(),
                'approved_items' => $settlement->items()->where('status', 'approved')->count(),
                'returned_items' => $settlement->items()->where('is_returned', true)->count(),
            ],
        ]);
    }

    /**
     * Get pending settlements
     */
    public function pending(Request $request)
    {
        $supplier = $this->getAuthenticatedSupplier();

        $pendingSettlements = Settlement::forSupplier($supplier->id)
            ->pending()
            ->with(['items'])
            ->orderBy('settlement_period_end', 'desc')
            ->get();

        $totalPending = $pendingSettlements->sum('net_amount');

        return response()->json([
            'settlements' => SettlementResource::collection($pendingSettlements),
            'total_pending' => round($totalPending, 2),
            'count' => $pendingSettlements->count(),
        ]);
    }

    /**
     * Get paid settlements
     */
    public function paid(Request $request)
    {
        $supplier = $this->getAuthenticatedSupplier();

        $query = Settlement::forSupplier($supplier->id)
            ->paid()
            ->with(['items']);

        // Year filter
        if ($request->filled('year')) {
            $query->whereYear('payment_date', $request->input('year'));
        }

        $paidSettlements = $query->orderBy('payment_date', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'settlements' => SettlementResource::collection($paidSettlements),
            'pagination' => [
                'current_page' => $paidSettlements->currentPage(),
                'last_page' => $paidSettlements->lastPage(),
                'per_page' => $paidSettlements->perPage(),
                'total' => $paidSettlements->total(),
            ],
        ]);
    }

    /**
     * Dispute a settlement
     */
    public function dispute(Request $request, $settlementId)
    {
        $request->validate([
            'reason' => 'required|string|max:2000',
        ]);

        $supplier = $this->getAuthenticatedSupplier();

        $settlement = Settlement::where('id', $settlementId)
            ->where('supplier_id', $supplier->id)
            ->firstOrFail();

        if (! $settlement->canBeDisputed()) {
            return response()->json([
                'error' => 'This settlement cannot be disputed in its current status.',
            ], 422);
        }

        $settlement->dispute($request->input('reason'));

        return response()->json([
            'message' => 'Settlement has been disputed. Our team will review it shortly.',
            'settlement' => new SettlementResource($settlement->fresh()),
        ]);
    }

    /**
     * Get settlement statistics
     */
    public function statistics(Request $request)
    {
        $supplier = $this->getAuthenticatedSupplier();

        $stats = $this->getDetailedStatistics($supplier);

        return response()->json($stats);
    }

    /**
     * Download settlement as PDF
     */
    public function downloadPdf(Request $request, $settlementId)
    {
        $supplier = $this->getAuthenticatedSupplier();

        $settlement = Settlement::where('id', $settlementId)
            ->where('supplier_id', $supplier->id)
            ->with(['items.product', 'supplier', 'organization'])
            ->firstOrFail();

        $pdf = Pdf::loadView('pdf.settlement', [
            'settlement' => $settlement,
            'items' => $settlement->items,
            'supplier' => $supplier,
        ]);

        return $pdf->download("settlement-{$settlement->settlement_number}.pdf");
    }

    /**
     * Export settlements to CSV
     */
    public function exportCsv(Request $request)
    {
        $supplier = $this->getAuthenticatedSupplier();

        $query = Settlement::forSupplier($supplier->id);

        // Apply date filters
        if ($request->filled('date_from')) {
            $query->where('settlement_period_start', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('settlement_period_end', '<=', $request->input('date_to'));
        }

        $settlements = $query->orderBy('settlement_period_end', 'desc')->get();

        $csvData = [];
        $csvData[] = [
            'Settlement Number',
            'Period Start',
            'Period End',
            'Status',
            'Gross Sales',
            'Returns',
            'Net Sales',
            'Commission',
            'Fees',
            'Adjustments',
            'Net Amount',
            'Payment Date',
            'Payment Method',
        ];

        foreach ($settlements as $settlement) {
            $csvData[] = [
                $settlement->settlement_number,
                $settlement->settlement_period_start->format('Y-m-d'),
                $settlement->settlement_period_end->format('Y-m-d'),
                $settlement->status->value,
                $settlement->gross_sales,
                $settlement->returns_amount,
                $settlement->net_sales,
                $settlement->commission_amount,
                $settlement->fees_amount,
                $settlement->adjustments_amount,
                $settlement->net_amount,
                $settlement->payment_date?->format('Y-m-d'),
                $settlement->payment_method,
            ];
        }

        $filename = 'settlements-'.now()->format('Y-m-d').'.csv';
        $handle = fopen('php://temp', 'r+');

        foreach ($csvData as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    /**
     * Get upcoming settlement preview
     */
    public function preview(Request $request)
    {
        $supplier = $this->getAuthenticatedSupplier();

        // Get unsettled items
        $unsettledItems = DB::table('supplier_products')
            ->where('supplier_id', $supplier->id)
            ->where('status', 'sold')
            ->whereNull('settlement_id')
            ->selectRaw('
                COUNT(*) as item_count,
                SUM(sale_price) as gross_sales,
                SUM(commission_amount) as commission
            ')
            ->first();

        $estimatedPayout = ($unsettledItems->gross_sales ?? 0) - ($unsettledItems->commission ?? 0);

        return response()->json([
            'next_settlement_date' => $supplier->next_settlement_date,
            'settlement_schedule' => $supplier->settlement_schedule,
            'preview' => [
                'item_count' => $unsettledItems->item_count ?? 0,
                'gross_sales' => round($unsettledItems->gross_sales ?? 0, 2),
                'estimated_commission' => round($unsettledItems->commission ?? 0, 2),
                'estimated_payout' => round($estimatedPayout, 2),
            ],
            'note' => 'This is an estimate and may change based on returns and adjustments.',
        ]);
    }

    /**
     * Get tax documents (1099s)
     */
    public function taxDocuments(Request $request)
    {
        $supplier = $this->getAuthenticatedSupplier();
        $year = $request->input('year', now()->year - 1);

        // Get year summary
        $yearSummary = Settlement::forSupplier($supplier->id)
            ->paid()
            ->whereYear('payment_date', $year)
            ->selectRaw('
                SUM(net_amount) as total_paid,
                COUNT(*) as settlement_count
            ')
            ->first();

        // Get 1099 threshold status
        $threshold1099 = 600; // IRS threshold
        $requiresForm = ($yearSummary->total_paid ?? 0) >= $threshold1099;

        // Check for existing tax documents
        $taxDocuments = \App\Models\Supplier\SupplierDocument::where('supplier_id', $supplier->id)
            ->where('category', 'tax_form_1099')
            ->whereYear('created_at', $year + 1) // 1099s issued in following year
            ->get();

        $query = Settlement::forSupplier($supplier->id)
            ->with(['items']);

        if ($request->filled('status')) {
            $query->status($request->input('status'));
        }

        if ($request->filled('date_from')) {
            $query->where('settlement_period_start', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('settlement_period_end', '<=', $request->input('date_to'));
        }

        $settlements = $query->orderBy('settlement_period_end', 'desc')
            ->paginate($request->input('per_page', 15));

        $summary = $this->getSettlementSummary($supplier);

        return Inertia::render('Supplier/Settlements', [
            'settlements' => SettlementResource::collection($settlements),
            'summary' => $summary,
            'filters' => $request->only(['status', 'date_from', 'date_to']),
            'taxDocuments' => $taxDocuments->map(fn ($doc) => [
                'id' => $doc->id,
                'year' => $year,
                'name' => $doc->name,
                'category' => $doc->category,
                'created_at' => $doc->created_at,
            ]),
        ]);
    }

    // ============================================
    // Protected Helper Methods
    // ============================================

    /**
     * Get the authenticated supplier
     */
    protected function getAuthenticatedSupplier(): ?Supplier
    {
        $user = Auth::user();

        if ($user->supplier_id) {
            return Supplier::find($user->supplier_id);
        }

        return Supplier::where('email', $user->email)
            ->orWhere('user_id', $user->id)
            ->first();
    }

    /**
     * Get settlement summary
     */
    protected function getSettlementSummary(Supplier $supplier): array
    {
        $stats = Settlement::forSupplier($supplier->id)
            ->selectRaw("
                COUNT(*) as total_settlements,
                SUM(CASE WHEN status IN ('approved', 'pending_payment', 'processing') THEN net_amount ELSE 0 END) as pending_amount,
                SUM(CASE WHEN status = 'paid' THEN net_amount ELSE 0 END) as paid_amount,
                SUM(CASE WHEN status = 'disputed' THEN 1 ELSE 0 END) as disputed_count
            ")
            ->first();

        // YTD paid
        $ytdPaid = Settlement::forSupplier($supplier->id)
            ->paid()
            ->whereYear('payment_date', now()->year)
            ->sum('net_amount');

        return [
            'total_settlements' => $stats->total_settlements ?? 0,
            'pending_amount' => round($stats->pending_amount ?? 0, 2),
            'total_paid' => round($stats->paid_amount ?? 0, 2),
            'disputed_count' => $stats->disputed_count ?? 0,
            'ytd_paid' => round($ytdPaid, 2),
            'next_settlement' => $supplier->next_settlement_date,
        ];
    }

    /**
     * Get detailed statistics
     */
    protected function getDetailedStatistics(Supplier $supplier): array
    {
        $now = now();

        // Monthly breakdown for current year
        $monthlyData = Settlement::forSupplier($supplier->id)
            ->paid()
            ->whereYear('payment_date', $now->year)
            ->selectRaw('
                EXTRACT(MONTH FROM payment_date) as month,
                SUM(net_amount) as total,
                COUNT(*) as count
            ')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $monthlyBreakdown = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthlyBreakdown[] = [
                'month' => date('M', mktime(0, 0, 0, $m, 1)),
                'total' => round($monthlyData->get($m)?->total ?? 0, 2),
                'count' => $monthlyData->get($m)?->count ?? 0,
            ];
        }

        // Year over year comparison
        $lastYear = Settlement::forSupplier($supplier->id)
            ->paid()
            ->whereYear('payment_date', $now->year - 1)
            ->sum('net_amount');

        $thisYear = Settlement::forSupplier($supplier->id)
            ->paid()
            ->whereYear('payment_date', $now->year)
            ->sum('net_amount');

        // Average settlement
        $avgSettlement = Settlement::forSupplier($supplier->id)
            ->paid()
            ->avg('net_amount');

        // Payment method breakdown
        $paymentMethods = Settlement::forSupplier($supplier->id)
            ->paid()
            ->whereNotNull('payment_method')
            ->selectRaw('payment_method, COUNT(*) as count, SUM(net_amount) as total')
            ->groupBy('payment_method')
            ->get();

        return [
            'monthly_breakdown' => $monthlyBreakdown,
            'year_comparison' => [
                'last_year' => round($lastYear, 2),
                'this_year' => round($thisYear, 2),
                'change_percent' => $lastYear > 0
                    ? round((($thisYear - $lastYear) / $lastYear) * 100, 1)
                    : 0,
            ],
            'average_settlement' => round($avgSettlement ?? 0, 2),
            'payment_methods' => $paymentMethods->map(fn ($pm) => [
                'method' => $pm->payment_method,
                'count' => $pm->count,
                'total' => round($pm->total, 2),
            ]),
        ];
    }

    /**
     * Check if supplier has W9 on file
     */
    protected function hasW9OnFile(Supplier $supplier): bool
    {
        return \App\Models\Supplier\SupplierDocument::where('supplier_id', $supplier->id)
            ->where('category', 'w9_form')
            ->where('verification_status', 'verified')
            ->exists();
    }
}
