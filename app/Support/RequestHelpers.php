<?php

namespace App\Support;

use App\Models\Request as RequestModel;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Shared helper methods to eliminate duplicate code across controllers and services.
 * All methods are static so they can be called from any class.
 */
class RequestHelpers
{
    /**
     * Generate a unique request number for ICT or PM tickets.
     * Format: {PREFIX}-{REGION}-{BRANCHCODE}-{YEAR}-{NUMBER}
     *
     * @param string $type 'ICT' or 'PM'
     * @param User|null $actorUser Used in cron context where Auth::user() is null
     */
    public static function generateRequestNumber(string $type, ?User $actorUser = null): string
    {
        $prefix = $type === 'ICT' ? 'REQ' : 'PM';
        $year = date('Y');

        $user = $actorUser ?? Auth::user();
        $region = strtoupper($user->region ?? 'SYS');
        $branchCode = self::getBranchCode($user->branch);

        $searchPrefix = "{$prefix}-{$region}-{$branchCode}-{$year}";

        // Use MySQL advisory lock to prevent race conditions
        $lockName = "request_number_{$region}_{$branchCode}_{$year}";
        $lockTimeout = 10;

        $acquired = DB::select("SELECT GET_LOCK(?, ?) AS acquired", [$lockName, $lockTimeout]);

        if (!($acquired[0]->acquired ?? false)) {
            Log::warning("Could not acquire advisory lock for request number generation (prefix: {$searchPrefix}). Proceeding without lock.");
        }

        try {
            $last = RequestModel::withTrashed()
                ->where('request_number', 'LIKE', "{$searchPrefix}-%")
                ->orderByDesc('request_number')
                ->value('request_number');

            $next = 1;
            if ($last) {
                $parts = explode('-', $last);
                $next = (int) end($parts) + 1;
            }

            return "{$prefix}-{$region}-{$branchCode}-{$year}-" . str_pad($next, 4, '0', STR_PAD_LEFT);
        } finally {
            DB::select("SELECT RELEASE_LOCK(?)", [$lockName]);
        }
    }

    /**
     * Convert a branch name to a short code for request numbering.
     */
    public static function getBranchCode(?string $branch): string
    {
        if (!$branch) {
            return 'SYS';
        }

        $branchUpper = strtoupper($branch);

        $mapping = [
            'RCMB' => 'RCMB',
            'NATIONAL CAPITAL REGION' => 'NCR',
            'NCR' => 'NCR',
            'REGION I' => 'RI',
            'REGION II' => 'RII',
            'REGION III' => 'RIII',
            'REGION IV-A' => 'R4A',
            'REGION IV-B' => 'R4B',
            'REGION V' => 'RV',
            'REGION VI' => 'RVI',
            'REGION VII' => 'RVII',
            'REGION VIII' => 'RVIII',
            'REGION IX' => 'RIX',
            'REGION X' => 'RX',
            'REGION XI' => 'RXI',
            'REGION XII' => 'RXII',
            'REGION XIII' => 'RXIII',
            'CAR' => 'CAR',
            'BARMM' => 'BARMM',
        ];

        foreach ($mapping as $keyword => $code) {
            if (str_contains($branchUpper, $keyword)) {
                return $code;
            }
        }

        $clean = preg_replace('/[^A-Z0-9]/', '', $branchUpper);
        return substr($clean, 0, 4) ?: 'SYS';
    }

    /**
     * Save a base64 signature image to storage/app/public/signatures/.
     */
    public static function saveSignature(?string $base64Data, string $type, string $name): ?string
    {
        if (empty($base64Data) || !str_contains($base64Data, 'data:image')) {
            return null;
        }

        try {
            $image = str_replace('data:image/png;base64,', '', $base64Data);
            $image = str_replace(' ', '+', $image);

            $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '', str_replace(' ', '_', $name));
            if (empty($safeName)) {
                $safeName = 'signature';
            }

            $filename = $type . '_' . $safeName . '_' . time() . '.png';
            $filepath = 'signatures/' . $filename;

            Storage::disk('public')->put($filepath, base64_decode($image));

            return $filepath;
        } catch (\Exception $e) {
            Log::error('Signature save failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if the authenticated user can access a given ticket.
     */
    public static function checkTicketAccess($trackingRequest): void
    {
        if (!RequestAuthorization::canViewIctTicket(Auth::user(), $trackingRequest)) {
            abort(403, 'Unauthorized access to this request.');
        }
    }
}