<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Supplier;
use App\Models\Supplier\SupplierNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SupplierNotificationController extends Controller
{
    /**
     * Display notifications list
     */
    public function index(Request $request)
    {
        $supplier = $this->getAuthenticatedSupplier();

        if (! $supplier) {
            return redirect()->route('dashboard')
                ->with('error', 'Supplier account not found.');
        }

        $query = SupplierNotification::forSupplier($supplier->id)
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('category')) {
            $query->category($request->input('category'));
        }

        if ($request->filled('type')) {
            $query->type($request->input('type'));
        }

        if ($request->filled('read')) {
            if ($request->input('read') === 'unread') {
                $query->unread();
            } elseif ($request->input('read') === 'read') {
                $query->read();
            }
        }

        if ($request->filled('priority')) {
            $query->priority($request->input('priority'));
        }

        $notifications = $query->paginate($request->input('per_page', 20));

        // Get counts for filters
        $counts = [
            'all' => SupplierNotification::forSupplier($supplier->id)->count(),
            'unread' => SupplierNotification::forSupplier($supplier->id)->unread()->count(),
            'urgent' => SupplierNotification::forSupplier($supplier->id)->urgent()->count(),
            'action_required' => SupplierNotification::forSupplier($supplier->id)->actionRequired()->count(),
        ];

        // Get category counts
        $categories = SupplierNotification::forSupplier($supplier->id)
            ->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category')
            ->toArray();

        return Inertia::render('Supplier/Notifications', [
            'notifications' => $notifications,
            'counts' => $counts,
            'categories' => $categories,
            'filters' => $request->only(['category', 'type', 'read', 'priority']),
        ]);
    }

    /**
     * Mark a notification as read
     */
    public function markAsRead(Request $request, $notificationId)
    {
        $supplier = $this->getAuthenticatedSupplier();

        $notification = SupplierNotification::where('id', $notificationId)
            ->where('supplier_id', $supplier->id)
            ->firstOrFail();

        $notification->markAsRead();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read.',
            ]);
        }

        return back()->with('success', 'Notification marked as read.');
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request)
    {
        $supplier = $this->getAuthenticatedSupplier();

        $count = SupplierNotification::markAllAsRead($supplier->id);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "{$count} notifications marked as read.",
                'count' => $count,
            ]);
        }

        return back()->with('success', "{$count} notifications marked as read.");
    }

    /**
     * Delete a notification
     */
    public function destroy(Request $request, $notificationId)
    {
        $supplier = $this->getAuthenticatedSupplier();

        $notification = SupplierNotification::where('id', $notificationId)
            ->where('supplier_id', $supplier->id)
            ->firstOrFail();

        $notification->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Notification deleted.',
            ]);
        }

        return back()->with('success', 'Notification deleted.');
    }

    /**
     * Get unread count (for header badge)
     */
    public function unreadCount(Request $request)
    {
        $supplier = $this->getAuthenticatedSupplier();

        if (! $supplier) {
            return response()->json(['count' => 0]);
        }

        $count = SupplierNotification::forSupplier($supplier->id)
            ->unread()
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Get recent notifications (for dropdown)
     */
    public function recent(Request $request)
    {
        $supplier = $this->getAuthenticatedSupplier();

        if (! $supplier) {
            return response()->json(['notifications' => []]);
        }

        $notifications = SupplierNotification::forSupplier($supplier->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'title' => $n->title,
                'message' => $n->short_message ?? substr($n->message, 0, 100),
                'type' => $n->type,
                'category' => $n->category,
                'is_read' => $n->is_read,
                'action_url' => $n->action_url,
                'created_at' => $n->created_at->diffForHumans(),
            ]);

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => SupplierNotification::forSupplier($supplier->id)->unread()->count(),
        ]);
    }

    /**
     * Get notification preferences
     */
    public function preferences(Request $request)
    {
        $supplier = $this->getAuthenticatedSupplier();

        $defaults = [
            'email_notifications' => true,
            'sms_notifications' => false,
            'push_notifications' => true,
            'categories' => [
                'settlement' => ['email' => true, 'sms' => false, 'push' => true],
                'tier_change' => ['email' => true, 'sms' => true, 'push' => true],
                'approval' => ['email' => true, 'sms' => false, 'push' => true],
                'payment' => ['email' => true, 'sms' => true, 'push' => true],
                'system' => ['email' => true, 'sms' => false, 'push' => false],
            ],
        ];

        $preferences = $supplier->meta_data['notification_preferences'] ?? $defaults;

        return response()->json($preferences);
    }

    /**
     * Update notification preferences
     */
    public function updatePreferences(Request $request)
    {
        $request->validate([
            'email_notifications' => 'boolean',
            'sms_notifications' => 'boolean',
            'push_notifications' => 'boolean',
            'categories' => 'array',
        ]);

        $supplier = $this->getAuthenticatedSupplier();

        $metaData = $supplier->meta_data ?? [];
        $metaData['notification_preferences'] = $request->only([
            'email_notifications',
            'sms_notifications',
            'push_notifications',
            'categories',
        ]);
        $supplier->meta_data = $metaData;
        $supplier->save();

        return response()->json([
            'success' => true,
            'message' => 'Notification preferences updated.',
        ]);
    }

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
}
