<?php

namespace App\Http\Controllers\Printing;

use App\Http\Controllers\Controller;
use App\Models\Printing\LabelTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class TemplateController extends Controller
{
    private function getOrganizationId(Request $request): string
    {
        $user = $request->user();

        return $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;
    }

    /**
     * Display a listing of label templates
     */
    public function index(Request $request)
    {
        $orgId = $this->getOrganizationId($request);
        $filters = $request->only(['search', 'size', 'active_only', 'sort_by', 'sort_direction']);

        $query = LabelTemplate::where('organization_id', $orgId);

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('description', 'ILIKE', "%{$search}%");
            });
        }

        if ($request->filled('size')) {
            match ($request->get('size')) {
                'small' => $query->where('height_mm', '<=', 50),
                'medium' => $query->where('height_mm', '>', 50)->where('height_mm', '<=', 75),
                'large' => $query->where('height_mm', '>', 75),
                default => null,
            };
        }

        if ($request->filled('active_only') && $request->get('active_only') === 'true') {
            $query->where('is_active', true);
        }

        $sortBy = $request->get('sort_by', 'name');
        $sortDirection = $request->get('sort_direction', 'asc');
        $allowedSorts = ['name', 'width_mm', 'height_mm', 'usage_count', 'created_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDirection === 'desc' ? 'desc' : 'asc');
        }

        $templates = $query->get();

        $allTemplates = LabelTemplate::where('organization_id', $orgId);
        $stats = [
            'total' => (clone $allTemplates)->count(),
            'active' => (clone $allTemplates)->where('is_active', true)->count(),
            'default' => (clone $allTemplates)->where('is_default', true)->first()?->name,
            'small_labels' => (clone $allTemplates)->where('height_mm', '<=', 50)->count(),
            'medium_labels' => (clone $allTemplates)->where('height_mm', '>', 50)->where('height_mm', '<=', 75)->count(),
            'large_labels' => (clone $allTemplates)->where('height_mm', '>', 75)->count(),
        ];

        return Inertia::render('Printing/Templates/Index', [
            'templates' => $templates,
            'stats' => $stats,
            'filters' => $filters,
            'size_options' => [
                'small' => 'Small (<=50mm)',
                'medium' => 'Medium (51-75mm)',
                'large' => 'Large (>75mm)',
            ],
        ]);
    }

    /**
     * Show the form for creating a new template
     */
    public function create()
    {
        return Inertia::render('Printing/Templates/Create', [
            'default_settings' => [
                'width_mm' => 50,
                'height_mm' => 30,
                'template_data' => [
                    'elements' => [],
                ],
            ],
            'element_types' => $this->elementTypes(),
            'field_options' => $this->fieldOptions(),
        ]);
    }

    /**
     * Store a newly created template
     */
    public function store(Request $request)
    {
        $orgId = $this->getOrganizationId($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'width_mm' => 'required|numeric|min:10|max:200',
            'height_mm' => 'required|numeric|min:10|max:200',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'template_data' => 'required|array',
            'template_data.elements' => 'array',
        ]);

        $validated['organization_id'] = $orgId;

        if (! empty($validated['is_default'])) {
            LabelTemplate::where('organization_id', $orgId)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $template = LabelTemplate::create($validated);

        Log::info('Label template created', ['template_id' => $template->id, 'organization_id' => $orgId]);

        return redirect()->route('printing.templates.index')
            ->with('success', 'Template created successfully.');
    }

    /**
     * Display the specified template
     */
    public function show(Request $request, string $id)
    {
        $orgId = $this->getOrganizationId($request);
        $template = LabelTemplate::where('organization_id', $orgId)->findOrFail($id);

        $thisWeek = $template->printJobs()->where('created_at', '>=', now()->startOfWeek())->count();
        $thisMonth = $template->printJobs()->where('created_at', '>=', now()->startOfMonth())->count();

        return Inertia::render('Printing/Templates/Show', [
            'template' => array_merge($template->toArray(), [
                'usage_stats' => [
                    'total_prints' => $template->usage_count,
                    'this_week' => $thisWeek,
                    'this_month' => $thisMonth,
                ],
            ]),
            'preview_data' => [
                'sku' => 'SAMPLE-001',
                'product_name' => 'Sample Product',
                'location' => 'A1-B2-C3',
                'organization' => $request->user()->organization?->name ?? 'Organization',
                'qr_data' => 'SAMPLE-001|Sample Product|A1-B2-C3',
            ],
        ]);
    }

    /**
     * Show the form for editing the specified template
     */
    public function edit(Request $request, string $id)
    {
        $orgId = $this->getOrganizationId($request);
        $template = LabelTemplate::where('organization_id', $orgId)->findOrFail($id);

        return Inertia::render('Printing/Templates/Edit', [
            'template' => $template,
            'element_types' => $this->elementTypes(),
            'field_options' => $this->fieldOptions(),
        ]);
    }

    /**
     * Update the specified template
     */
    public function update(Request $request, string $id)
    {
        $orgId = $this->getOrganizationId($request);
        $template = LabelTemplate::where('organization_id', $orgId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'width_mm' => 'required|numeric|min:10|max:200',
            'height_mm' => 'required|numeric|min:10|max:200',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'template_data' => 'required|array',
            'template_data.elements' => 'array',
        ]);

        if (! empty($validated['is_default']) && ! $template->is_default) {
            LabelTemplate::where('organization_id', $orgId)
                ->where('is_default', true)
                ->where('id', '!=', $template->id)
                ->update(['is_default' => false]);
        }

        $template->update($validated);

        return redirect()->route('printing.templates.show', $id)
            ->with('success', 'Template updated successfully.');
    }

    /**
     * Remove the specified template
     */
    public function destroy(Request $request, string $id)
    {
        $orgId = $this->getOrganizationId($request);
        $template = LabelTemplate::where('organization_id', $orgId)->findOrFail($id);

        $activeJobs = $template->printJobs()->whereIn('status', ['pending', 'processing'])->count();
        if ($activeJobs > 0) {
            return back()->withErrors(['error' => "Cannot delete template with {$activeJobs} active job(s)."]);
        }

        $template->delete();

        Log::info('Label template deleted', ['template_id' => $id, 'organization_id' => $orgId]);

        return redirect()->route('printing.templates.index')
            ->with('success', 'Template deleted successfully.');
    }

    private function elementTypes(): array
    {
        return [
            'text' => 'Text Field',
            'qr_code' => 'QR Code',
            'barcode' => 'Barcode',
            'line' => 'Line',
            'rectangle' => 'Rectangle',
        ];
    }

    private function fieldOptions(): array
    {
        return [
            'sku' => 'SKU',
            'product_name' => 'Product Name',
            'location' => 'Warehouse Location',
            'organization' => 'Organization Name',
            'date_created' => 'Date Created',
            'qr_data' => 'Full QR Data',
            'barcode_data' => 'Barcode Data',
        ];
    }
}
