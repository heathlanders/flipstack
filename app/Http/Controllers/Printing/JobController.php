<?php

namespace App\Http\Controllers\Printing;

use App\Http\Controllers\Controller;
use App\Models\Printing\OrganizationPrinter;
use App\Models\Printing\PrintJob;
use Illuminate\Http\Request;
use Inertia\Inertia;

class JobController extends Controller
{
    private function getOrganizationId(Request $request): string
    {
        $user = $request->user();

        return $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;
    }

    /**
     * Display a listing of print jobs
     */
    public function index(Request $request)
    {
        $orgId = $this->getOrganizationId($request);
        $filters = $request->only(['status', 'printer_id', 'date_from', 'date_to', 'search', 'sort_by', 'sort_direction']);

        $query = PrintJob::where('organization_id', $orgId)
            ->with(['printer:id,name,driver,status', 'template:id,name', 'product:id,name,sku', 'creator:id,name']);

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->filled('printer_id')) {
            $query->where('printer_id', $request->get('printer_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->get('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->get('date_to'));
        }

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('job_number', 'ILIKE', "%{$search}%")
                    ->orWhereHas('product', fn ($pq) => $pq->where('name', 'ILIKE', "%{$search}%")->orWhere('sku', 'ILIKE', "%{$search}%"));
            });
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $allowedSorts = ['job_number', 'status', 'priority', 'quantity', 'created_at', 'completed_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDirection === 'desc' ? 'desc' : 'asc');
        }

        $jobs = $query->paginate(25)->withQueryString();

        $allJobs = PrintJob::where('organization_id', $orgId);
        $stats = [
            'total' => (clone $allJobs)->count(),
            'pending' => (clone $allJobs)->where('status', 'pending')->count(),
            'processing' => (clone $allJobs)->where('status', 'processing')->count(),
            'completed' => (clone $allJobs)->where('status', 'completed')->count(),
            'failed' => (clone $allJobs)->where('status', 'failed')->count(),
            'cancelled' => (clone $allJobs)->where('status', 'cancelled')->count(),
        ];

        $printers = OrganizationPrinter::where('organization_id', $orgId)
            ->where('is_active', true)
            ->get(['id', 'name']);

        return Inertia::render('Printing/Jobs/Index', [
            'jobs' => $jobs,
            'stats' => $stats,
            'filters' => $filters,
            'printers' => $printers,
            'status_options' => [
                'pending' => 'Pending',
                'processing' => 'Processing',
                'completed' => 'Completed',
                'failed' => 'Failed',
                'cancelled' => 'Cancelled',
            ],
            'priority_options' => [
                'low' => 'Low',
                'normal' => 'Normal',
                'high' => 'High',
                'urgent' => 'Urgent',
            ],
        ]);
    }

    /**
     * Display the specified print job
     */
    public function show(Request $request, string $id)
    {
        $orgId = $this->getOrganizationId($request);

        $job = PrintJob::where('organization_id', $orgId)
            ->with([
                'printer:id,name,driver,status',
                'template:id,name,width_mm,height_mm',
                'product:id,name,sku',
                'creator:id,name,email',
            ])
            ->findOrFail($id);

        return Inertia::render('Printing/Jobs/Show', [
            'job' => $job,
        ]);
    }
}
