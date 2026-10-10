<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\Analytics\SavedReport;
use App\Models\Analytics\ScheduledReport;
use App\Services\Analytics\ReportExportService;
use App\Services\Analytics\ReportRegistry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __construct(protected ReportExportService $exporter)
    {
        $this->middleware(['auth']);
    }

    private function ctx(Request $request): array
    {
        $user = $request->user();
        $orgId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;
        $isManager = $user->hasRole(['super_admin', 'organization_owner', 'admin', 'manager']);

        return [$user, $orgId, $isManager];
    }

    /**
     * GET /reports/export?view=workforce_individual&format=csv&start_date=...
     */
    public function export(Request $request)
    {
        [$user, $orgId, $isManager] = $this->ctx($request);

        if (! $isManager) {
            abort(403, 'Only managers can export analytics.');
        }

        $request->validate([
            'view' => 'required|string',
            'format' => 'required|in:csv,xlsx,pdf',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $filters = [
            'start_date' => $request->get('start_date', now()->subDays(29)->toDateString()),
            'end_date' => $request->get('end_date', now()->toDateString()),
            'compare_start_date' => $request->get('compare_start_date'),
            'compare_end_date' => $request->get('compare_end_date'),
        ];

        return $this->exporter->export($request->view, $request->format, $orgId, $filters);
    }

    public function index(Request $request): Response
    {
        [$user, $orgId, $isManager] = $this->ctx($request);

        if (! $isManager) {
            abort(403);
        }

        $saved = SavedReport::where('organization_id', $orgId)
            ->where(fn ($q) => $q->where('owner_id', $user->id)->orWhere('is_shared', true))
            ->orderBy('view')->orderBy('name')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'view' => $r->view,
                'view_label' => ReportRegistry::get($r->view)['label'] ?? $r->view,
                'filters' => $r->filters,
                'is_shared' => $r->is_shared,
                'is_owner' => $r->owner_id === $user->id,
            ]);

        $scheduled = ScheduledReport::where('organization_id', $orgId)
            ->where('owner_id', $user->id)
            ->orderBy('next_due_at')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'view' => $s->view,
                'view_label' => ReportRegistry::get($s->view)['label'] ?? $s->view,
                'filters' => $s->filters,
                'format' => $s->format,
                'frequency' => $s->frequency,
                'day_of_period' => $s->day_of_period,
                'send_time' => $s->send_time,
                'recipients' => $s->recipients,
                'is_active' => $s->is_active,
                'last_sent_at' => $s->last_sent_at?->toDateTimeString(),
                'next_due_at' => $s->next_due_at?->toDateTimeString(),
            ]);

        return Inertia::render('Settings/Reports/Index', [
            'saved' => $saved,
            'scheduled' => $scheduled,
            'viewOptions' => ReportRegistry::viewOptions(),
            'frequencies' => [ScheduledReport::FREQ_DAILY, ScheduledReport::FREQ_WEEKLY, ScheduledReport::FREQ_MONTHLY],
            'formats' => ['csv', 'xlsx', 'pdf'],
        ]);
    }

    public function storeSaved(Request $request)
    {
        [$user, $orgId, $isManager] = $this->ctx($request);
        if (! $isManager) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'view' => 'required|string',
            'filters' => 'nullable|array',
            'is_shared' => 'boolean',
        ]);

        SavedReport::create(array_merge($validated, [
            'organization_id' => $orgId,
            'owner_id' => $user->id,
        ]));

        return redirect()->route('analytics.reports.index')->with('success', 'Saved report created.');
    }

    public function destroySaved(Request $request, SavedReport $saved)
    {
        [$user, $orgId, $isManager] = $this->ctx($request);
        if (! $isManager || $saved->organization_id !== $orgId || $saved->owner_id !== $user->id) {
            abort(403);
        }
        $saved->delete();

        return redirect()->route('analytics.reports.index')->with('success', 'Removed.');
    }

    public function storeScheduled(Request $request)
    {
        [$user, $orgId, $isManager] = $this->ctx($request);
        if (! $isManager) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'view' => 'required|string',
            'filters' => 'nullable|array',
            'format' => 'required|in:csv,xlsx,pdf',
            'frequency' => 'required|in:daily,weekly,monthly',
            'day_of_period' => 'nullable|integer|min:0|max:31',
            'send_time' => 'nullable|string|max:5',
            'recipients' => 'required|array|min:1',
            'recipients.*' => 'email',
            'is_active' => 'boolean',
        ]);

        $report = new ScheduledReport(array_merge($validated, [
            'organization_id' => $orgId,
            'owner_id' => $user->id,
        ]));
        $report->next_due_at = $report->calculateNextDueAt(now());
        $report->save();

        return redirect()->route('analytics.reports.index')->with('success', 'Scheduled report created.');
    }

    public function updateScheduled(Request $request, ScheduledReport $scheduled)
    {
        [$user, $orgId, $isManager] = $this->ctx($request);
        if (! $isManager || $scheduled->organization_id !== $orgId || $scheduled->owner_id !== $user->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'view' => 'required|string',
            'filters' => 'nullable|array',
            'format' => 'required|in:csv,xlsx,pdf',
            'frequency' => 'required|in:daily,weekly,monthly',
            'day_of_period' => 'nullable|integer|min:0|max:31',
            'send_time' => 'nullable|string|max:5',
            'recipients' => 'required|array|min:1',
            'recipients.*' => 'email',
            'is_active' => 'boolean',
        ]);

        $scheduled->fill($validated);
        $scheduled->next_due_at = $scheduled->calculateNextDueAt(now());
        $scheduled->save();

        return redirect()->route('analytics.reports.index')->with('success', 'Scheduled report updated.');
    }

    public function destroyScheduled(Request $request, ScheduledReport $scheduled)
    {
        [$user, $orgId, $isManager] = $this->ctx($request);
        if (! $isManager || $scheduled->organization_id !== $orgId || $scheduled->owner_id !== $user->id) {
            abort(403);
        }
        $scheduled->delete();

        return redirect()->route('analytics.reports.index')->with('success', 'Removed.');
    }
}
