<?php

namespace App\Http\Controllers\Printing;

use App\Http\Controllers\Controller;
use App\Models\Printing\LabelTemplate;
use App\Models\Printing\OrganizationPrinter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class PrinterController extends Controller
{
    private function getOrganizationId(Request $request): string
    {
        $user = $request->user();

        return $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;
    }

    /**
     * Display a listing of printers
     */
    public function index(Request $request)
    {
        $orgId = $this->getOrganizationId($request);
        $filters = $request->only(['search', 'status', 'driver', 'sort_by', 'sort_direction']);

        $query = OrganizationPrinter::where('organization_id', $orgId);

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('connection_string', 'ILIKE', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->filled('driver')) {
            $query->where('driver', $request->get('driver'));
        }

        $sortBy = $request->get('sort_by', 'name');
        $sortDirection = $request->get('sort_direction', 'asc');
        $allowedSorts = ['name', 'status', 'driver', 'last_used_at', 'total_jobs', 'created_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDirection === 'desc' ? 'desc' : 'asc');
        }

        $printers = $query->get();

        $allPrinters = OrganizationPrinter::where('organization_id', $orgId);
        $stats = [
            'total' => (clone $allPrinters)->count(),
            'active' => (clone $allPrinters)->where('is_active', true)->count(),
            'online' => (clone $allPrinters)->where('status', 'online')->count(),
            'offline' => (clone $allPrinters)->where('status', 'offline')->count(),
            'network' => (clone $allPrinters)->where('printer_type', 'network')->count(),
            'usb' => (clone $allPrinters)->where('printer_type', 'usb')->count(),
        ];

        return Inertia::render('Printing/Printers/Index', [
            'printers' => $printers,
            'stats' => $stats,
            'filters' => $filters,
            'driver_options' => $this->driverOptions(),
        ]);
    }

    /**
     * Show printer discovery page
     */
    public function discovery()
    {
        return Inertia::render('Printing/Printers/Discovery', [
            'discovery_settings' => [
                'timeout' => 30,
                'ip_ranges' => ['192.168.1.0/24'],
                'ports' => [9100, 515, 631],
            ],
        ]);
    }

    /**
     * Show the form for creating a new printer
     */
    public function create(Request $request)
    {
        $orgId = $this->getOrganizationId($request);

        $templates = LabelTemplate::where('organization_id', $orgId)
            ->where('is_active', true)
            ->get(['id', 'name', 'width_mm', 'height_mm']);

        return Inertia::render('Printing/Printers/Create', [
            'templates' => $templates,
            'driver_options' => $this->driverOptions(),
            'connection_types' => [
                'network' => 'Network Printer (IP)',
                'usb' => 'USB Printer',
                'cups' => 'CUPS Printer',
            ],
        ]);
    }

    /**
     * Store a newly created printer
     */
    public function store(Request $request)
    {
        $orgId = $this->getOrganizationId($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'printer_type' => 'required|in:network,usb,cups',
            'connection_string' => 'required|string|max:255',
            'driver' => 'required|in:zebra_zpl,dymo_label,brother_ptouch,generic_escp,pdf',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'default_template_id' => 'nullable|exists:label_templates,id',
            'print_settings' => 'nullable|array',
        ]);

        $validated['organization_id'] = $orgId;

        // If setting as default, unset other defaults
        if (! empty($validated['is_default'])) {
            OrganizationPrinter::where('organization_id', $orgId)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $printer = OrganizationPrinter::create($validated);

        Log::info('Printer created', ['printer_id' => $printer->id, 'organization_id' => $orgId]);

        return redirect()->route('printing.printers.index')
            ->with('success', 'Printer created successfully.');
    }

    /**
     * Display the specified printer
     */
    public function show(Request $request, string $id)
    {
        $orgId = $this->getOrganizationId($request);
        $printer = OrganizationPrinter::where('organization_id', $orgId)->findOrFail($id);

        $recentJobs = $printer->printJobs()
            ->with(['template:id,name', 'product:id,name,sku', 'creator:id,name'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return Inertia::render('Printing/Printers/Show', [
            'printer' => array_merge($printer->toArray(), [
                'success_rate' => $printer->success_rate,
            ]),
            'recent_jobs' => $recentJobs,
        ]);
    }

    /**
     * Show the form for editing the specified printer
     */
    public function edit(Request $request, string $id)
    {
        $orgId = $this->getOrganizationId($request);
        $printer = OrganizationPrinter::where('organization_id', $orgId)->findOrFail($id);

        $templates = LabelTemplate::where('organization_id', $orgId)
            ->where('is_active', true)
            ->get(['id', 'name', 'width_mm', 'height_mm']);

        return Inertia::render('Printing/Printers/Edit', [
            'printer' => $printer,
            'templates' => $templates,
            'driver_options' => $this->driverOptions(),
        ]);
    }

    /**
     * Update the specified printer
     */
    public function update(Request $request, string $id)
    {
        $orgId = $this->getOrganizationId($request);
        $printer = OrganizationPrinter::where('organization_id', $orgId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'connection_string' => 'required|string|max:255',
            'driver' => 'required|in:zebra_zpl,dymo_label,brother_ptouch,generic_escp,pdf',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'default_template_id' => 'nullable|exists:label_templates,id',
            'print_settings' => 'nullable|array',
        ]);

        // If setting as default, unset other defaults
        if (! empty($validated['is_default']) && ! $printer->is_default) {
            OrganizationPrinter::where('organization_id', $orgId)
                ->where('is_default', true)
                ->where('id', '!=', $printer->id)
                ->update(['is_default' => false]);
        }

        $printer->update($validated);

        return redirect()->route('printing.printers.show', $id)
            ->with('success', 'Printer updated successfully.');
    }

    /**
     * Remove the specified printer
     */
    public function destroy(Request $request, string $id)
    {
        $orgId = $this->getOrganizationId($request);
        $printer = OrganizationPrinter::where('organization_id', $orgId)->findOrFail($id);

        $activeJobs = $printer->printJobs()->whereIn('status', ['pending', 'processing'])->count();
        if ($activeJobs > 0) {
            return back()->withErrors(['error' => "Cannot delete printer with {$activeJobs} active job(s)."]);
        }

        $printer->delete();

        Log::info('Printer deleted', ['printer_id' => $id, 'organization_id' => $orgId]);

        return redirect()->route('printing.printers.index')
            ->with('success', 'Printer deleted successfully.');
    }

    private function driverOptions(): array
    {
        return [
            'zebra_zpl' => 'Zebra (ZPL)',
            'dymo_label' => 'Dymo LabelWriter',
            'brother_ptouch' => 'Brother P-touch',
            'generic_escp' => 'Generic ESC-P',
            'pdf' => 'PDF (Universal)',
        ];
    }
}
