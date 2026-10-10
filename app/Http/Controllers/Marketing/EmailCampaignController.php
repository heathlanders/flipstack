<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\EmailCampaign;
use App\Services\Marketing\EmailCampaignService;
use App\Services\Marketing\EmailTemplateRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailCampaignController extends Controller
{
    public function __construct(
        protected EmailCampaignService $campaignService,
        protected EmailTemplateRenderer $templateRenderer
    ) {
        $this->middleware(['auth']);
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        $campaigns = EmailCampaign::where('organization_id', $organizationId)
            ->orderByDesc('created_at')
            ->get()
            ->map(function (EmailCampaign $campaign) {
                return [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'type' => $campaign->type,
                    'type_label' => EmailCampaign::TYPES[$campaign->type] ?? $campaign->type,
                    'trigger_event' => $campaign->trigger_event,
                    'trigger_label' => EmailCampaign::TRIGGERS[$campaign->trigger_event] ?? $campaign->trigger_event,
                    'trigger_delay_hours' => $campaign->trigger_delay_hours,
                    'is_active' => $campaign->is_active,
                    'sent_count' => $campaign->sent_count,
                    'open_rate' => $campaign->openRate(),
                    'click_rate' => $campaign->clickRate(),
                    'created_at' => $campaign->created_at?->toDateTimeString(),
                ];
            });

        $totalCampaigns = $campaigns->count();
        $activeCampaigns = $campaigns->where('is_active', true)->count();
        $totalSent = $campaigns->sum('sent_count');
        $avgOpenRate = $totalCampaigns > 0
            ? round($campaigns->avg('open_rate'), 2)
            : 0;

        return Inertia::render('Marketing/Campaigns/Index', [
            'campaigns' => $campaigns,
            'stats' => [
                'total_campaigns' => $totalCampaigns,
                'active_campaigns' => $activeCampaigns,
                'total_sent' => $totalSent,
                'avg_open_rate' => $avgOpenRate,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Marketing/Campaigns/Create', [
            'types' => EmailCampaign::TYPES,
            'triggers' => EmailCampaign::TRIGGERS,
            'availableVariables' => $this->templateRenderer->getAvailableVariables(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:'.implode(',', array_keys(EmailCampaign::TYPES)),
            'trigger_event' => 'required|string|in:'.implode(',', array_keys(EmailCampaign::TRIGGERS)),
            'trigger_delay_hours' => 'required|integer|min:0|max:8760',
            'subject_template' => 'required|string|max:500',
            'body_template' => 'required|string|max:65535',
            'is_active' => 'boolean',
        ]);

        $this->campaignService->create($validated, $organizationId);

        return redirect()->route('marketing.campaigns.index')
            ->with('success', 'Campaign created successfully.');
    }

    public function edit(Request $request, EmailCampaign $campaign): Response
    {
        return Inertia::render('Marketing/Campaigns/Edit', [
            'campaign' => $campaign,
            'types' => EmailCampaign::TYPES,
            'triggers' => EmailCampaign::TRIGGERS,
            'availableVariables' => $this->templateRenderer->getAvailableVariables(),
        ]);
    }

    public function update(Request $request, EmailCampaign $campaign): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:'.implode(',', array_keys(EmailCampaign::TYPES)),
            'trigger_event' => 'required|string|in:'.implode(',', array_keys(EmailCampaign::TRIGGERS)),
            'trigger_delay_hours' => 'required|integer|min:0|max:8760',
            'subject_template' => 'required|string|max:500',
            'body_template' => 'required|string|max:65535',
            'is_active' => 'boolean',
        ]);

        $this->campaignService->update($campaign, $validated);

        return redirect()->route('marketing.campaigns.index')
            ->with('success', 'Campaign updated successfully.');
    }

    public function destroy(EmailCampaign $campaign): RedirectResponse
    {
        $campaign->delete();

        return redirect()->route('marketing.campaigns.index')
            ->with('success', 'Campaign deleted successfully.');
    }

    public function toggle(EmailCampaign $campaign): RedirectResponse
    {
        $this->campaignService->toggle($campaign);

        $status = $campaign->fresh()->is_active ? 'activated' : 'deactivated';

        return redirect()->back()
            ->with('success', "Campaign {$status} successfully.");
    }

    public function stats(Request $request, EmailCampaign $campaign): Response
    {
        $stats = $this->campaignService->getStats($campaign);

        $recentSends = $campaign->sends()
            ->with(['customer:id,name,email', 'order:id,order_number,marketplace_order_id'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function ($send) {
                return [
                    'id' => $send->id,
                    'customer_name' => $send->customer?->name ?? 'Unknown',
                    'customer_email' => $send->customer?->email ?? '',
                    'order_number' => $send->order?->order_number ?? $send->order?->marketplace_order_id ?? '-',
                    'status' => $send->status,
                    'sent_at' => $send->sent_at?->toDateTimeString(),
                    'opened_at' => $send->opened_at?->toDateTimeString(),
                    'clicked_at' => $send->clicked_at?->toDateTimeString(),
                ];
            });

        return Inertia::render('Marketing/Campaigns/Stats', [
            'campaign' => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'type' => $campaign->type,
                'type_label' => EmailCampaign::TYPES[$campaign->type] ?? $campaign->type,
                'is_active' => $campaign->is_active,
            ],
            'stats' => $stats,
            'recentSends' => $recentSends,
        ]);
    }

    public function preview(Request $request, EmailCampaign $campaign): JsonResponse
    {
        $rendered = $this->templateRenderer->preview($campaign);

        return response()->json($rendered);
    }
}
