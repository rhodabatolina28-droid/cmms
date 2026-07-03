<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PMDivisionSchedule extends Model
{
    protected $table = 'pm_division_schedules';

    protected $fillable = [
        'pm_cycle_id',
        'pm_schedule_id',
        'division_name',
        'last_completed_at',
        'next_scheduled_at',
    ];

    protected $casts = [
        'last_completed_at' => 'date',
        'next_scheduled_at' => 'date',
    ];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(PMCycle::class, 'pm_cycle_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(PMSchedule::class, 'pm_schedule_id');
    }

    /**
     * Compute and save next_scheduled_at based on the frequency of the parent schedule.
     */
    public function computeNextDate(): void
    {
        $freqMonths = match ($this->schedule->frequency) {
            'Monthly'     => 1,
            'Quarterly'   => 3,
            'Semi-annual' => 6,
            'Annual'      => 12,
            default       => 6,
        };

        $this->next_scheduled_at = \Carbon\Carbon::parse($this->last_completed_at)
            ->addMonths($freqMonths)
            ->toDateString();
        $this->save();
    }

    /**
     * Check if this division is overdue for its next PM.
     */
    public function isDue(): bool
    {
        if (!$this->next_scheduled_at) return false;
        return $this->next_scheduled_at->lte(now());
    }
}
