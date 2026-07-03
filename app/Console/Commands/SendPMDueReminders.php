<?php

namespace App\Console\Commands;

use App\Models\Request as RequestModel;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendPMDueReminders extends Command
{
    protected $signature   = 'pm:send-reminders';
    protected $description = 'Send weekly pending PM summary to IT staff and Super Admin per branch';

    public function handle(): int
    {
        // In cron context, request() is always null.
        // Derive branches from active PM schedule creators instead.
        $branches = \App\Models\PMSchedule::active()
            ->with('creator')
            ->get()
            ->map(fn($s) => $s->creator?->branch)
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        // Fallback: no active schedules with a branch — send once with no branch filter
        if (empty($branches)) {
            $branches = [null];
        }

        $totalSent = 0;
        foreach ($branches as $branch) {
            $totalSent += $this->sendSummaryForBranch($branch);
        }

        $this->info("Done. Sent summaries for " . count($branches) . " branch(es). Total emails: {$totalSent}");
        return Command::SUCCESS;
    }

    /**
     * Build and send the PM pending summary for one branch.
     * Returns the number of emails sent.
     */
    private function sendSummaryForBranch(?string $branch): int
    {
        $branchLabel = $branch ?? 'All Branches';

        // Pending PM requests for this branch
        $pendingPMs = RequestModel::where('type', 'Preventive Maintenance')
            ->where('is_auto_generated', true)
            ->whereIn('status', ['Scheduled', 'Ongoing', 'Awaiting Signature'])
            ->when($branch, fn($q) => $q->where('branch', $branch))
            ->get();

        if ($pendingPMs->isEmpty()) {
            $this->info("  [{$branchLabel}] No pending PM tickets — skipping.");
            return 0;
        }

        // Group by division
        $grouped = [];
        foreach ($pendingPMs as $pm) {
            $div = $pm->office ?? 'Unassigned';
            $grouped[$div] ??= ['total' => 0, 'completed' => 0, 'pending' => 0];
            $grouped[$div]['total']++;
            $grouped[$div]['pending']++;
        }

        // Add completed counts for the current month
        $completedPMs = RequestModel::where('type', 'Preventive Maintenance')
            ->where('is_auto_generated', true)
            ->where('status', 'Completed')
            ->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [now()->format('Y-m')])
            ->when($branch, fn($q) => $q->where('branch', $branch))
            ->get();

        foreach ($completedPMs as $pm) {
            $div = $pm->office ?? 'Unassigned';
            if (isset($grouped[$div])) {
                $grouped[$div]['completed']++;
                $grouped[$div]['pending'] = max(0, $grouped[$div]['pending'] - 1);
            }
        }

        // Build summary message
        $totalPending   = 0;
        $totalCompleted = 0;
        $lines          = [];

        foreach ($grouped as $div => $data) {
            $lines[]        = "  {$div}: {$data['total']} generated, {$data['completed']} completed, {$data['pending']} pending";
            $totalPending   += $data['pending'];
            $totalCompleted += $data['completed'];
        }

        $summaryMessage  = "Weekly PM Pending Summary — {$branchLabel}\n";
        $summaryMessage .= "Generated: " . now()->format('F d, Y') . "\n\n";
        $summaryMessage .= implode("\n", $lines);
        $summaryMessage .= "\n\nTOTAL: {$totalPending} pending / {$totalCompleted} completed\n";
        $summaryMessage .= "\nPlease log in to your CMMS dashboard to conduct the remaining PMs.";

        // Notify IT staff and Super Admins in this branch only
        $staff = User::whereIn('role', ['super_admin', 'it'])
            ->where('is_active', true)
            ->whereNotNull('email')
            ->when($branch, fn($q) => $q->where('branch', $branch))
            ->get();

        if ($staff->isEmpty()) {
            $this->info("  [{$branchLabel}] No IT staff or Super Admin found — skipping.");
            return 0;
        }

        $sent = 0;
        foreach ($staff as $user) {
            try {
                Mail::to($user->email)->queue(
                    new \App\Mail\SystemNotificationMail(
                        $user->full_name,
                        'PM Weekly Summary',
                        $summaryMessage,
                        'SYSTEM',
                        null,
                        $branch,
                        $user->region ?? null
                    )
                );
                $sent++;
                $this->info("  [{$branchLabel}] Summary sent to {$user->email}");
            } catch (\Throwable $e) {
                $this->warn("  [{$branchLabel}] Failed to send to {$user->email}: {$e->getMessage()}");
                Log::warning("PM reminder email failed for {$user->email}: {$e->getMessage()}");
            }
        }

        Log::info("PM weekly summary sent for [{$branchLabel}]: {$sent} email(s), {$totalPending} pending, {$totalCompleted} completed");
        return $sent;
    }
}
