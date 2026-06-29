<?php

namespace App\Console\Commands;

use App\Models\Request as RequestModel;
use App\Models\User;
use App\Services\PMNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendPMDueReminders extends Command
{
    protected $signature = 'pm:send-reminders';
    protected $description = 'Send weekly pending PM summary to IT staff and Super Admin';

    public function handle(): int
    {
        $branch = request('branch') ?? null; // Optional: filter by branch
        
        // Get all pending PM requests grouped by division
        $pendingPMs = RequestModel::where('type', 'Preventive Maintenance')
            ->where('is_auto_generated', true)
            ->whereIn('status', ['Scheduled', 'Ongoing', 'Awaiting Signature'])
            ->when($branch, fn($q) => $q->where('branch', $branch))
            ->get();

        if ($pendingPMs->isEmpty()) {
            $this->info('No pending PM tickets found.');
            return Command::SUCCESS;
        }

        // Group by division
        $groupedByDivision = [];
        foreach ($pendingPMs as $pm) {
            $div = $pm->office ?? 'Unassigned';
            if (!isset($groupedByDivision[$div])) {
                $groupedByDivision[$div] = ['total' => 0, 'completed' => 0, 'pending' => 0];
            }
            $groupedByDivision[$div]['total']++;
            $groupedByDivision[$div]['pending']++;
        }

        // Get completed counts for the same cycle
        $completedPMs = RequestModel::where('type', 'Preventive Maintenance')
            ->where('is_auto_generated', true)
            ->where('status', 'Completed')
            ->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [now()->format('Y-m')])
            ->when($branch, fn($q) => $q->where('branch', $branch))
            ->get();

        foreach ($completedPMs as $pm) {
            $div = $pm->office ?? 'Unassigned';
            if (isset($groupedByDivision[$div])) {
                $groupedByDivision[$div]['completed']++;
                $groupedByDivision[$div]['pending']--;
            }
        }

        // Build summary message
        $summaryLines = [];
        $totalPending = 0;
        $totalCompleted = 0;
        
        foreach ($groupedByDivision as $div => $data) {
            $summaryLines[] = "{$div}: {$data['total']} tickets generated, {$data['completed']} completed, {$data['pending']} pending";
            $totalPending += $data['pending'];
            $totalCompleted += $data['completed'];
        }

        $summaryMessage = "Weekly PM Pending Summary\n\n";
        $summaryMessage .= implode("\n", $summaryLines);
        $summaryMessage .= "\n\nTOTAL: {$totalPending} pending / {$totalCompleted} completed\n";
        $summaryMessage .= "\nPlease log in to your dashboard to conduct the remaining PMs.";

        // Send to IT staff and Super Admin
        $query = User::whereIn('role', ['super_admin', 'it'])
            ->whereNotNull('email');

        if ($branch) {
            $query->where('branch', $branch);
        }

        $staff = $query->get();

        if ($staff->isEmpty()) {
            $this->info('No IT staff or Super Admin found to notify.');
            return Command::SUCCESS;
        }

        $sent = 0;
        foreach ($staff as $user) {
            Mail::to($user->email)->queue(
                new \App\Mail\SystemNotificationMail(
                    $user->full_name,
                    'PM Pending Summary',
                    $summaryMessage,
                    'Weekly Summary'
                )
            );
            $sent++;
            $this->info("Summary sent to {$user->email}");
        }

        $this->info("Sent PM pending summary to {$sent} IT staff/Super Admin(s).");
        Log::info("PM pending summary sent to {$sent} staff: {$totalPending} pending, {$totalCompleted} completed");
        
        return Command::SUCCESS;
    }
}
