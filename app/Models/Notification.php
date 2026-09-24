<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'request_id',
        'type',
        'message',
        'url',
        'sender_id',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::created(function ($notification) {
            try {
                $user = $notification->user;
                if (!$user || !$user->email) {
                    return;
                }

                // PR-type notifications carry their number inside the message
                // (they have no requests row). D9.42 Phase 3: ICT/PM
                // notifications can reach this point without a request relation
                // too, so recover their STORED number from the message with the
                // same helper the bell uses ('{ICT|PM}-…'-aware). Only a message
                // with no recognisable number falls back to "N/A".
                $requestNumber = $notification->request
                    ? $notification->request->request_number
                    : ($notification->prNumber()
                        ?? \App\Support\RequestHelpers::extractRequestNumber($notification->message)
                        ?? 'N/A');

                // System admin: in-app only (no email flood on shared region).
                // D9.20: exception — ICT requests are routed straight to the System
                // Admin for review/assignment, so "for Review" types must also email.
                // D9.32: second exception — CSM* notification types (Monthly Report,
                // Severe Alert, Weekly Digest) must also email the SA; they are
                // bundled by design (~2-4 emails/month), so the flood rule is safe.
                if ($user->role === 'super_admin'
                    && !str_ends_with((string) $notification->type, 'for Review')
                    && !str_starts_with((string) $notification->type, 'CSM')) {
                    return;
                }

                $isLocal = app()->environment('local');
                $mailer = config('mail.default');

                // Local + log mailer: always write a readable preview to laravel.log
                if ($isLocal) {
                    \App\Services\RequestNotificationService::logLocalEmailPreview(
                        $user->email,
                        $notification->type,
                        $notification->message,
                        $requestNumber
                    );
                }

                // Production safety: skip alias emails unless explicitly testing one inbox
                if (!$isLocal && str_contains($user->email, '+')) {
                    \Illuminate\Support\Facades\Log::info('Skipped email to alias: ' . $user->email);
                    return;
                }

                // Build ticket URL and branch/region data
                $ticketUrl = $notification->url ?: null;
                $branch = $user->branch;
                $region = $user->region;
                $request = $notification->request;
                if ($request) {
                    $branch = $request->branch ?: $branch;
                    $region = $request->region ?: $region;
                }

                // BUG-NOTIF-LINK: the email "View Details" button must follow
                // the SAME destination rules as the bell (resolveTargetUrl).
                // Parts-family and PM-batch notices resolve OUTSIDE the linked
                // ticket — the request-linked branch below used to rebuild the
                // ICT/PM form URL for every request_id row.
                if (! $ticketUrl && self::isPartsFamilyType($notification->type)) {
                    $ticketUrl = self::partsFamilyUrlFor($user);
                } elseif (! $ticketUrl && str_contains((string) $notification->type, 'PM Batch')) {
                    $ticketUrl = self::pmBatchUrlFor($user);
                } elseif ($request) {
                    if ($request->type === 'ICT') {
                        if ($user->role === 'admin' || $user->role === 'super_admin') {
                            $ticketUrl = route('ict.show', $request->id);
                        } else {
                            $ticketUrl = route('ict.edit', $request->id);
                        }
                    } else {
                        if (in_array($user->role, ['it', 'super_admin'])) {
                            $ticketUrl = route('maintenance.edit', $request->id);
                        } else {
                            $ticketUrl = null;
                        }
                    }
                }

                // Choose email template based on notification type
                $mailable = ($notification->type === 'PM Scheduled')
                    ? new \App\Mail\PMScheduledMail(
                        $user->full_name,
                        $requestNumber,
                        $user->office ?? $user->department ?? 'your division',
                        null,
                        $ticketUrl,
                        $branch,
                        $region
                    )
                    : new \App\Mail\SystemNotificationMail(
                        $user->full_name,
                        $notification->type,
                        $notification->message,
                        $requestNumber,
                        $ticketUrl,
                        $branch,
                        $region
                    );

                // SMTP: send directly so emails go out immediately without needing a queue worker.
                // log/array: queue is fine since it just writes to log/memory.
                if ($mailer === 'smtp') {
                    \Illuminate\Support\Facades\Mail::to($user->email)->send($mailable);
                } else {
                    \Illuminate\Support\Facades\Mail::to($user->email)->queue($mailable);
                }

                // If PM Scheduled, also notify System Admins/IT
                if ($notification->type === 'PM Scheduled') {
                    \App\Services\PMNotificationService::notifyITStaff(
                        $requestNumber,
                        $notification->type,
                        $notification->message
                    );
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to send notification email: ' . $e->getMessage());
            }
        });
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // BUG-NOTIF-FROM-2: the user who PERFORMED the action that produced this
    // notification (admin who assigned, IT who requested parts, requestor who
    // submitted) — NOT the ticket's requestor. NULL = system/legacy row.
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function request()
    {
        return $this->belongsTo(Request::class);
    }

    // Scopes
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    // Helpers
    public function prNumber(): ?string
    {
        if (preg_match('/PR-[A-Z0-9-]+/', $this->message, $m)) {
            return $m[0];
        }

        return null;
    }

    /**
     * BUG-NOTIF-LINK: types whose actionable page is the requisitions
     * workspace (request-parts flow), NOT the parent ticket form. Covers the
     * whole family: "Parts Requisition", "Parts Request — Approve/Issue/
     * Reject/Issued", "Parts Request Rejected", "Parts Low Stock Alert".
     */
    public static function isPartsFamilyType(?string $type): bool
    {
        return str_contains((string) $type, 'Parts')
            || str_contains((string) $type, 'Requisition');
    }

    /**
     * Requisitions-workspace landing for the recipient: supply sees the
     * review queue (default view); the IT/SA requester sees their History
     * tab. Only roles that RECEIVE parts notifications reach this helper
     * (supply_officer, admin+can_supply, it, super_admin) — all pass the
     * requisitions.index role middleware.
     */
    public static function partsFamilyUrlFor(?User $user): string
    {
        if ($user && $user->canProcessSupply()) {
            return route('requisitions.index');
        }

        return route('requisitions.index', ['tab' => 'history']);
    }

    /**
     * BUG-NOTIF-LINK: pm-schedules.* routes are super_admin-only, but
     * "PM Batch Generated" also reaches IT (conduct the PMs) and division
     * admins/supply (inform personnel). Role-aware landing = no 403 redirect.
     * Plain admins are additionally 403'd inside ListMaintenanceRequestsAction,
     * so the non-IT/non-SA arm lands on the recipient's own dashboard.
     */
    public static function pmBatchUrlFor(?User $user): string
    {
        return match ($user?->role) {
            'super_admin' => route('pm-schedules.index'),
            'it' => route('pm.tasks'),
            default => route($user ? $user->dashboardRouteName() : 'dashboard.user'),
        };
    }

    public function markAsRead()
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    /**
     * @param  int|false  $senderId  BUG-NOTIF-FROM-2: who performed the action
     *        (bell "From" name beside the icon). `false` (default) auto-detects
     *        the authenticated actor — covers every web call site at once
     *        (admin assigning IT, IT/SA filing parts, user submitting a ticket).
     *        Pass an explicit user id to override, or `null` for system notices
     *        that must name nobody (console commands have no Auth anyway; the
     *        CSM severe alert passes null so the survey respondent — who IS
     *        signed in — is never shown as the sender).
     */
    public static function send($userId, $requestId, $type, $message, $url = null, $senderId = false)
    {
        if ($senderId === false) {
            $senderId = \Illuminate\Support\Facades\Auth::id();
        }

        return self::create([
            'user_id' => $userId,
            'request_id' => $requestId,
            'type' => $type,
            'message' => $message,
            'url' => $url,
            'sender_id' => $senderId,
            'is_read' => false
        ]);
    }
}
