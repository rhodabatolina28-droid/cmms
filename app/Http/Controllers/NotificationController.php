<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function getNotifications(Request $request)
    {
        $userId = Auth::id();

        // Badge: true unread total (no limit) so the red bubble is accurate.
        $count = Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->count();

        // Dropdown list: allow scrolling through all unread notifications.
        // Default limit is 50 so 20+ notifications are immediately visible on scroll,
        // with offset support for infinite scrolling if a user has even more.
        $limit = max(1, min((int) $request->input('limit', 50), 100));
        $offset = max(0, (int) $request->input('offset', 0));

        $query = Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->orderBy('created_at', 'desc');

        $totalUnread = $query->count();
        $notifications = $query->skip($offset)->take($limit)->get();

        return response()->json([
            'notifications' => $notifications,
            'count' => $count,
            'total' => $totalUnread,
            'has_more' => ($offset + $notifications->count()) < $totalUnread,
        ]);
    }

    public function markAsRead($id)
    {
        $notification = Notification::where('user_id', Auth::id())->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    public function markAllAsRead()
    {
        Notification::where('user_id', Auth::id())
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now()
            ]);

        return response()->json(['success' => true]);
    }
}
