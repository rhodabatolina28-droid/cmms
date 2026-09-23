<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function getNotifications(Request $request)
    {
        $user = Auth::user();
        $userId = $user ? $user->id : 0;

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
            ->with(['request.user'])
            ->orderBy('created_at', 'desc');

        $totalUnread = $query->count();
        $rawNotifications = $query->skip($offset)->take($limit)->get();

        $notifications = $rawNotifications->map(function ($n) use ($user) {
            $targetUrl = $this->resolveTargetUrl($n, $user);
            $reqNum = null;
            $sender = null;

            if ($n->request) {
                $reqNum = $n->request->request_number;
                if ($n->request->user) {
                    $sender = $n->request->user->full_name ?: $n->request->user->name;
                }
            } elseif (preg_match('/REQ-[A-Z0-9-]+/', $n->message, $m)) {
                $reqNum = $m[0];
            } elseif (preg_match('/PR-[A-Z0-9-]+/', $n->message, $m)) {
                $reqNum = $m[0];
            }

            // Extract sender from message if not found on relation
            if (!$sender) {
                if (preg_match('/(?:from|Admin|staff|personnel)\s+([A-Z\s]{3,30}?)(?:\s+in|\s+forwarded|\s+has|\s+\(|\.)/i', $n->message, $sm)) {
                    $sender = trim($sm[1]);
                }
            }

            // BUG-NOTIF-FROM-1: a notification addressed to the ticket's own
            // requestor must not read "From: <yourself>" — the requestor IS
            // the recipient. The acting IT personnel named in the message
            // ("IT personnel X has been assigned...") wins; otherwise the
            // notification is system-generated.
            if ($sender !== null && $n->request !== null && (int) $n->request->user_id === (int) $n->user_id) {
                $sender = $this->deriveSelfNotificationSender($n->message);
            }

            return [
                'id' => $n->id,
                'type' => $n->type ?: 'Notification',
                'message' => $n->message,
                'url' => $targetUrl,
                'request_number' => $reqNum,
                'sender' => $sender,
                'created_at' => $n->created_at ? $n->created_at->toISOString() : null,
                'time_ago' => $n->created_at ? $n->created_at->diffForHumans() : '',
            ];
        });

        return response()->json([
            'notifications' => $notifications,
            'count' => $count,
            'total' => $totalUnread,
            'has_more' => ($offset + $notifications->count()) < $totalUnread,
        ]);
    }

    /**
     * BUG-NOTIF-FROM-1: sender label for self-addressed notifications.
     * The ticket owner is the recipient here, so "From: <owner>" is wrong;
     * prefer the acting IT personnel named in the message, else "System".
     */
    private function deriveSelfNotificationSender(string $message): string
    {
        // "… is now Ongoing. IT personnel Marites Santos-Reyes has been assigned …"
        if (preg_match('/IT personnel\s+(.+?)(?=\s+(?:has|will|is|was)\b|\.(?:\s|$)|$)/i', $message, $m)) {
            $candidate = trim(preg_replace('/\s+/', ' ', $m[1]));
            $first = strtolower((string) strtok($candidate, ' '));

            // "IT personnel has updated …" names nobody — treat as system.
            if ($candidate !== '' && !in_array($first, ['has', 'will', 'is', 'was', 'updated', 'completed'], true)) {
                return $candidate;
            }
        }

        return 'System';
    }

    protected function resolveTargetUrl($notification, $user)
    {
        if (!empty($notification->url) && $notification->url !== '#') {
            return $notification->url;
        }

        if ($notification->request_id && $notification->request) {
            $req = $notification->request;
            if ($req->type === 'ICT') {
                if ($user && in_array($user->role, ['super_admin', 'admin'])) {
                    return route('ict.show', $req->id);
                }
                return route('ict.edit', $req->id);
            } else {
                if ($user && in_array($user->role, ['super_admin', 'it', 'admin'])) {
                    return route('maintenance.show', $req->id);
                }
                return route('maintenance.edit', $req->id);
            }
        }

        $msg = $notification->message ?? '';
        $type = $notification->type ?? '';

        if (preg_match('/REQ-[A-Z0-9-]+/', $msg, $m)) {
            $foundReq = \App\Models\Request::where('request_number', $m[0])->first();
            if ($foundReq) {
                if ($foundReq->type === 'ICT') {
                    return ($user && in_array($user->role, ['super_admin', 'admin']))
                        ? route('ict.show', $foundReq->id)
                        : route('ict.edit', $foundReq->id);
                }
                return route('maintenance.show', $foundReq->id);
            }
        }

        if (preg_match('/PR-[A-Z0-9-]+/', $msg, $m)) {
            $pr = \App\Models\PurchaseRequest::where('pr_number', $m[0])->first();
            if ($pr) {
                return route('purchase_requests.show', $pr->id);
            }
            return route('requisitions.index');
        }

        if (str_contains($type, 'Parts') || str_contains($type, 'Requisition')) {
            return route('requisitions.index');
        }

        if (str_contains($type, 'PR ') || str_contains($type, 'Purchase')) {
            return route('requisitions.index');
        }

        if (str_contains($type, 'PM ') || str_contains($type, 'Preventive')) {
            return route('pm-schedules.index');
        }

        return route('ict.index');
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
