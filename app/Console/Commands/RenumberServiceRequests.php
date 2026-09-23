<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Request as Ticket;
use App\Support\RequestHelpers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * D9.42 Phase 2 — backfill legacy service request numbers to the per-region
 * daily format: {ICT|PM}-{REGION}-{BRANCH}-{YYYY}-{MM}-{DD}-{NNNN}.
 *
 *   legacy   REQ-NCR-RCMB-2026-0028  ->  ICT-NCR-RCMB-2026-08-20-0001
 *   D9.24    REQ-2026-09-17-0001     ->  ICT-NCR-RCMB-2026-09-17-0001
 *   old PM   PM-NCR-RCMB-2026-0007   ->  PM-NCR-RCMB-2026-09-01-0001
 *
 * Rules (docs/asset-downtime-tracking.md — D9.42 Phase 2):
 *  - The sequence restarts every day and is scoped per (prefix, region, branch),
 *    exactly like RequestHelpers::generateRequestNumber() does for new rows.
 *  - Rows are ordered by created_at, then id, so the assignment is stable and a
 *    second run changes NOTHING (idempotent by construction).
 *  - Mirrors are renumbered in the same transaction, but ONLY when they still
 *    mirror the old number: repair_requests.service_request_no,
 *    preventive_maintenance.form_no / service_request_no. Foreign values are
 *    left alone and reported.
 *  - A number that already belongs to a row OUTSIDE this run is never
 *    overwritten. That row is reported as a collision and the WHOLE write is
 *    aborted (all-or-nothing, exit code 1), because three of the four number
 *    columns are UNIQUE (requests.request_number,
 *    repair_requests.service_request_no, preventive_maintenance.form_no) and a
 *    partial run would leave the numbering inconsistent.
 *  - Soft-deleted rows (deleted_at) are included — they still hold numbers.
 *  - Rows with no detail_id are renumbered like any other row by default; pass
 *    --skip-orphans to leave temporary/placeholder tickets on their old number.
 *  - DRY RUN BY DEFAULT: nothing is written without --force.
 *
 * Run it while traffic is low (or inside the deploy window): the live generator
 * does not share its advisory lock with this command.
 */
class RenumberServiceRequests extends Command
{
    protected $signature = 'requests:renumber
        {--region= : Limit to one region — code or name (e.g. NCR, "REGION II")}
        {--branch= : Limit to one branch — code or name}
        {--dry-run : Report the plan and write nothing (this is the default)}
        {--skip-orphans : Leave ICT/PM rows with no detail_id alone (temporary/placeholder rows)}
        {--force : Actually write the new numbers}';

    protected $description = 'D9.42 Phase 2 — backfill legacy request numbers to {ICT|PM}-{REGION}-{BRANCH}-{date}-{NNNN} (dry-run by default)';

    /**
     * Plan entries keyed by request id: row, new, status
     * (ready|unchanged|collision|unsupported|no-created-at), note, the captured
     * mirror values and the mirror_*_write flags.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $plan = [];

    /** @var array<string, int> target number => request id that will claim it */
    private array $targetOwners = [];

    public function handle(): int
    {
        // --dry-run always wins, even when --force is also passed.
        $write = (bool) $this->option('force') && !(bool) $this->option('dry-run');

        $rows = $this->loadRows();

        if ($rows->isEmpty()) {
            $this->warn('No request rows matched the given filters — nothing to do.');
            return self::SUCCESS;
        }

        $this->info(($write ? 'RENUMBER — write mode' : 'DRY RUN — nothing will be written')
            . ': ' . $rows->count() . ' row(s) in scope' . $this->filterLabel() . '.');

        $this->buildPlan($rows);
        $this->detectCollisions();
        $this->renderPlan();

        $ready = $this->withStatus('ready');
        $collisions = $this->withStatus('collision');

        $renumbered = 0;
        $mirrors = 0;

        // All-or-nothing: a collision means the plan disagrees with existing
        // data (wrong scope?), so writing part of it would only make it worse.
        if ($collisions !== []) {
            $this->newLine();
            $this->error('Collisions found — WRITE ABORTED, no rows were changed. Resolve them, then re-run.');
        } elseif ($write && $ready !== []) {
            try {
                [$renumbered, $mirrors] = DB::transaction(fn () => $this->apply());
            } catch (RuntimeException $e) {
                $this->newLine();
                $this->error($e->getMessage());
                return self::FAILURE;
            }
        } elseif ($write) {
            $this->line('  (nothing to write — every row is already in the new format)');
        }

        $this->newLine();
        $this->info(sprintf(
            '%s Scanned: %d | to renumber: %d | already correct: %d | collisions: %d | skipped: %d',
            $write ? 'WRITE done.' : 'DRY RUN.',
            count($this->plan),
            count($ready),
            count($this->withStatus('unchanged')),
            count($collisions),
            count($this->withStatus('unsupported')) + count($this->withStatus('no-created-at')) + count($this->withStatus('orphan'))
        ));

        if ($write) {
            $this->info("Written: {$renumbered} request number(s), {$mirrors} mirror value(s).");
        }

        return $collisions === [] ? self::SUCCESS : self::FAILURE;
    }

    /** @return array<int, array<string, mixed>> */
    private function withStatus(string $status): array
    {
        return array_filter($this->plan, fn (array $e) => $e['status'] === $status);
    }

    /**
     * Every request row (soft-deleted included) in a stable order, narrowed by
     * the optional region/branch filters.
     */
    private function loadRows()
    {
        $region = $this->option('region');
        $branch = $this->option('branch');

        return Ticket::withTrashed()
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'request_number', 'type', 'region', 'branch', 'created_at', 'detail_id'])
            ->filter(fn ($row) => $this->matches($row->region, $region) && $this->matches($row->branch, $branch))
            ->values();
    }

    /**
     * Accepts either the short code or the full name: --region=NCR also matches
     * a column holding 'NATIONAL CAPITAL REGION' — the same getBranchCode()
     * rule the generator and the display accessors use.
     */
    private function matches(?string $value, ?string $filter): bool
    {
        $filter = trim((string) $filter);
        if ($filter === '') {
            return true;
        }

        if (strtoupper(trim((string) $value)) === strtoupper($filter)) {
            return true;
        }

        return RequestHelpers::getBranchCode($value) === RequestHelpers::getBranchCode($filter);
    }

    private function filterLabel(): string
    {
        $parts = [];
        if (trim((string) $this->option('region')) !== '') {
            $parts[] = 'region=' . $this->option('region');
        }
        if (trim((string) $this->option('branch')) !== '') {
            $parts[] = 'branch=' . $this->option('branch');
        }

        return $parts === [] ? ' (all regions/branches)' : ' (' . implode(', ', $parts) . ')';
    }

    /**
     * 'ICT' / 'PM' for the types this system numbers. requests.type is an ENUM
     * of exactly those two values today, so null is unreachable — the guard is
     * here so a future request type cannot silently join the ICT series.
     */
    private function prefixFor(?string $type): ?string
    {
        if ($type === 'ICT') {
            return 'ICT';
        }

        if ($type === 'Preventive Maintenance') {
            return 'PM';
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function planEntry(Ticket $row, ?string $new, string $status, string $note): array
    {
        return [
            'row' => $row,
            'new' => $new,
            'status' => $status,
            'note' => $note,
            'mirror_repair' => null,
            'mirror_form' => null,
            'mirror_sr' => null,
            'mirror_repair_write' => false,
            'mirror_form_write' => false,
            'mirror_sr_write' => false,
        ];
    }

    /**
     * Group the rows exactly the way the generator does and lay out the targets.
     * Nothing is written here.
     *
     * @param  \Illuminate\Support\Collection<int, Ticket>  $rows
     */
    private function buildPlan($rows): void
    {
        /** @var array<string, array<int, array<string, mixed>>> $groups */
        $groups = [];

        foreach ($rows as $row) {
            $prefix = $this->prefixFor($row->type);

            if ($prefix === null) {
                $this->plan[$row->id] = $this->planEntry($row, null, 'unsupported', "request type '{$row->type}' is not ICT/PM");
                continue;
            }

            if (!$row->created_at) {
                $this->plan[$row->id] = $this->planEntry($row, null, 'no-created-at', 'row has no created_at, cannot derive the day');
                continue;
            }

            // ICT/PM tickets are always created with a service-request detail
            // row. A row without detail_id is a temporary/placeholder ticket, so
            // renumbering it would burn a sequence slot on that day for nothing.
            if ($this->option('skip-orphans') && !$row->detail_id) {
                $this->plan[$row->id] = $this->planEntry($row, null, 'orphan', 'no detail_id (temporary placeholder row)');
                continue;
            }

            $region = RequestHelpers::getBranchCode($row->region);
            $branch = RequestHelpers::getBranchCode($row->branch);
            $date = $row->created_at->format('Y-m-d');

            $groups["{$prefix}|{$region}|{$branch}|{$date}"][] = [
                'row' => $row,
                'prefix' => $prefix,
                'region' => $region,
                'branch' => $branch,
                'date' => $date,
            ];
        }

        foreach ($groups as $entries) {
            foreach ($entries as $index => $entry) {
                $new = sprintf(
                    '%s-%s-%s-%s-%04d',
                    $entry['prefix'],
                    $entry['region'],
                    $entry['branch'],
                    $entry['date'],
                    $index + 1
                );

                $status = $new === $entry['row']->request_number ? 'unchanged' : 'ready';

                $this->plan[$entry['row']->id] = $this->planEntry($entry['row'], $new, $status, '');
                $this->targetOwners[$new] = $entry['row']->id;
            }
        }

        $this->loadMirrors();
    }

    /**
     * Pull the mirror columns for every planned row — one query per table — and
     * flag which of them still hold the old number (so they get renumbered too).
     *
     * detail_id is type-scoped: ICT rows point into repair_requests, PM rows
     * into preventive_maintenance. The ids overlap, so the lookup MUST follow
     * the request type — never both tables.
     */
    private function loadMirrors(): void
    {
        $repairIds = [];
        $pmIds = [];

        foreach ($this->plan as $entry) {
            $detailId = $entry['row']->detail_id ? (int) $entry['row']->detail_id : 0;
            if ($detailId === 0) {
                continue;
            }

            if ($entry['row']->type === 'ICT') {
                $repairIds[$detailId] = true;
            } elseif ($entry['row']->type === 'Preventive Maintenance') {
                $pmIds[$detailId] = true;
            }
        }

        $repair = $repairIds === []
            ? collect()
            : DB::table('repair_requests')->whereIn('id', array_keys($repairIds))
                ->get(['id', 'service_request_no'])->keyBy('id');

        $pm = $pmIds === []
            ? collect()
            : DB::table('preventive_maintenance')->whereIn('id', array_keys($pmIds))
                ->get(['id', 'form_no', 'service_request_no'])->keyBy('id');

        foreach ($this->plan as $id => $entry) {
            $old = (string) $entry['row']->request_number;
            $detailId = $entry['row']->detail_id ? (int) $entry['row']->detail_id : null;

            if ($detailId && $entry['row']->type === 'ICT' && isset($repair[$detailId])) {
                $value = (string) $repair[$detailId]->service_request_no;
                $entry['mirror_repair'] = $value;
                $entry['mirror_repair_write'] = $value !== '' && $value === $old;
            }

            if ($detailId && $entry['row']->type === 'Preventive Maintenance' && isset($pm[$detailId])) {
                $form = (string) $pm[$detailId]->form_no;
                $sr = (string) $pm[$detailId]->service_request_no;

                $entry['mirror_form'] = $form;
                $entry['mirror_sr'] = $sr;
                $entry['mirror_form_write'] = $form !== '' && $form === $old;
                $entry['mirror_sr_write'] = $sr !== '' && $sr === $old;
            }

            $this->plan[$id] = $entry;
        }
    }

    /**
     * Flag plans whose target number is already taken by a request row that this
     * run will NOT renumber — a filtered-out row, or a row whose created_at day
     * produces the same number (explicit created_at drift).
     */
    private function detectCollisions(): void
    {
        $targets = array_keys($this->targetOwners);
        if ($targets === []) {
            return;
        }

        $existing = Ticket::withTrashed()
            ->whereIn('request_number', $targets)
            ->get(['id', 'request_number'])
            ->keyBy('request_number');

        foreach ($this->plan as $id => $entry) {
            if ($entry['status'] !== 'ready') {
                continue;
            }

            $holder = $existing->get($entry['new']);
            if (!$holder || (int) $holder->id === (int) $id) {
                continue;
            }

            $entry['status'] = 'collision';
            $entry['note'] = "target {$entry['new']} is already held by request #{$holder->id} (not in scope: filtered out or on another created_at day)";

            $this->plan[$id] = $entry;
        }
    }

    /** One line per row plus the mirror summary underneath it. */
    private function renderPlan(): void
    {
        foreach ($this->plan as $entry) {
            $row = $entry['row'];
            $label = sprintf('#%-5s %-24s', $row->id, (string) $row->request_number);

            $line = match ($entry['status']) {
                'ready' => sprintf('  %s -> %-30s [RENUMBER]', $label, (string) $entry['new']),
                'unchanged' => sprintf('  %s    %-30s [OK already current]', $label, (string) $entry['new']),
                'collision' => sprintf('  %s !! %-30s [COLLISION] %s', $label, (string) $entry['new'], $entry['note']),
                default => sprintf('  %s    [SKIP] %s', $label, $entry['note']),
            };

            $this->line($line);
            $this->line('        ' . $this->mirrorNote($entry));
        }
    }

    /** Human-readable summary of what the mirrors will do for one row. */
    private function mirrorNote(array $entry): string
    {
        if (!$entry['row']->detail_id) {
            return 'mirrors: none (row has no detail_id)';
        }

        $parts = [];

        if ($entry['mirror_repair'] !== null) {
            $parts[] = 'repair_requests.service_request_no: '
                . ($entry['mirror_repair_write'] ? 'sync' : 'LEFT AS IS (' . $this->displayOrNull($entry['mirror_repair']) . ')');
        }

        if ($entry['mirror_form'] !== null) {
            $parts[] = 'preventive_maintenance.form_no: '
                . ($entry['mirror_form_write'] ? 'sync' : 'LEFT AS IS (' . $this->displayOrNull($entry['mirror_form']) . ')');

            $parts[] = 'preventive_maintenance.service_request_no: '
                . ($entry['mirror_sr_write'] ? 'sync' : 'LEFT AS IS (' . $this->displayOrNull($entry['mirror_sr']) . ')');
        }

        return 'mirrors: ' . ($parts === [] ? 'none linked' : implode(' | ', $parts));
    }

    private function displayOrNull(?string $value): string
    {
        return ($value === null || $value === '') ? 'empty' : $value;
    }

    /**
     * Write the plan. Runs inside a transaction, so a UNIQUE-index refusal rolls
     * the whole batch back instead of leaving half-renumbered rows behind.
     *
     * @return array{0: int, 1: int}  [renumbered requests, mirror values written]
     */
    private function apply(): array
    {
        $renumbered = 0;
        $mirrors = 0;
        $summary = [];

        foreach ($this->plan as $entry) {
            if ($entry['status'] !== 'ready') {
                continue;
            }

            $row = $entry['row'];
            $old = (string) $row->request_number;

            // Re-check right before the write: a live ticket may have taken the
            // number between the plan and now. Never overwrite another ticket.
            $taken = Ticket::withTrashed()
                ->where('request_number', $entry['new'])
                ->whereKeyNot($row->id)
                ->exists();

            if ($taken) {
                throw new RuntimeException(
                    "Aborted: {$entry['new']} was claimed by another request after the plan was built. "
                    . 'Re-run the command — no rows were written.'
                );
            }

            Ticket::withTrashed()->whereKey($row->id)->update(['request_number' => $entry['new']]);
            $renumbered++;

            if ($entry['row']->detail_id) {
                $mirrors += $this->applyMirrors((int) $row->detail_id, $entry, $old);
            }

            $summary[] = "#{$row->id} {$old} -> {$entry['new']}";
        }

        if (!$this->option('dry-run') && $renumbered > 0) {
            AuditLog::log(
                'Renumber Service Requests',
                'Requests',
                "D9.42 Phase 2 backfill: {$renumbered} request number(s) and {$mirrors} mirror value(s) "
                . 'moved to the per-region daily format' . $this->filterLabel() . '.',
                null
            );
        }

        foreach ($summary as $line) {
            $this->line("    [WRITTEN] {$line}");
        }

        return [$renumbered, $mirrors];
    }

    /** Sync the mirror columns that still held the old number. */
    private function applyMirrors(int $detailId, array $entry, string $old): int
    {
        $written = 0;

        if ($entry['mirror_repair_write']) {
            DB::table('repair_requests')->where('id', $detailId)
                ->update(['service_request_no' => $entry['new'], 'updated_at' => now()]);
            $written++;
        }

        if ($entry['mirror_form_write']) {
            DB::table('preventive_maintenance')->where('id', $detailId)
                ->update(['form_no' => $entry['new'], 'updated_at' => now()]);
            $written++;
        }

        if ($entry['mirror_sr_write']) {
            DB::table('preventive_maintenance')->where('id', $detailId)
                ->update(['service_request_no' => $entry['new'], 'updated_at' => now()]);
            $written++;
        }

        return $written;
    }
}
