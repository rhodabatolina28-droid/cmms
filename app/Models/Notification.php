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

                $requestNumber = $notification->request ? $notification->request->request_number : 'N/A';

                // Super admin: in-app only (no email flood on shared region)
                if ($user->role === 'super_admin') {
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
                $ticketUrl = null;
                $branch = $user->branch;
                $region = $user->region;
                $request = $notification->request;
                if ($request) {
                    $branch = $request->branch ?: $branch;
                    $region = $request->region ?: $region;
                    if ($request->type === 'ICT') {
                        $ticketUrl = route('ict.ticket', $request->id);
                    } else {
                        $ticketUrl = route('maintenance.edit', $request->id);
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

                // log driver still "sends" (writes MIME to log) — good for Laragon local test
                if ($mailer === 'log' || $mailer === 'array' || $isLocal) {
                    \Illuminate\Support\Facades\Mail::to($user->email)->queue($mailable);
                } else {
                    \Illuminate\Support\Facades\Mail::to($user->email)->queue($mailable);
                }

                // If PM Scheduled, also notify Super Admins/IT
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
    public function markAsRead()
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    public static function send($userId, $requestId, $type, $message)
    {
        return self::create([
            'user_id' => $userId,
            'request_id' => $requestId,
            'type' => $type,
            'message' => $message,
            'is_read' => false
        ]);
    }
}
