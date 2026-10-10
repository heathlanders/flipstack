<?php

namespace App\Http\Controllers\Printing;

use App\Http\Controllers\Controller;
use App\Models\Printing\LabelTemplate;
use App\Models\Printing\OrganizationPrinter;
use App\Models\Printing\PrintJob;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * Display the printing system dashboard
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $orgId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        $printerQuery = OrganizationPrinter::where('organization_id', $orgId);
        $jobQuery = PrintJob::where('organization_id', $orgId);

        $completedToday = (clone $jobQuery)->where('status', 'completed')->whereDate('completed_at', today())->count();
        $labelsPrintedToday = (clone $jobQuery)->where('status', 'completed')->whereDate('completed_at', today())->sum('quantity');
        $labelsPrintedWeek = (clone $jobQuery)->where('status', 'completed')->where('completed_at', '>=', now()->startOfWeek())->sum('quantity');
        $labelsPrintedMonth = (clone $jobQuery)->where('status', 'completed')->where('completed_at', '>=', now()->startOfMonth())->sum('quantity');

        $stats = [
            'total_printers' => (clone $printerQuery)->count(),
            'active_printers' => (clone $printerQuery)->where('is_active', true)->count(),
            'offline_printers' => (clone $printerQuery)->where('status', 'offline')->count(),
            'pending_jobs' => (clone $jobQuery)->where('status', 'pending')->count(),
            'completed_jobs_today' => $completedToday,
            'failed_jobs' => (clone $jobQuery)->where('status', 'failed')->whereDate('created_at', today())->count(),
            'labels_printed_today' => (int) $labelsPrintedToday,
            'labels_printed_week' => (int) $labelsPrintedWeek,
            'labels_printed_month' => (int) $labelsPrintedMonth,
            'total_templates' => LabelTemplate::where('organization_id', $orgId)->count(),
            'active_templates' => LabelTemplate::where('organization_id', $orgId)->where('is_active', true)->count(),
            'warehouse_locations' => \App\Models\Inventory\WarehouseLocation::where('organization_id', $orgId)->count(),
        ];

        $recentActivity = PrintJob::where('organization_id', $orgId)
            ->with(['printer:id,name', 'product:id,name,sku', 'creator:id,name'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($job) => [
                'id' => $job->id,
                'type' => 'print_job',
                'message' => "Printed {$job->quantity} label(s)".($job->product ? " for {$job->product->sku}" : ''),
                'user' => $job->creator?->name ?? 'System',
                'timestamp' => $job->created_at->toDateTimeString(),
                'status' => $job->status,
            ]);

        $printerStatus = OrganizationPrinter::where('organization_id', $orgId)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'status' => $p->status,
                'last_used' => $p->last_used_at?->toDateTimeString(),
                'jobs_today' => $p->printJobs()->whereDate('created_at', today())->count(),
            ]);

        $alerts = [];
        $offlinePrinters = OrganizationPrinter::where('organization_id', $orgId)
            ->where('is_active', true)
            ->where('status', 'offline')
            ->get();
        foreach ($offlinePrinters as $p) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "Printer \"{$p->name}\" is offline",
                'action_url' => route('printing.printers.show', $p->id),
            ];
        }

        $failedRecent = PrintJob::where('organization_id', $orgId)
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subDay())
            ->count();
        if ($failedRecent > 0) {
            $alerts[] = [
                'type' => 'error',
                'message' => "{$failedRecent} print job(s) failed in the last 24 hours",
                'action_url' => route('printing.jobs.index', ['status' => 'failed']),
            ];
        }

        return Inertia::render('Printing/Dashboard', [
            'stats' => $stats,
            'recent_activity' => $recentActivity,
            'printer_status' => $printerStatus,
            'alerts' => $alerts,
            'quick_actions' => [
                'print_test_label' => route('printing.templates.index'),
                'add_printer' => route('printing.printers.create'),
                'create_template' => route('printing.templates.create'),
                'view_jobs' => route('printing.jobs.index'),
            ],
        ]);
    }
}
