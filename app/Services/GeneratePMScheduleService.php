<?php

namespace App\Services;

use App\Models\PMSchedule;
use App\Models\PMScheduleHistory;
use App\Models\InventoryAsset;
use App\Models\Request as RequestModel;
use App\Models\PreventiveMaintenance;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class GeneratePMScheduleService
{
    public function generate(PMSchedule $schedule): array
    {
        if (!$schedule->is_active) {
            Log::warning("Attempted to generate PM from inactive schedule {$schedule->id}");
            return [];
        }

        // Anti-Spam: Check if there's already an IN PROGRESS cycle
        if ($schedule->current_focus_division) {
            Log::warning("PM cycle already running for schedule {$schedule->id}, focus: {$schedule->current_focus_division}");
            return [];
        }

        $eligibleUsers = $this->getEligibleUsers($schedule);

        if (empty($eligibleUsers)) {
            return [];
        }

        // BATCH GENERATION: Process ALL eligible users in the focus division
        $actor = Auth::user();
        $now = now();
        $created = [];

        DB::beginTransaction();
        try {
            // Get the focus division name from the first user
            $firstUserData = reset($eligibleUsers);
            $focusDivision = $firstUserData['division'] ?? 'Unassigned';

            // Mark division as IN PROGRESS.
            // next_scheduled_date is intentionally NOT updated here —
            // it only advances after the full cycle (all divisions) is completed.
            $schedule->update([
                'current_focus_division' => $focusDivision,
                'last_generated_date'    => $now->toDateString(),
            ]);

            foreach ($eligibleUsers as $userId => $data) {
                $requestNumber = $this->generateRequestNumber();
                $user = User::find($userId);
                
                $endUserName   = $user?->full_name ?? 'Auto-generated';
                $endUserDiv    = $user?->office ?? $user?->department ?? $actor?->office ?? '';
                
                // Map the assets into PreventiveMaintenance attributes
                $pmAttributes = [
                    'form_no'           => $requestNumber,
                    'end_user_name'     => $endUserName,
                    'end_user_division' => $endUserDiv,
                    'maintenance_date'  => $now->toDateString(),
                ];
                
                $pmAttributes = array_merge($pmAttributes, $this->mapUserAssetsToPMForm($data['assets']));

                $pm = PreventiveMaintenance::create($pmAttributes);

                $trackingRequest = RequestModel::create([
                    'request_number'              => $requestNumber,
                    'user_id'                     => $userId,
                    'requestor_name'              => $endUserName,
                    'type'                        => 'Preventive Maintenance',
                    'status'                      => RequestModel::STATUS_SCHEDULED,
                    'linked_asset_id'             => null, // Bundled workstation PM
                    'assigned_to'                 => null,
                    'office'                      => $endUserDiv,
                    'branch'                      => $user?->branch ?? $actor?->branch ?? null,
                    'is_auto_generated'           => true,
                    'pm_schedule_id'              => $schedule->id,
                    'asset_id'                    => 0, // Bundled
                    'division_admin_review_status' => 'Approved',
                    'detail_id'                   => $pm->id,
                ]);

                if ($user) {
                    Notification::send(
                        $user->id,
                        $trackingRequest->id,
                        'PM Scheduled',
                        "A workstation preventive maintenance has been scheduled for your equipment in {$focusDivision}. Please coordinate with your ICT Unit for your schedule. Request #{$requestNumber}."
                    );
                }

                $created[] = $requestNumber;
            }

            // Log the batch generation
            $this->logGeneration($schedule, $created);

            DB::commit();

            // Notify Division Admin about the batch
            \App\Services\PMNotificationService::notifyDivisionAdmin(
                $focusDivision,
                count($created),
                $actor?->branch
            );

            // Notify IT staff and Super Admin about the batch
            \App\Services\PMNotificationService::notifyITStaffOfBatch(
                $focusDivision,
                count($created),
                $actor?->branch
            );
        } catch (\Exception $e) {
            DB::rollBack();
            // Reset focus division on failure
            $schedule->update(['current_focus_division' => null]);
            Log::error("PM Schedule batch generation failed for schedule {$schedule->id}: {$e->getMessage()}");
            throw $e;
        }

        return $created;
    }

    /**
     * Check if current division is complete and auto-advance to next.
     *
     * Cycle logic:
     * - When a division is fully done → record its completion date, advance to next division
     * - The next_scheduled_date updates per division completion (now + frequency)
     *   so each division gets its own "next due" date based on when IT was actually done
     * - When ALL divisions are done → reset current_focus_division = null (cycle complete)
     */
    public function checkAndAdvance(): ?string
    {
        $schedule = PMSchedule::active()->first();

        if (!$schedule || $schedule->is_paused) {
            return null;
        }

        $focusDivision = $schedule->current_focus_division;
        if (!$focusDivision) {
            return null;
        }

        $divisionStatus = $this->getQueueStatus($schedule);
        $divisionData   = $divisionStatus['divisions'][$focusDivision] ?? null;

        // Current division is complete when all PENDING (due) users are done.
        // not_due users are excluded from the completion check.
        $pendingInFocus = $divisionData['pending'] ?? 0;
        if (!$divisionData || $pendingInFocus > 0) {
            return null; // Still has pending users in current division
        }

        // --- Current division is complete ---
        // Update next_scheduled_date based on when THIS division finished.
        // Each division sets its own next due date = today + frequency.
        $nextDateForThisDivision = $schedule->calculateNextDate();

        $nextDivision = $divisionStatus['next_division'] ?? null;

        if ($nextDivision && $nextDivision !== $focusDivision) {
            // Update date for the completed division, then reset focus
            // so generate() can pick the next division naturally
            $schedule->update([
                'next_scheduled_date' => $nextDateForThisDivision,
                'last_generated_date' => now()->toDateString(),
                'current_focus_division' => null,
            ]);

            // Re-fetch fresh model so generate() anti-spam check reads correct DB state
            $fresh = $schedule->fresh();
            $this->generate($fresh);

            Log::info("PM advanced from {$focusDivision} to next division for schedule {$schedule->id}");
            return $nextDivision;
        }

        // --- All divisions complete — full cycle done ---
        $schedule->update([
            'next_scheduled_date'    => $nextDateForThisDivision,
            'current_focus_division' => null,
        ]);

        Log::info("PM full cycle complete for schedule {$schedule->id}. Next: {$nextDateForThisDivision}");
        return null;
    }

    public function preview(PMSchedule $schedule): array
    {
        $users = $this->getEligibleUsers($schedule);
        
        return [
            'total_matching' => count($users),
            'new_eligible' => count($users),
            'already_scheduled' => 0,
            'assets' => collect($users),
        ];
    }

    /**
     * Returns the current queue status for a schedule showing:
     * - Which division is being processed
     * - Progress per division (done/total)
     * - Next eligible user in queue
     */
    public function getQueueStatus(PMSchedule $schedule): array
    {
        $user = Auth::user();
        $query = InventoryAsset::whereIn('status', ['Active', 'Spare'])
            ->whereNotNull('assigned_to_user');
        
        // Filter by user's branch for multi-location support
        if ($user && $user->branch) {
            $query->where('branch', $user->branch);
        }

        // Filter by asset categories if specified
        if (!empty($schedule->asset_categories)) {
            $query->whereIn('category', $schedule->asset_categories);
        }

        if ($schedule->division_filter) {
            $divisionMappings = [
                'RID'  => ['RESEARCH AND INFORMATION', 'RID'],
                'AD'   => ['ADMINISTRATIVE', 'AD'],
                'FMD'  => ['FINANCIAL AND MANAGEMENT', 'FMD'],
                'COA'  => ['COMMISSION ON AUDIT', 'COA'],
                'CMD'  => ['CONCILIATION AND MEDIATION', 'CMD'],
                'VAD'  => ['VOLUNTARY ARBITRATION', 'VAD'],
                'WRED' => ['WORKPLACE RELATIONS', 'WRED'],
                'OED'  => ['EXECUTIVE DIRECTOR', 'OED'],
            ];

            $keywords = $divisionMappings[$schedule->division_filter] ?? [$schedule->division_filter];
            $query->where(function ($q) use ($keywords) {
                foreach ($keywords as $kw) {
                    $q->orWhere('department', 'LIKE', "%{$kw}%")
                      ->orWhere('office', 'LIKE', "%{$kw}%");
                }
            });
        }

        $assets = $query->get();
        $freqMonths = [
            'Monthly' => 1, 'Quarterly' => 3, 'Semi-annual' => 6, 'Annual' => 12
        ][$schedule->frequency] ?? 3;
        $yearMonth = now()->format('Y-m');

        // Get users who completed their PM within the current frequency window
        $windowStart = now()->subMonths($freqMonths)->toDateTimeString();
        $completedUserIds = RequestModel::where('pm_schedule_id', $schedule->id)
            ->where('is_auto_generated', true)
            ->where('status', 'Completed')
            ->where('created_at', '>=', $windowStart)
            ->pluck('user_id')
            ->toArray();

        $processedUsers = User::whereIn('id', $completedUserIds)->get(['id', 'full_name']);

        // Group assets by user then by division
        $grouped = [];
        foreach ($assets as $asset) {
            $uid = $asset->assigned_to_user;
            if (!isset($grouped[$uid])) {
                $grouped[$uid] = [
                    'oldest_date' => $asset->date_acquired ? \Carbon\Carbon::parse($asset->date_acquired) : now(),
                    'division' => $asset->office ?? $asset->department ?? 'Unassigned',
                    'user_name' => $asset->assignedUser?->full_name ?? "User #{$uid}",
                    'assets' => []
                ];
            }
            $grouped[$uid]['assets'][] = $asset;
            $assetDate = $asset->date_acquired ? \Carbon\Carbon::parse($asset->date_acquired) : now();
            if ($assetDate->isBefore($grouped[$uid]['oldest_date'])) {
                $grouped[$uid]['oldest_date'] = $assetDate;
            }
        }

        // Build division status
        $divisionStatus = [];
        foreach ($grouped as $uid => $data) {
            $div = $data['division'] ?: 'Unassigned';
            $monthsPassed = (now()->year * 12 + now()->month) - ($data['oldest_date']->year * 12 + $data['oldest_date']->month);
            $isDue = ($monthsPassed >= $freqMonths);
            $isDone = in_array($uid, $completedUserIds);

            if (!isset($divisionStatus[$div])) {
                $divisionStatus[$div] = [
                    'total' => 0,
                    'done' => 0,
                    'pending' => 0,
                    'not_due' => 0,
                    'users' => [],
                    'oldest_date' => $data['oldest_date'],
                ];
            }
            $divisionStatus[$div]['total']++;
            $divisionStatus[$div]['users'][] = [
                'user_id' => $uid,
                'name' => $data['user_name'],
                'oldest_date' => $data['oldest_date']->toDateString(),
                'is_done' => $isDone,
                'is_due' => $isDue,
            ];
            if ($isDone) {
                $divisionStatus[$div]['done']++;
            } elseif ($isDue) {
                $divisionStatus[$div]['pending']++;
            } else {
                $divisionStatus[$div]['not_due']++;
            }
            if ($data['oldest_date']->isBefore($divisionStatus[$div]['oldest_date'])) {
                $divisionStatus[$div]['oldest_date'] = $data['oldest_date'];
            }
        }

        // Find current focus division (division with oldest pending eligible asset)
        $focusDivision = null;
        $nextUser = null;
        $pendingDivisions = [];

        foreach ($divisionStatus as $div => $status) {
            if ($status['pending'] > 0) {
                $pendingDivisions[$div] = $status['oldest_date'];
            }
        }
        asort($pendingDivisions);
        $focusDivision = array_key_first($pendingDivisions);

        // Find next user in focus division
        if ($focusDivision && isset($divisionStatus[$focusDivision])) {
            $pendingUsers = array_filter($divisionStatus[$focusDivision]['users'], fn($u) => !$u['is_done'] && $u['is_due']);
            usort($pendingUsers, fn($a, $b) => strtotime($a['oldest_date']) - strtotime($b['oldest_date']));
            $nextUser = $pendingUsers[0] ?? null;
        }

        // Determine next division after current
        $divisionOrder = array_keys($divisionStatus);
        $nextDivision = null;
        if ($focusDivision) {
            $found = false;
            foreach ($divisionOrder as $d) {
                if ($found && $divisionStatus[$d]['pending'] > 0) {
                    $nextDivision = $d;
                    break;
                }
                if ($d === $focusDivision) $found = true;
            }
            // If no next found, wrap to first
            if (!$nextDivision && count($pendingDivisions) > 0) {
                reset($pendingDivisions);
                $nextDivision = key($pendingDivisions);
            }
        }

        return [
            'success' => true,
            'focus_division' => $focusDivision,
            'next_division' => $nextDivision,
            'next_user' => $nextUser,
            'divisions' => $divisionStatus,
            'total_users' => count($grouped),
            'total_done' => count($completedUserIds),
            'total_pending' => count($grouped) - count($completedUserIds),
        ];
    }

    private function getEligibleUsers(PMSchedule $schedule): array
    {
        $user = Auth::user();
        $query = InventoryAsset::whereIn('status', ['Active', 'Spare'])
            ->whereNotNull('assigned_to_user');
        
        // Filter by user's branch for multi-location support
        if ($user && $user->branch) {
            $query->where('branch', $user->branch);
        }

        // Filter by asset categories if specified
        if (!empty($schedule->asset_categories)) {
            $query->whereIn('category', $schedule->asset_categories);
        }

        if ($schedule->division_filter) {
            $divisionMappings = [
                'RID'  => ['RESEARCH AND INFORMATION', 'RID'],
                'AD'   => ['ADMINISTRATIVE', 'AD'],
                'FMD'  => ['FINANCIAL AND MANAGEMENT', 'FMD'],
                'COA'  => ['COMMISSION ON AUDIT', 'COA'],
                'CMD'  => ['CONCILIATION AND MEDIATION', 'CMD'],
                'VAD'  => ['VOLUNTARY ARBITRATION', 'VAD'],
                'WRED' => ['WORKPLACE RELATIONS', 'WRED'],
                'OED'  => ['EXECUTIVE DIRECTOR', 'OED'],
            ];

            $keywords = $divisionMappings[$schedule->division_filter] ?? [$schedule->division_filter];

            $query->where(function ($q) use ($keywords) {
                foreach ($keywords as $kw) {
                    $q->orWhere('department', 'LIKE', "%{$kw}%")
                      ->orWhere('office', 'LIKE', "%{$kw}%");
                }
            });
        }

        $assets = $query->get();
        $grouped = [];

        // Group by user, track their division
        foreach ($assets as $asset) {
            $uid = $asset->assigned_to_user;
            if (!isset($grouped[$uid])) {
                $grouped[$uid] = [
                    'oldest_date' => $asset->date_acquired ? \Carbon\Carbon::parse($asset->date_acquired) : now(),
                    'division' => $asset->office ?? $asset->department ?? 'Unassigned',
                    'assets' => []
                ];
            }
            $grouped[$uid]['assets'][] = $asset;
            
            $assetDate = $asset->date_acquired ? \Carbon\Carbon::parse($asset->date_acquired) : now();
            if ($assetDate->isBefore($grouped[$uid]['oldest_date'])) {
                $grouped[$uid]['oldest_date'] = $assetDate;
            }
        }

        // Define frequency BEFORE using it
        $freqMonths = [
            'Monthly'     => 1,
            'Quarterly'   => 3,
            'Semi-annual' => 6,
            'Annual'      => 12,
        ][$schedule->frequency] ?? 3;

        // Filter out users who already completed their PM within the current frequency window.
        // E.g. Semi-annual: if completed within last 6 months, skip them.
        $windowStart = now()->subMonths($freqMonths)->toDateTimeString();
        $completedUserIds = RequestModel::where('pm_schedule_id', $schedule->id)
            ->where('is_auto_generated', true)
            ->where('status', 'Completed')
            ->where('created_at', '>=', $windowStart)
            ->pluck('user_id')
            ->toArray();

        // Group eligible users by division
        $byDivision = [];
        foreach ($grouped as $uid => $data) {
            if (in_array($uid, $completedUserIds)) {
                continue;
            }

            $oldestDate = $data['oldest_date'];
            $monthsPassed = (now()->year * 12 + now()->month) - ($oldestDate->year * 12 + $oldestDate->month);

            // Eligible if the asset is at least $freqMonths old
            // (no need for modulo — any asset past the interval is due)
            if ($monthsPassed >= $freqMonths) {
                $div = $data['division'] ?: 'Unassigned';
                if (!isset($byDivision[$div])) {
                    $byDivision[$div] = [];
                }
                $byDivision[$div][$uid] = $data;
            }
        }

        if (empty($byDivision)) {
            return [];
        }

        // Find which division to focus: the one with the oldest overall eligible asset
        $divisionOldest = [];
        foreach ($byDivision as $div => $users) {
            $oldest = PHP_INT_MAX;
            foreach ($users as $uid => $data) {
                $ts = $data['oldest_date']->timestamp;
                if ($ts < $oldest) $oldest = $ts;
            }
            $divisionOldest[$div] = $oldest;
        }
        asort($divisionOldest);

        // Focus on the division with the oldest asset that still has pending users
        $focusDivision = array_key_first($divisionOldest);
        $eligible = $byDivision[$focusDivision] ?? [];

        // Sort users in this division by oldest asset first
        uasort($eligible, function ($a, $b) {
            return $a['oldest_date']->timestamp - $b['oldest_date']->timestamp;
        });

        return $eligible;
    }

    private function mapUserAssetsToPMForm(array $assets): array
    {
        $mapped = [];
        $monitors = 0;
        $printers = 0;

        foreach ($assets as $asset) {
            $cat = strtolower($asset->category ?? '');
            $specs = is_array($asset->specifications) ? $asset->specifications : json_decode($asset->specifications ?? '[]', true);
            
            if (str_contains($cat, 'desktop')) {
                $mapped['desktop_brand'] = $asset->brand;
                $mapped['desktop_model'] = $asset->model;
                $mapped['desktop_pno']   = $asset->property_number;
                $mapped['desktop_cpu']   = $specs['cpu'] ?? $specs['processor'] ?? null;
                $mapped['desktop_ram']   = $specs['ram'] ?? null;
                $mapped['desktop_gpu']   = $specs['gpu'] ?? null;
                $mapped['desktop_os']    = $specs['os'] ?? null;
                $mapped['desktop_hd1']   = $specs['hd1'] ?? $specs['storage'] ?? null;
                $mapped['desktop_hd2']   = $specs['hd2'] ?? null;
                $mapped['desktop_office'] = $specs['office'] ?? null;
                $mapped['desktop_year_purchased'] = $asset->date_acquired ? \Carbon\Carbon::parse($asset->date_acquired)->format('Y') : null;
            } elseif (str_contains($cat, 'laptop')) {
                $mapped['laptop_brand'] = $asset->brand;
                $mapped['laptop_model'] = $asset->model;
                $mapped['laptop_pno']   = $asset->property_number;
                $mapped['laptop_cpu']   = $specs['cpu'] ?? $specs['processor'] ?? null;
                $mapped['laptop_ram']   = $specs['ram'] ?? null;
                $mapped['laptop_gpu']   = $specs['gpu'] ?? null;
                $mapped['laptop_os']    = $specs['os'] ?? null;
                $mapped['laptop_hd1']   = $specs['hd1'] ?? $specs['storage'] ?? null;
                $mapped['laptop_hd2']   = $specs['hd2'] ?? null;
                $mapped['laptop_office'] = $specs['office'] ?? null;
                $mapped['laptop_year_purchased'] = $asset->date_acquired ? \Carbon\Carbon::parse($asset->date_acquired)->format('Y') : null;
            } elseif (str_contains($cat, 'monitor')) {
                $monitors++;
                if ($monitors <= 2) {
                    $mapped["monitor{$monitors}_brand"] = $asset->brand;
                    $mapped["monitor{$monitors}_model"] = $asset->model;
                    $mapped["monitor{$monitors}_pno"]   = $asset->property_number;
                }
            } elseif (str_contains($cat, 'printer') || str_contains($cat, 'printer/scanner')) {
                $printers++;
                if ($printers <= 2) {
                    $mapped["printer{$printers}_brand"] = $asset->brand;
                    $mapped["printer{$printers}_model"] = $asset->model;
                    $mapped["printer{$printers}_pno"]   = $asset->property_number;
                }
            } elseif (str_contains($cat, 'ups')) {
                $mapped['ups_brand'] = $asset->brand;
                $mapped['ups_model'] = $asset->model;
                $mapped['ups_pno']   = $asset->property_number;
            } elseif (str_contains($cat, 'scanner') && !str_contains($cat, 'printer')) {
                $mapped['scanner_brand'] = $asset->brand;
                $mapped['scanner_model'] = $asset->model;
                $mapped['scanner_pno']   = $asset->property_number;
            } elseif (str_contains($cat, 'webcam')) {
                $mapped['webcam_brand'] = $asset->brand;
                $mapped['webcam_model'] = $asset->model;
                $mapped['webcam_pno']   = $asset->property_number;
            } elseif (str_contains($cat, 'speaker') || str_contains($cat, 'earphone')) {
                $mapped['speakers_brand'] = $asset->brand;
                $mapped['speakers_model'] = $asset->model;
                $mapped['speakers_pno']   = $asset->property_number;
            }
        }
        
        return $mapped;
    }

    private function logGeneration(PMSchedule $schedule, array $requestNumbers): void
    {
        PMScheduleHistory::create([
            'pm_schedule_id' => $schedule->id,
            'action' => 'generated',
            'generated_count' => count($requestNumbers),
            'generated_at' => now(),
        ]);
    }

    private function generateRequestNumber(): string
    {
        $year = now()->format('Y');
        $user = Auth::user();
        
        // Use region + branch from database for unique identification
        $region = strtoupper($user->region ?? 'SYS');
        $branchCode = $this->getBranchCode($user->branch);
        
        $searchPrefix = "PM-{$region}-{$branchCode}-{$year}";
        
        $last = RequestModel::where('request_number', 'LIKE', "{$searchPrefix}-%")
            ->orderByDesc('request_number')
            ->lockForUpdate()
            ->value('request_number');

        $next = 1;
        if ($last) {
            $parts = explode('-', $last);
            $next = (int) end($parts) + 1;
        }

        return "PM-{$region}-{$branchCode}-{$year}-" . str_pad($next, 4, '0', STR_PAD_LEFT);
    }
    
    private function getBranchCode(?string $branch): string
    {
        if (!$branch) {
            return 'SYS';
        }
        
        // Use the actual branch name from database, just remove spaces and special characters
        // This ensures each branch gets its unique identifier based on actual data
        $clean = preg_replace('/[^A-Z0-9]/', '', strtoupper($branch));
        
        // If branch name is too long (>10 chars), use first meaningful part
        if (strlen($clean) > 10) {
            // Try to extract meaningful abbreviation from the branch name
            $branchUpper = strtoupper($branch);
            
            // Common patterns in Philippine government branches
            if (str_contains($branchUpper, 'RCMB')) {
                $clean = 'RCMB';
            } elseif (str_contains($branchUpper, 'NATIONAL CAPITAL')) {
                $clean = 'NCR';
            } elseif (preg_match('/REGION\s+([IVXLCDM]+)/i', $branchUpper, $matches)) {
                $clean = 'R' . strtoupper($matches[1]);
            } elseif (str_contains($branchUpper, 'CAR')) {
                $clean = 'CAR';
            } elseif (str_contains($branchUpper, 'BARMM')) {
                $clean = 'BARMM';
            } else {
                // Fallback: use first 4-5 characters
                $clean = substr($clean, 0, 5);
            }
        }
        
        return $clean ?: 'SYS';
    }
}