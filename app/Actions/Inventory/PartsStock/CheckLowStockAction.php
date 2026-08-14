<?php

namespace App\Actions\Inventory\PartsStock;

use App\Models\Notification;
use App\Models\Part;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class CheckLowStockAction
{
    /**
     * Combined low-stock alerting (event-based).
     *
     * **Hindi per-item** — i-grupo ang lahat ng bagong LOW + CRITICAL na item ayon sa
     * **location** (region/branch) at magpadala ng **ISANG summary notification** bawat
     * supply staff na naglilista ng lahat ng item. Kung isa lang ang item, isa pa ring
     * notification na may 1 laman lang.
     *
     * Dedupe: i-flag ang item (`low_notified_at`/`critical_notified_at`) kapag naipadala na,
     * para hindi lumabas ulit hanggang bumalik sa healthy (self-heal).
     *
     * @return array{notified:int, low:int, critical:int}
     */
    public function execute(bool $dryRun = false): array
    {
        $now = now();

        $low = Part::query()
            ->where('is_active', true)
            ->where('reorder_level', '>', 0)
            ->whereColumn('on_hand_qty', '<', 'reorder_level')
            ->where('on_hand_qty', '>', 0)
            ->whereNull('low_notified_at')
            ->get();

        $critical = Part::query()
            ->where('is_active', true)
            ->where('on_hand_qty', '<=', 0)
            ->whereNull('critical_notified_at')
            ->get();

        $items = collect();
        foreach ($low as $p) {
            $items->push(['part' => $p, 'level' => 'Low stock']);
        }
        foreach ($critical as $p) {
            $items->push(['part' => $p, 'level' => 'Critical']);
        }

        $groups = $items->groupBy(fn ($row) => ($row['part']->region ?? '') . '|' . ($row['part']->branch ?? ''));

        $notified = 0;
        $flags = []; // part_id => [column => timestamp]

        foreach ($groups as $key => $rows) {
            [$region, $branch] = explode('|', $key, 2);
            $users = $this->supplyUsers($region ?: null, $branch ?: null);
            if ($users->isEmpty()) {
                continue;
            }

            $notified += $users->count(); // would-be (o aktwal) na bilang ng tatanggap

            if (! $dryRun) {
                $message = $this->buildSummary($rows, $region ?: null, $branch ?: null);
                foreach ($users as $user) {
                    Notification::send($user->id, null, 'Parts Low Stock Alert', $message);
                }
                foreach ($rows as $row) {
                    $col = $row['level'] === 'Critical' ? 'critical_notified_at' : 'low_notified_at';
                    $flags[$row['part']->id][$col] = $now;
                }
            }
        }

        if (! $dryRun) {
            foreach ($flags as $id => $cols) {
                Part::whereKey($id)->update($cols);
            }
            $this->resetRecoveredFlags();
        }

        return [
            'notified' => $notified,
            'low' => $low->count(),
            'critical' => $critical->count(),
        ];
    }

    protected function buildSummary($rows, ?string $region, ?string $branch): string
    {
        $location = trim(implode(' · ', array_filter([$region, $branch]))) ?: 'All locations';
        $lines = [];
        foreach ($rows as $i => $row) {
            $p = $row['part'];
            if ($row['level'] === 'Critical') {
                $lines[] = ($i + 1) . ". {$p->item_name} — on-hand {$p->on_hand_qty} (CRITICAL / no stock)";
            } else {
                $lines[] = ($i + 1) . ". {$p->item_name} — on-hand {$p->on_hand_qty} / reorder {$p->reorder_level} (LOW)";
            }
        }

        return "Low / Critical stock alert — {$location}:\n"
            . implode("\n", $lines)
            . "\n\nMangyaring gumawa ng Purchase Request para sa mga kinakailangang item.";
    }

    protected function supplyUsers(?string $region, ?string $branch)
    {
        return User::query()
            ->where(function ($q) {
                $q->where('role', 'supply_officer')
                    ->orWhere(function ($q2) {
                        $q2->where('role', 'admin')->where('can_supply', true);
                    });
            })
            ->where('is_active', true)
            ->when($region, fn ($q) => $q->where('region', $region))
            ->when($branch, fn ($q) => $q->where('branch', $branch))
            ->get();
    }

    protected function resetRecoveredFlags(): void
    {
        Part::query()->where('is_active', true)
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->where('reorder_level', 0)->where('on_hand_qty', '>', 0);
                })->orWhere(function ($q2) {
                    $q2->where('reorder_level', '>', 0)->whereColumn('on_hand_qty', '>=', 'reorder_level');
                });
            })
            ->whereNotNull('low_notified_at')
            ->update(['low_notified_at' => null]);

        Part::query()->where('is_active', true)
            ->where('on_hand_qty', '>', 0)
            ->whereNotNull('critical_notified_at')
            ->update(['critical_notified_at' => null]);
    }
}