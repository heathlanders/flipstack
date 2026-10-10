<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Owner/admin-only dashboard reading aggregate metrics from notification_logs.
 * Counts sent / delivered / opened / clicked / failed by channel, plus daily
 * trend over the last 30 days.
 */
class NotificationMetricsController extends Controller
{
    private const ALLOWED_ROLES = ['super_admin', 'organization_owner', 'admin'];

    public function index()
    {
        $this->authorizeOwner();

        $orgId = Auth::user()->organization_id;
        $since = now()->subDays(30);

        $byChannel = DB::table('notification_logs')
            ->where('tenant_id', $orgId)
            ->where('created_at', '>=', $since)
            ->select([
                'channel',
                DB::raw('COUNT(*) as total'),
                DB::raw("COUNT(*) FILTER (WHERE status = 'sent') as sent"),
                DB::raw("COUNT(*) FILTER (WHERE status = 'failed') as failed"),
                DB::raw('COUNT(*) FILTER (WHERE delivered_at IS NOT NULL) as delivered'),
                DB::raw('COUNT(*) FILTER (WHERE opened_at IS NOT NULL) as opened'),
                DB::raw('COUNT(*) FILTER (WHERE clicked_at IS NOT NULL) as clicked'),
            ])
            ->groupBy('channel')
            ->get();

        $dailyTrend = DB::table('notification_logs')
            ->where('tenant_id', $orgId)
            ->where('created_at', '>=', $since)
            ->select([
                DB::raw('DATE(created_at) as day'),
                DB::raw('COUNT(*) as total'),
                DB::raw("COUNT(*) FILTER (WHERE status = 'failed') as failed"),
            ])
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $totals = [
            'total' => $byChannel->sum('total'),
            'sent' => $byChannel->sum('sent'),
            'failed' => $byChannel->sum('failed'),
            'delivered' => $byChannel->sum('delivered'),
            'opened' => $byChannel->sum('opened'),
            'clicked' => $byChannel->sum('clicked'),
        ];

        $totals['delivery_rate'] = $totals['total'] > 0
            ? round(($totals['delivered'] / $totals['total']) * 100, 1)
            : 0;
        $totals['open_rate'] = $totals['delivered'] > 0
            ? round(($totals['opened'] / $totals['delivered']) * 100, 1)
            : 0;
        $totals['click_rate'] = $totals['opened'] > 0
            ? round(($totals['clicked'] / $totals['opened']) * 100, 1)
            : 0;
        $totals['failure_rate'] = $totals['total'] > 0
            ? round(($totals['failed'] / $totals['total']) * 100, 1)
            : 0;

        return Inertia::render('Settings/Notifications/Metrics', [
            'totals' => $totals,
            'by_channel' => $byChannel,
            'daily_trend' => $dailyTrend,
            'window_days' => 30,
        ]);
    }

    private function authorizeOwner(): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(403);
        }

        $hasRole = $user->roles()->whereIn('slug', self::ALLOWED_ROLES)->exists();
        if (! $hasRole) {
            abort(403);
        }
    }
}
