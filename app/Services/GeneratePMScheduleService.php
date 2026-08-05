<?php

namespace App\Services;

use App\Models\PMSchedule;
use App\Models\PMCycle;
use App\Models\PMDivisionSchedule;
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
    /**
     * Resolve the actor (user performing the action).
     * In HTTP context: returns Auth::user().
     * In console/cron context: Auth::user() is null, so falls back to
     * the schedule's creator, or the first active super_admin.
     * This ensures branch scoping works correctly in all contexts.
     */
    private function resolveActor(PMSchedule $schedule): ?User
    {
        // HTTP context — logged-in user
        if (Auth::check()) {
            return Auth::user();
        }

        // Cron context — use the schedule's creator (has branch context)
        if ($schedule->created_by) {
            $creator = User::find($schedule->created_by);
            if ($creator) {
                return $creator;
            }
        }

        // Last resort fallback — first active super_admin
        return User::where('role', 'super_admin')
            ->where('is_active', true)
            ->first();
    }
    public function generate(PMSchedule $schedule): array
    {
        if (!$schedule->is_active) {
            Log::warning("Attempted to generate PM from inactive schedule {$schedule->id}");
            return [];
        }

        // Start Date Guard: Do not generate if next_scheduled_date is still in the future.
        // The schedule should only generate on or after its next_scheduled_date.
        if ($schedule->next_scheduled_date && now()->lt(\Carbon\Carbon::parse($schedule->next_scheduled_date)->startOfDay())) {
            Log::info("PM schedule {$schedule->id} not yet due. next_scheduled_date: {$schedule->next_scheduled_date}");
            return ['__not_due__' => $schedule->next_scheduled_date->toDateString()];
        }

        // Anti-Spam: Check if there's already an IN PROGRESS cycle with requests
        if ($schedule->current_focus_division) {
            // Check if there are already pending requests for this division
            // Uses actual system status values: Scheduled, Ongoing, Awaiting Signature
            $existingPending = \App\Models\Request::where('pm_schedule_id', $schedule->id)
                ->where('is_auto_generated', true)
                ->whereIn('status', ['Scheduled', 'Ongoing', 'Awaiting Signature'])
                ->exists();

            if ($existingPending) {
                Log::warning("PM cycle already running for schedule {$schedule->id}, focus: {$schedule->current_focus_division}");
                return [];
            }
            // If no pending requests, this is a resumed/advanced cycle — continue generation
        }

        // Cooldown Guard: If no active cycle (idle), check the last completed cycle's
        // next_scheduled_at to prevent premature re-generation.
        if (!$schedule->current_cycle_id) {
            $lastCycle = PMCycle::where('pm_schedule_id', $schedule->id)
                ->whereNotNull('completed_at')
                ->latest('completed_at')
                ->first();

            if ($lastCycle) {
                // Find the soonest next_scheduled_at across all divisions in the last cycle
                $soonestNextDate = PMDivisionSchedule::where('pm_cycle_id', $lastCycle->id)
                    ->whereNotNull('next_scheduled_at')
                    ->min('next_scheduled_at');

                if ($soonestNextDate && now()->lt(\Carbon\Carbon::parse($soonestNextDate))) {
                    Log::warning("PM schedule {$schedule->id} is in cooldown. Next allowed: {$soonestNextDate}");
                    return ['__cooldown__' => $soonestNextDate]; // Signal cooldown to caller
                }
            }
        }

        $eligibleUsers = $this->getEligibleUsers($schedule);

        if (empty($eligibleUsers)) {
            return [];
        }

        // CYCLE MANAGEMENT: Get or create the active PMCycle for this generation run.
        // If no current_cycle_id exists on the schedule, this is a brand-new cycle.
        $actor = $this->resolveActor($schedule);
        $now = now();

        $created = [];

        DB::beginTransaction();
        try {
            $activeCycle = $schedule->current_cycle_id
                ? PMCycle::find($schedule->current_cycle_id)
                : null;

            if (!$activeCycle) {
                // Start a new cycle — increment cycle_count
                $newCycleNumber = ($schedule->cycle_count ?? 0) + 1;
                $activeCycle = PMCycle::create([
                    'pm_schedule_id' => $schedule->id,
                    'cycle_number'   => $newCycleNumber,
                    'started_at'     => $now,
                ]);
                $schedule->update([
                    'current_cycle_id' => $activeCycle->id,
                    'cycle_count'      => $newCycleNumber,
                ]);
                Log::info("PM Cycle #{$newCycleNumber} started for schedule {$schedule->id} (cycle_id: {$activeCycle->id})");
            }

            // Get the focus division name from the first user
            $firstUserData = reset($eligibleUsers);
            $focusDivision = $firstUserData['division'] ?? 'Unassigned';

            // Mark division as IN PROGRESS on the schedule
            $schedule->update(['current_focus_division' => $focusDivision]);

            foreach ($eligibleUsers as $userId => $data) {
                $requestNumber = $this->generateRequestNumber($actor);
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
     * Returns: [$nextDivision, $cycleComplete]
     *  - $nextDivision (string|null): name of the next division to process,
     *    or null if the cycle is still in progress OR the full cycle just completed.
     *  - $cycleComplete (bool): true when ALL divisions are done and next_scheduled_date was advanced.
     *
     * Cycle logic:
     * - When a division is fully done → record its completion, advance to next division
     * - When ALL divisions are done → reset current_focus_division = null, advance next_scheduled_date
     */
    public function checkAndAdvance(PMSchedule $schedule): array
    {
        if (!$schedule->is_active || $schedule->is_paused) {
            return [null, false];
        }

        $focusDivision = $schedule->current_focus_division;
        if (!$focusDivision) {
            return [null, false];
        }

        // Division is complete when ALL requests for the current focus division
        // are Completed (no more Scheduled or In Progress requests).
        $pendingRequests = RequestModel::where('pm_schedule_id', $schedule->id)
            ->where('is_auto_generated', true)
            ->whereIn('status', ['Scheduled', 'Ongoing', 'Awaiting Signature'])
            ->where('office', $focusDivision)
            ->count();

        if ($pendingRequests > 0) {
            return [null, false]; // Still has unfinished requests in current division
        }

        // Also verify there ARE completed requests for this division in this cycle
        // (guards against advancing on a division that was never started)
        $activeCycle = $schedule->current_cycle_id
            ? \App\Models\PMCycle::find($schedule->current_cycle_id)
            : null;

        $completedCount = RequestModel::where('pm_schedule_id', $schedule->id)
            ->where('is_auto_generated', true)
            ->where('status', 'Completed')
            ->where('office', $focusDivision)
            ->when($activeCycle, fn($q) => $q->where('created_at', '>=', $activeCycle->started_at))
            ->count();

        if ($completedCount === 0) {
            return [null, false]; // No completed requests yet for this division
        }

        // CRITICAL: Verify ALL eligible users (with Active assets) in this division
        // have been processed. This prevents advancing when some users were never
        // generated (e.g. due to division name mismatch, or other filtering issues).
        $totalEligibleUsers = \App\Models\InventoryAsset::where('status', 'Active')
            ->whereNotNull('assigned_to_user')
            ->where('office', $focusDivision)
            ->distinct('assigned_to_user')
            ->count('assigned_to_user');

        $uniqueCompletedUsers = RequestModel::where('pm_schedule_id', $schedule->id)
            ->where('is_auto_generated', true)
            ->where('status', 'Completed')
            ->where('office', $focusDivision)
            ->when($activeCycle, fn($q) => $q->where('created_at', '>=', $activeCycle->started_at))
            ->distinct('user_id')
            ->count('user_id');

        if ($uniqueCompletedUsers < $totalEligibleUsers) {
            Log::warning("PM division '{$focusDivision}' has {$uniqueCompletedUsers}/{$totalEligibleUsers} users completed. "
                . "Cannot advance — not all eligible users have been processed yet.");
            return [null, false];
        }

        // --- Current division is complete ---
        // Record this division's completion date and compute its next schedule
        // Scoped to the CURRENT CYCLE — no cross-cycle contamination.
        $completedDate    = now()->toDateString();
        $nextDivisionDate = $schedule->calculateNextDate($completedDate);
        $currentCycleId   = $schedule->current_cycle_id;

        if ($currentCycleId) {
            PMDivisionSchedule::updateOrCreate(
                ['pm_cycle_id' => $currentCycleId, 'division_name' => $focusDivision],
                [
                    'pm_schedule_id'  => $schedule->id,
                    'last_completed_at' => $completedDate,
                    'next_scheduled_at' => $nextDivisionDate,
                ]
            );
        }

        Log::info("PM division '{$focusDivision}' completed (cycle #{$schedule->cycle_count}). Next scheduled: {$nextDivisionDate}");

        // Find the next division that has eligible users not yet processed in this cycle
        $nextDivision = $this->getNextEligibleDivision($schedule, $focusDivision);

        if ($nextDivision) {
            // More divisions to process — clear focus so cron advances to next division
            $schedule->update(['current_focus_division' => null]);
            Log::info("PM advanced from '{$focusDivision}' to '{$nextDivision}' for schedule {$schedule->id}");
            return [$nextDivision, false];
        }

        // --- All divisions complete — full cycle done ---
        // Mark the PMCycle record as completed — history is PRESERVED.
        // Next time Generate PM is triggered, a brand-new cycle will be created.
        if ($currentCycleId) {
            PMCycle::where('id', $currentCycleId)->update(['completed_at' => now()]);
        }

        // Reset the schedule: clear focus and current_cycle_id so next Generate starts fresh
        $schedule->update([
            'current_focus_division' => null,
            'current_cycle_id'       => null,
        ]);

        Log::info("PM full cycle #{$schedule->cycle_count} complete for schedule {$schedule->id}. Cycle record preserved for audit.");
        return [null, true];
    }

    /**
     * Find the next division that still has eligible users not yet processed
     * in the CURRENT CYCLE. Ordered by oldest asset date (government priority).
     */
    private function getNextEligibleDivision(PMSchedule $schedule, string $currentDivision): ?string
    {
        $actor = $this->resolveActor($schedule);
        $query = InventoryAsset::where('status', 'Active')
            ->whereNotNull('assigned_to_user');

        if ($actor && $actor->branch) {
            $query->where('branch', $actor->branch);
        }

        if (!empty($schedule->asset_categories)) {
            $query->whereIn('category', $schedule->asset_categories);
        }

        // Apply division filter to prevent cycle-advance into other divisions
        $this->applyDivisionFilter($query, $schedule);

        $assets = $query->get();

        // Get divisions already COMPLETED in the CURRENT CYCLE (not all cycles)
        $completedDivisionsThisCycle = [];
        if ($schedule->current_cycle_id) {
            $completedDivisionsThisCycle = PMDivisionSchedule::where('pm_cycle_id', $schedule->current_cycle_id)
                ->whereNotNull('last_completed_at')
                ->pluck('division_name')
                ->toArray();
        }

        // Also include the current division as done (we just finished it)
        $completedDivisionsThisCycle[] = $currentDivision;

        // Get users with active requests in this cycle (in-progress, not yet complete)
        $inProgressUserIds = RequestModel::where('pm_schedule_id', $schedule->id)
            ->where('is_auto_generated', true)
            ->whereIn('status', ['Scheduled', 'Ongoing', 'Awaiting Signature'])
            ->pluck('user_id')
            ->toArray();

        // Group unprocessed users by division, track oldest asset date
        $divisionOldest = [];
        foreach ($assets as $asset) {
            $uid = $asset->assigned_to_user;
            if (in_array($uid, $inProgressUserIds)) continue;

            $div = $asset->office ?? $asset->department ?? 'Unassigned';
            if (in_array($div, $completedDivisionsThisCycle)) continue;

            $date = $asset->date_acquired
                ? \Carbon\Carbon::parse($asset->date_acquired)->timestamp
                : now()->timestamp;

            if (!isset($divisionOldest[$div]) || $date < $divisionOldest[$div]) {
                $divisionOldest[$div] = $date;
            }
        }

        if (empty($divisionOldest)) {
            return null;
        }

        asort($divisionOldest);
        return array_key_first($divisionOldest);
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
        $actor = $this->resolveActor($schedule);
        $query = InventoryAsset::where('status', 'Active')
            ->whereNotNull('assigned_to_user');
        
        // Filter by user's branch for multi-location support
        if ($actor && $actor->branch) {
            $query->where('branch', $actor->branch);
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

        // Get users who completed their PM within the current cycle window
        if ($schedule->current_cycle_id) {
            $activeCycle = \App\Models\PMCycle::find($schedule->current_cycle_id);
            $cycleStart = $activeCycle ? $activeCycle->started_at->toDateTimeString() : now()->toDateTimeString();
        } else {
            $lastCycle = \App\Models\PMCycle::where('pm_schedule_id', $schedule->id)
                ->whereNotNull('completed_at')
                ->latest('completed_at')
                ->first();
                
            $cycleStart = $lastCycle 
                ? $lastCycle->started_at->toDateTimeString()
                : now()->subMonths($freqMonths)->toDateTimeString();
        }

        $completedUserIds = RequestModel::where('pm_schedule_id', $schedule->id)
            ->where('is_auto_generated', true)
            ->where('status', 'Completed')
            ->where('created_at', '>=', $cycleStart)
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

        // Find current focus division based on DB or computation
        $dbFocus = $schedule->current_focus_division;
        $pendingDivisions = [];

        foreach ($divisionStatus as $div => $status) {
            if ($status['pending'] > 0) {
                $pendingDivisions[$div] = $status['oldest_date'];
            }
        }
        asort($pendingDivisions);
        
        // If DB has a focus, use it. Otherwise, compute it.
        $focusDivision = $dbFocus ?: array_key_first($pendingDivisions);

        // Sort the divisions array so it appears perfectly in the UI
        uksort($divisionStatus, function($a, $b) use ($focusDivision, $divisionStatus) {
            // 1. Focus Division goes first
            if ($a === $focusDivision) return -1;
            if ($b === $focusDivision) return 1;

            // 2. Sort by pending status (pending > completed)
            $aPending = $divisionStatus[$a]['pending'] > 0;
            $bPending = $divisionStatus[$b]['pending'] > 0;
            
            if ($aPending && !$bPending) return -1;
            if (!$aPending && $bPending) return 1;

            // 3. If both are pending (or both not), sort by oldest_date
            return $divisionStatus[$a]['oldest_date']->timestamp <=> $divisionStatus[$b]['oldest_date']->timestamp;
        });

        // Find next user in focus division
        $nextUser = null;
        if ($focusDivision && isset($divisionStatus[$focusDivision])) {
            $pendingUsers = array_filter($divisionStatus[$focusDivision]['users'], fn($u) => !$u['is_done'] && $u['is_due']);
            usort($pendingUsers, fn($a, $b) => strtotime($a['oldest_date']) - strtotime($b['oldest_date']));
            $nextUser = $pendingUsers[0] ?? null;
        }

        // Determine next division after current focus
        $divisionOrder = array_keys($divisionStatus);
        $nextDivision = null;
        if ($focusDivision) {
            $found = false;
            foreach ($divisionOrder as $d) {
                if ($found && ($divisionStatus[$d]['pending'] ?? 0) > 0) {
                    $nextDivision = $d;
                    break;
                }
                if ($d === $focusDivision) $found = true;
            }
        }

        $cycleComplete = empty($pendingDivisions);

        return [
            'success' => true,
            'cycle_complete' => $cycleComplete,
            'next_scheduled_date' => $schedule->next_scheduled_date?->toDateString(),
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
        $actor = $this->resolveActor($schedule);
        // Only Active assets (assigned to users) are eligible for PM.
        // Spare assets have no assigned user, so they are excluded.
        // Disposed/Scrapped assets are also excluded automatically.
        $query = InventoryAsset::where('status', 'Active')
            ->whereNotNull('assigned_to_user');
        
        // Filter by actor's branch for multi-location support
        // Works in both HTTP (Auth::user()) and cron (schedule creator) contexts
        if ($actor && $actor->branch) {
            $query->where('branch', $actor->branch);
        }

        // Filter by asset categories if specified
        if (!empty($schedule->asset_categories)) {
            $query->whereIn('category', $schedule->asset_categories);
        }

        // Apply division filter — restricts generation to one division if configured.
        // No-op when division_filter is null (all divisions included).
        $this->applyDivisionFilter($query, $schedule);

        // NOTE: Division filter is applied above via applyDivisionFilter()
        // This ensures we include only users in the configured division

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

        // Filter out users whose DIVISION has already been completed in the CURRENT CYCLE ONLY.
        // By scoping to current_cycle_id, we guarantee that completed divisions from
        // previous cycles do NOT bleed into this cycle.
        $completedDivisions = [];
        if ($schedule->current_cycle_id) {
            $completedDivisions = \App\Models\PMDivisionSchedule::where('pm_cycle_id', $schedule->current_cycle_id)
                ->whereNotNull('last_completed_at')
                ->pluck('division_name')
                ->toArray();
        }

        // Also get users with active requests in the current wave (not yet complete)
        $inProgressUserIds = RequestModel::where('pm_schedule_id', $schedule->id)
            ->where('is_auto_generated', true)
            ->whereIn('status', ['Scheduled', 'Ongoing', 'Awaiting Signature'])
            ->pluck('user_id')
            ->toArray();

        // Get users who already have a COMPLETED request in the current cycle.
        // This prevents re-generating a PM for a user whose division was not yet
        // recorded in pm_division_schedules (e.g. completed outside MaintenanceController).
        $completedUserIdsThisCycle = [];
        if ($schedule->current_cycle_id) {
            $activeCycle = \App\Models\PMCycle::find($schedule->current_cycle_id);
            if ($activeCycle) {
                $completedUserIdsThisCycle = RequestModel::where('pm_schedule_id', $schedule->id)
                    ->where('is_auto_generated', true)
                    ->where('status', 'Completed')
                    ->where('created_at', '>=', $activeCycle->started_at)
                    ->pluck('user_id')
                    ->toArray();
            }
        }

        // Group eligible users by division
        // NOTE: All users with Active assets are included regardless of asset age.
        // The monthsPassed/due date filter has been removed so that users with
        // newly acquired assets are still included in the PM cycle.
        $byDivision = [];
        foreach ($grouped as $uid => $data) {
            // Skip users in divisions already completed this cycle
            $div = $data['division'] ?: 'Unassigned';
            if (in_array($div, $completedDivisions)) {
                continue;
            }
            // Skip users already in an active wave
            if (in_array($uid, $inProgressUserIds)) {
                continue;
            }
            // Skip users who already completed their PM in this cycle
            if (in_array($uid, $completedUserIdsThisCycle)) {
                continue;
            }

            if (!isset($byDivision[$div])) {
                $byDivision[$div] = [];
            }
            $byDivision[$div][$uid] = $data;
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

    /**
     * Apply the schedule's division_filter to an asset query.
     * Maps short codes (e.g. 'RID') to full division name patterns.
     * No-op when division_filter is null/empty — all divisions included.
     */
    private function applyDivisionFilter(\Illuminate\Database\Eloquent\Builder $query, PMSchedule $schedule): void
    {
        if (!$schedule->division_filter) {
            return;
        }

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
                $q->orWhere('office', 'LIKE', "%{$kw}%")
                  ->orWhere('department', 'LIKE', "%{$kw}%");
            }
        });
    }

    private function mapUserAssetsToPMForm(array $assets): array
    {
        $mapped = [];
        $monitors = 0;
        $printers = 0;

        foreach ($assets as $asset) {
            $cat = strtolower($asset->category ?? '');
            $name = strtolower($asset->item_name ?? '');
            $typeStr = $cat . ' ' . $name;
            $specs = is_array($asset->specifications) ? $asset->specifications : json_decode($asset->specifications ?? '[]', true);

            // Peripherals stored inside the parent Desktop/Laptop specs as set_includes[].
            // Peripherals stored as rich objects: [{'type':'Speaker','brand':'HT','model':'HT-208'}, ...]
            // We surface them in the PM form without separate DB records.
            $rawIncludes = $specs['set_includes'] ?? [];
            foreach ($rawIncludes as $peripheral) {
                // Support both legacy plain strings ('Speaker') and rich objects (['type'=>'Speaker','brand'=>...])
                if (is_string($peripheral)) {
                    $pType  = strtolower($peripheral);
                    $pBrand = null;
                    $pModel = null;
                } else {
                    $pType  = strtolower($peripheral['type'] ?? '');
                    $pBrand = $peripheral['brand'] ?? null;
                    $pModel = $peripheral['model'] ?? null;
                }
                $pno = 'Included in set ' . $asset->property_number;

                match (true) {
                    $pType === 'speaker' && empty($mapped['speakers_pno']) => (function () use (&$mapped, $pBrand, $pModel, $pno) {
                        $mapped['speakers_brand'] = $pBrand;
                        $mapped['speakers_model'] = $pModel;
                        $mapped['speakers_pno']   = $pno;
                    })(),
                    $pType === 'ups' && empty($mapped['ups_pno']) => (function () use (&$mapped, $pBrand, $pModel, $pno) {
                        $mapped['ups_brand'] = $pBrand;
                        $mapped['ups_model'] = $pModel;
                        $mapped['ups_pno']   = $pno;
                    })(),
                    in_array($pType, ['camera', 'webcam']) && empty($mapped['webcam_pno']) => (function () use (&$mapped, $pBrand, $pModel, $pno) {
                        $mapped['webcam_brand'] = $pBrand;
                        $mapped['webcam_model'] = $pModel;
                        $mapped['webcam_pno']   = $pno;
                    })(),
                    $pType === 'printer' && empty($mapped['printer1_pno']) => (function () use (&$mapped, &$printers, $pBrand, $pModel, $pno) {
                        $printers++;
                        $mapped["printer{$printers}_brand"] = $pBrand;
                        $mapped["printer{$printers}_model"] = $pModel;
                        $mapped["printer{$printers}_pno"]   = $pno;
                    })(),
                    $pType === 'scanner' && empty($mapped['scanner_pno']) => (function () use (&$mapped, $pBrand, $pModel, $pno) {
                        $mapped['scanner_brand'] = $pBrand;
                        $mapped['scanner_model'] = $pModel;
                        $mapped['scanner_pno']   = $pno;
                    })(),
                    in_array($pType, ['headset', 'earphone']) && empty($mapped['earphone_pno']) => (function () use (&$mapped, $pBrand, $pModel, $pno) {
                        $mapped['earphone_brand'] = $pBrand;
                        $mapped['earphone_model'] = $pModel;
                        $mapped['earphone_pno']   = $pno;
                    })(),
                    default => null,
                };
            }
            
            if (str_contains($typeStr, 'desktop')) {
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
            } elseif (str_contains($typeStr, 'laptop')) {
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
            } elseif (str_contains($typeStr, 'monitor')) {
                $monitors++;
                if ($monitors <= 2) {
                    $mapped["monitor{$monitors}_brand"] = $asset->brand;
                    $mapped["monitor{$monitors}_model"] = $asset->model;
                    $mapped["monitor{$monitors}_pno"]   = $asset->property_number;
                }
            } elseif (str_contains($typeStr, 'printer') || str_contains($typeStr, 'scanner')) {
                if (str_contains($typeStr, 'scanner') && !str_contains($typeStr, 'printer')) {
                    $mapped['scanner_brand'] = $asset->brand;
                    $mapped['scanner_model'] = $asset->model;
                    $mapped['scanner_pno']   = $asset->property_number;
                } else {
                    $printers++;
                    if ($printers <= 2) {
                        $mapped["printer{$printers}_brand"] = $asset->brand;
                        $mapped["printer{$printers}_model"] = $asset->model;
                        $mapped["printer{$printers}_pno"]   = $asset->property_number;
                    }
                }
            } elseif (str_contains($typeStr, 'ups') || str_contains($typeStr, 'uninterruptible')) {
                $mapped['ups_brand'] = $asset->brand;
                $mapped['ups_model'] = $asset->model;
                $mapped['ups_pno']   = $asset->property_number;
            } elseif (str_contains($typeStr, 'webcam') || str_contains($typeStr, 'camera')) {
                $mapped['webcam_brand'] = $asset->brand;
                $mapped['webcam_model'] = $asset->model;
                $mapped['webcam_pno']   = $asset->property_number;
            } elseif (str_contains($typeStr, 'speaker')) {
                $mapped['speakers_brand'] = $asset->brand;
                $mapped['speakers_model'] = $asset->model;
                $mapped['speakers_pno']   = $asset->property_number;
            } elseif (str_contains($typeStr, 'earphone') || str_contains($typeStr, 'headset')) {
                $mapped['earphone_brand'] = $asset->brand;
                $mapped['earphone_model'] = $asset->model;
                $mapped['earphone_pno']   = $asset->property_number;
            } elseif (str_contains($typeStr, 'network') || str_contains($typeStr, 'server') || str_contains($typeStr, 'ip phone')) {
                // Map IP Phone / Network equipment to "Other Equipment" section
                $brand = $asset->brand ?: $asset->model; // Use model as brand if brand is empty
                $mapped['other_equipment'] = 'IP Phone';
                $mapped['other_equipment_brand'] = $brand;
                $mapped['other_equipment_model_pno'] = $asset->par_number ?: $asset->property_number;
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

    private function generateRequestNumber(?User $actorUser = null): string
    {
        return \App\Support\RequestHelpers::generateRequestNumber('PM', $actorUser);
    }
    
    private function getBranchCode(?string $branch): string
    {
        return \App\Support\RequestHelpers::getBranchCode($branch);
    }
}