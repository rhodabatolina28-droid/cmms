<?php

namespace App\Services;

use App\Models\User;
use App\Models\Request as RequestModel;
use App\Mail\PMScheduledMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class PMNotificationService
{
    /**
     * Notify IT staff and Super Admins about a scheduled PM.
     * Automatically filters by branch based on the PM request's branch.
     */
    public static function notifyITStaff(string $requestNumber, string $type, string $message)
    {
        try {
            // Get the PM request to find which branch it belongs to
            $request = RequestModel::where('request_number', $requestNumber)->first();
            $branch = $request?->branch;

            // Build query for IT staff and super admins
            $query = User::whereIn('role', ['super_admin', 'it'])
                ->whereNotNull('email');

            // Filter by branch to avoid cross-location notifications
            if ($branch) {
                $query->where('branch', $branch);
            }

            $admins = $query->get();

            if ($admins->isEmpty()) {
                return;
            }

            foreach ($admins as $admin) {
                Mail::to($admin->email)->queue(
                    new \App\Mail\SystemNotificationMail(
                        $admin->full_name,
                        $type,
                        $message,
                        $requestNumber
                    )
                );
            }

            $location = $branch ?: 'all branches';
            Log::info("PM IT notification sent to {$admins->count()} admin(s) for {$requestNumber} ({$location})");
        } catch (\Throwable $e) {
            Log::warning('Failed to notify IT staff about PM: ' . $e->getMessage());
        }
    }

    /**
     * Notify Division Admin about a batch of PM tickets generated for their division.
     */
    public static function notifyDivisionAdmin(string $division, int $count, string $branch = null)
    {
        try {
            // Find the division admin (user with role 'admin' in the same division)
            $query = User::where('role', 'admin')
                ->whereNotNull('email')
                ->where(function ($q) use ($division) {
                    $q->where('office', 'LIKE', "%{$division}%")
                      ->orWhere('department', 'LIKE', "%{$division}%");
                });

            if ($branch) {
                $query->where('branch', $branch);
            }

            $admins = $query->get();

            if ($admins->isEmpty()) {
                Log::info("No division admin found for {$division}");
                return;
            }

            foreach ($admins as $admin) {
                Mail::to($admin->email)->queue(
                    new \App\Mail\SystemNotificationMail(
                        $admin->full_name,
                        'PM Scheduled',
                        "PM tickets have been generated for {$division} Division ({$count} users). Please inform your personnel to coordinate with ICT for their schedule.",
                        "{$division} PM Batch"
                    )
                );
            }

            Log::info("Division admin notified for {$division} ({$count} tickets)");
        } catch (\Throwable $e) {
            Log::warning('Failed to notify division admin: ' . $e->getMessage());
        }
    }

    /**
     * Notify IT staff and Super Admin about a batch generation.
     */
    public static function notifyITStaffOfBatch(string $division, int $count, string $branch = null)
    {
        try {
            $query = User::whereIn('role', ['super_admin', 'it'])
                ->whereNotNull('email');

            if ($branch) {
                $query->where('branch', $branch);
            }

            $staff = $query->get();

            if ($staff->isEmpty()) {
                return;
            }

            foreach ($staff as $user) {
                Mail::to($user->email)->queue(
                    new \App\Mail\SystemNotificationMail(
                        $user->full_name,
                        'PM Batch Generated',
                        "New PM tickets generated for {$division} Division ({$count} users). Please conduct the PMs at your earliest convenience.",
                        "{$division} PM Batch"
                    )
                );
            }

            Log::info("IT staff notified of batch generation for {$division} ({$count} tickets)");
        } catch (\Throwable $e) {
            Log::warning('Failed to notify IT staff of batch: ' . $e->getMessage());
        }
    }
}
