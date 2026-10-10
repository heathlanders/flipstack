<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User\User;
use App\Models\User\UserActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;

class AuditHubController extends Controller
{
    /**
     * Filterable audit list (Admin Audit Hub landing).
     */
    public function index(Request $request)
    {
        $this->authorizeAdmin($request);
        $org = $request->user()->organization;

        $query = UserActivityLog::query()
            ->where('organization_id', $org->id)
            ->with('user:id,name,email');

        $this->applyFilters($query, $request);

        $rows = $query->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $rows->through(fn ($row) => $this->transform($row));

        return Inertia::render('Admin/AuditHub/Index', [
            'rows' => $rows,
            'filters' => $request->only(['user_id', 'category', 'action', 'severity', 'model_type', 'source', 'date_from', 'date_to', 'search']),
            'facets' => $this->facets($org->id),
        ]);
    }

    /**
     * Per-entity audit timeline (e.g. one Product UUID).
     */
    public function entity(Request $request, string $type, string $id)
    {
        $this->authorizeAdmin($request);
        $org = $request->user()->organization;

        $rows = UserActivityLog::query()
            ->where('organization_id', $org->id)
            ->where('model_type', $type)
            ->where('model_id', $id)
            ->with('user:id,name,email')
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->map(fn ($row) => $this->transform($row));

        return Inertia::render('Admin/AuditHub/EntityTimeline', [
            'modelType' => $type,
            'modelId' => $id,
            'rows' => $rows,
        ]);
    }

    /**
     * Security-only feed (category=security across the org).
     */
    public function security(Request $request)
    {
        $this->authorizeAdmin($request);
        $org = $request->user()->organization;

        $query = UserActivityLog::query()
            ->where('organization_id', $org->id)
            ->where('category', 'security')
            ->with('user:id,name,email');

        $this->applyFilters($query, $request);

        $rows = $query->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $rows->through(fn ($row) => $this->transform($row));

        // 7d failed-login burst summary
        $failedByDay = UserActivityLog::query()
            ->where('organization_id', $org->id)
            ->where('action', 'auth.failed_login')
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('date(created_at) as day, count(*) as count')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return Inertia::render('Admin/AuditHub/Security', [
            'rows' => $rows,
            'failed_by_day' => $failedByDay,
            'filters' => $request->only(['user_id', 'action', 'severity', 'date_from', 'date_to', 'search']),
            'facets' => $this->facets($org->id, securityOnly: true),
        ]);
    }

    /**
     * Stream filtered audit rows as CSV.
     */
    public function export(Request $request)
    {
        $this->authorizeAdmin($request);
        $org = $request->user()->organization;

        $query = UserActivityLog::query()
            ->where('organization_id', $org->id)
            ->with('user:id,name,email');

        $this->applyFilters($query, $request);

        $filename = 'audit-hub-'.now()->format('Ymd-His').'.csv';

        return Response::streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'When', 'User', 'Email', 'Action', 'Category', 'Severity', 'Description', 'Model', 'Source', 'IP', 'Metadata']);

            $query->orderByDesc('id')->chunk(500, function ($chunk) use ($handle) {
                foreach ($chunk as $row) {
                    fputcsv($handle, [
                        $row->id,
                        $row->created_at?->toIso8601String(),
                        $row->user?->name ?? '',
                        $row->user?->email ?? '',
                        $row->action,
                        $row->category,
                        $row->severity,
                        $row->description,
                        $row->model_type ? class_basename($row->model_type).'#'.$row->model_id : '',
                        $row->metadata['source'] ?? '',
                        $row->ip_address,
                        json_encode($row->metadata, JSON_UNESCAPED_SLASHES),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    protected function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user && $user->hasRole(['super_admin', 'organization_owner', 'admin', 'manager']),
            403,
        );
    }

    protected function applyFilters($query, Request $request): void
    {
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('action')) {
            $action = $request->action;
            $query->where(function ($q) use ($action) {
                $q->where('action', $action)->orWhere('action', 'like', $action.'%');
            });
        }
        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }
        if ($request->filled('model_type')) {
            $query->where('model_type', $request->model_type);
        }
        if ($request->filled('source')) {
            $query->whereJsonContains('metadata->source', $request->source);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->filled('search')) {
            $search = '%'.$request->search.'%';
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', $search)
                    ->orWhere('action', 'like', $search);
            });
        }
    }

    protected function facets(int $orgId, bool $securityOnly = false): array
    {
        $base = UserActivityLog::query()->where('organization_id', $orgId);
        if ($securityOnly) {
            $base->where('category', 'security');
        }

        return [
            'users' => User::query()
                ->where('organization_id', $orgId)
                ->select('id', 'name', 'email')
                ->orderBy('name')
                ->get(),
            'categories' => (clone $base)->select('category')->distinct()->orderBy('category')->pluck('category'),
            'actions' => (clone $base)->select('action')->distinct()->orderBy('action')->pluck('action'),
            'severities' => ['critical', 'error', 'warning', 'info', 'debug'],
            'sources' => ['web', 'mobile', 'api', 'job', 'webhook', 'cron'],
            'model_types' => (clone $base)->whereNotNull('model_type')->select('model_type')->distinct()->pluck('model_type'),
        ];
    }

    protected function transform(UserActivityLog $row): array
    {
        return [
            'id' => $row->id,
            'action' => $row->action,
            'description' => $row->description,
            'category' => $row->category,
            'severity' => $row->severity,
            'severity_color' => $row->getSeverityColor(),
            'category_icon' => $row->getCategoryIcon(),
            'user' => [
                'id' => $row->user?->id,
                'name' => $row->user?->name ?? 'System',
                'email' => $row->user?->email ?? '',
            ],
            'model_type' => $row->model_type,
            'model_basename' => $row->model_type ? class_basename($row->model_type) : null,
            'model_id' => $row->model_id,
            'metadata' => $row->metadata,
            'source' => $row->metadata['source'] ?? null,
            'ip_address' => $row->ip_address,
            'created_at' => $row->created_at?->toIso8601String(),
            'time_ago' => $row->created_at?->diffForHumans(),
        ];
    }
}
