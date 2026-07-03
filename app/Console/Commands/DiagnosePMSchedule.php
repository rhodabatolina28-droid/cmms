<?php

namespace App\Console\Commands;

use App\Models\PMSchedule;
use App\Models\InventoryAsset;
use App\Models\Request as RequestModel;
use Illuminate\Console\Command;

class DiagnosePMSchedule extends Command
{
    protected $signature = 'pm:diagnose {id : The PM schedule ID}';
    protected $description = '[TESTING] Diagnose why a PM schedule generated 0 requests';

    public function handle(): int
    {
        $schedule = PMSchedule::find($this->argument('id'));

        if (!$schedule) {
            $this->error('Schedule not found.');
            return Command::FAILURE;
        }

        $this->info("=== PM Schedule: {$schedule->schedule_name} ===");
        $this->table(['Field', 'Value'], [
            ['asset_categories', json_encode($schedule->asset_categories)],
            ['division_filter', $schedule->division_filter ?? '(none)'],
            ['frequency', $schedule->frequency],
            ['current_focus_division', $schedule->current_focus_division ?? 'null'],
            ['next_scheduled_date', $schedule->next_scheduled_date?->toDateString()],
        ]);

        // Step 1: All active assigned assets
        $query = InventoryAsset::where('status', 'Active')->whereNotNull('assigned_to_user');
        $allAssets = $query->get();
        $this->newLine();
        $this->info("Step 1 — Total Active + Assigned assets in DB: {$allAssets->count()}");

        // Step 2: Filter by category
        if (!empty($schedule->asset_categories)) {
            $catAssets = InventoryAsset::where('status', 'Active')
                ->whereNotNull('assigned_to_user')
                ->whereIn('category', $schedule->asset_categories)
                ->get();
            $this->info("Step 2 — After category filter (" . implode(', ', $schedule->asset_categories) . "): {$catAssets->count()}");

            $this->newLine();
            $this->info("Distinct categories in DB:");
            $cats = InventoryAsset::where('status', 'Active')->whereNotNull('assigned_to_user')
                ->distinct()->pluck('category')->sort()->values();
            foreach ($cats as $c) {
                $this->line("  - \"{$c}\"");
            }
        } else {
            $this->info("Step 2 — No category filter on this schedule.");
            $catAssets = $allAssets;
        }

        // Step 3: Already completed in frequency window
        $freqMonths = match($schedule->frequency) {
            'Monthly' => 1, 'Quarterly' => 3, 'Semi-annual' => 6, 'Annual' => 12, default => 3
        };
        $windowStart = now()->subMonths($freqMonths)->toDateTimeString();
        $completedIds = RequestModel::where('pm_schedule_id', $schedule->id)
            ->where('is_auto_generated', true)
            ->where('status', 'Completed')
            ->where('created_at', '>=', $windowStart)
            ->pluck('user_id')
            ->toArray();
        $this->newLine();
        $this->info("Step 3 — Users already completed PM in last {$freqMonths} month(s): " . count($completedIds));

        // Step 4: Show asset users
        $this->newLine();
        $this->info("Step 4 — Assets after category filter:");
        if ($catAssets->isEmpty()) {
            $this->warn("  No matching assets found. Check category spelling.");
        } else {
            $this->table(
                ['Asset ID', 'Property No', 'Category', 'Assigned User', 'Branch', 'Status'],
                $catAssets->map(fn($a) => [
                    $a->id,
                    $a->property_number,
                    $a->category,
                    $a->assigned_to_user,
                    $a->branch ?? '(null)',
                    $a->status,
                ])->toArray()
            );
        }

        return Command::SUCCESS;
    }
}
