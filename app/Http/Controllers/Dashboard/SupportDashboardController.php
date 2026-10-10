<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Communication\MarketplaceMessage;
use App\Models\Fulfillment\Order;
use App\Models\Fulfillment\ReturnRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupportDashboardController extends BaseDashboardController
{
    public function show(Request $request)
    {
        $user = $request->user();
        $organization = $user->organization;

        return Inertia::render('Dashboard/Support', [
            'dashboardType' => 'support',
            'user' => [
                'name' => $user->name,
                'role' => $user->getPrimaryRole(),
                'permissions' => $user->getEffectivePermissions(),
            ],
            'metrics' => $this->getSupportMetrics($organization),
            'recentActivity' => $this->getRecentActivity($user, 'support'),
            'alerts' => $this->getAlerts($user),
            'quickActions' => $user->getQuickActions(),
            'recentMessages' => $this->getRecentMessages($organization),
            'returnRequests' => $this->getReturnRequests($organization),
        ]);
    }

    protected function getSupportMetrics($organization)
    {
        $orgId = $organization->id;

        $unreadMessages = MarketplaceMessage::where('organization_id', $orgId)
            ->whereNull('read_at')
            ->where('direction', 'incoming')
            ->count();

        $totalMessagesToday = MarketplaceMessage::where('organization_id', $orgId)
            ->whereDate('created_at', today())
            ->count();

        $openReturns = ReturnRequest::where('organization_id', $orgId)
            ->whereIn('status', ['requested', 'approved', 'in_transit'])
            ->count();

        $pendingOrders = Order::where('organization_id', $orgId)
            ->where('status', Order::STATUS_PENDING)
            ->count();

        // Approximate avg response time from messages that have replied_at
        $avgResponseTime = MarketplaceMessage::where('organization_id', $orgId)
            ->where('direction', 'incoming')
            ->where('created_at', '>=', now()->subDays(7))
            ->whereNotNull('replied_at')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (replied_at - received_at)) / 3600) as avg_hours')
            ->value('avg_hours') ?? 0;
        $avgResponseTime = (float) $avgResponseTime;

        return [
            'messages' => [
                'unread' => $unreadMessages,
                'today' => $totalMessagesToday,
                'avg_response_hours' => round($avgResponseTime, 1),
            ],
            'orders' => [
                'pending' => $pendingOrders,
            ],
            'returns' => [
                'open' => $openReturns,
            ],
        ];
    }

    protected function getRecentMessages($organization)
    {
        return MarketplaceMessage::where('organization_id', $organization->id)
            ->where('direction', 'incoming')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($msg) => [
                'id' => $msg->id,
                'sender' => $msg->sender_name ?? $msg->sender_id,
                'subject' => $msg->subject,
                'marketplace' => $msg->marketplace,
                'is_read' => $msg->read_at !== null,
                'created_at' => $msg->created_at,
            ]);
    }

    protected function getReturnRequests($organization)
    {
        return ReturnRequest::where('organization_id', $organization->id)
            ->whereIn('status', ['requested', 'approved', 'in_transit'])
            ->with('order:id,marketplace_order_id,buyer_name,marketplace')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($return) => [
                'id' => $return->id,
                'order_id' => $return->order?->marketplace_order_id ?? $return->order_id,
                'buyer' => $return->order?->buyer_name,
                'reason' => $return->reason,
                'status' => $return->status,
                'marketplace' => $return->order?->marketplace,
                'created_at' => $return->created_at,
            ]);
    }
}
