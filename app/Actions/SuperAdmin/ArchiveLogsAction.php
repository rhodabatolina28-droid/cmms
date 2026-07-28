<?php

namespace App\Actions\SuperAdmin;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ArchiveLogsAction
{
    /**
     * Archive old audit logs to CSV and delete them from the database.
     *
     * @return \Illuminate\Http\Response
     */
    public function execute()
    {
        // Define the cutoff date: logs older than 1 year
        // NOTE: For capstone demonstration purposes, you can change subYear() to subDays(30) or subMonths(6)
        $cutoffDate = \Carbon\Carbon::now()->subYear();
        $actor = Auth::user();

        // Super Admin archives logs for their entire branch.
        // Note: audit_logs uses the 'region' column to store branch/office scope info.
        $oldLogs = AuditLog::with('user')
            ->when($actor->branch, fn ($query) => $query->where('region', $actor->branch))
            ->where('created_at', '<', $cutoffDate)
            ->limit(5000)
            ->get();

        if ($oldLogs->isEmpty()) {
            return back()->with('error', 'No logs older than 1 year found for archiving.');
        }

        // Write CSV to temp file first so data is safely captured before DB delete
        $tempPath = tempnam(sys_get_temp_dir(), 'audit_') . '.csv';
        $tempFile = fopen($tempPath, 'w');
        fputcsv($tempFile, ['ID', 'Date', 'Office', 'Action', 'Module', 'Description', 'User', 'User ID']);

        foreach ($oldLogs as $log) {
            fputcsv($tempFile, [
                $log->id,
                $log->created_at->format('Y-m-d H:i:s'),
                $log->region ?? 'N/A',
                $log->action,
                $log->module,
                $log->description,
                $log->user ? $log->user->full_name : 'System/Unknown',
                $log->user_id
            ]);
        }
        fclose($tempFile);

        // Delete the logs from DB (Super Admin scope: entire branch).
        // Note: audit_logs uses the 'region' column to store branch/office scope info.
        AuditLog::query()
            ->when($actor->branch, fn ($query) => $query->where('region', $actor->branch))
            ->where('created_at', '<', $cutoffDate)
            ->delete();

        // Log the archiving action itself
        AuditLog::log(
            "Archived Old Logs",
            "System",
            "Exported and deleted " . $oldLogs->count() . " audit logs older than 1 year.",
            "System"
        );

        $csvFileName = 'audit_logs_archive_' . now()->format('Ymd_His') . '.csv';
        return response()->download($tempPath, $csvFileName, [
            'Content-Type' => 'text/csv',
        ])->deleteFileAfterSend(true);
    }
}
