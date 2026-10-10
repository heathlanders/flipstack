<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    /**
     * Display notification center page.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $notifications = $user->notifications()
            ->when($request->filter === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->when($request->category, fn ($query) => $query->where('data->category', $request->category))
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'unreadCount' => $this->notificationService->getUnreadCount($user),
            'filters' => [
                'filter' => $request->filter,
                'category' => $request->category,
            ],
        ]);
    }

    /**
     * Get recent notifications for dropdown.
     */
    public function recent()
    {
        $user = Auth::user();

        return response()->json([
            'notifications' => $this->notificationService->getRecent($user, 5),
            'unreadCount' => $this->notificationService->getUnreadCount($user),
        ]);
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(string $id)
    {
        $user = Auth::user();
        $this->notificationService->markAsRead($id, $user);

        return response()->json([
            'success' => true,
            'unreadCount' => $this->notificationService->getUnreadCount($user),
        ]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead()
    {
        $user = Auth::user();
        $this->notificationService->markAllAsRead($user);

        return response()->json([
            'success' => true,
            'unreadCount' => 0,
        ]);
    }

    /**
     * Delete a notification.
     */
    public function destroy(string $id)
    {
        $user = Auth::user();
        $user->notifications()->where('id', $id)->delete();

        return response()->json(['success' => true]);
    }
}
