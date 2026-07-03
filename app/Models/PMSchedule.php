<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PMSchedule extends Model
{
    use SoftDeletes;

    protected $table = 'pm_schedules';

    protected $fillable = [
        'schedule_name',
        'asset_categories',
        'frequency',
        'is_active',
        'is_paused',
        'paused_at',
        'cycle_stopped_at',
        'current_focus_division',
        'current_cycle_id',
        'cycle_count',
        'last_generated_date',
        'created_by',
    ];

    protected $attributes = [
        'is_active'        => true,
        'is_paused'        => false,
        'asset_categories' => '[]',
        'cycle_count'      => 0,
    ];

    protected $casts = [
        'asset_categories'   => 'array',
        'is_active'          => 'boolean',
        'is_paused'          => 'boolean',
        'paused_at'          => 'datetime',
        'cycle_stopped_at'   => 'datetime',
        'last_generated_date'=> 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function history(): HasMany
    {
        return $this->hasMany(PMScheduleHistory::class, 'pm_schedule_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(\App\Models\Request::class, 'pm_schedule_id');
    }

    /** All PM cycles ever run for this schedule */
    public function cycles(): HasMany
    {
        return $this->hasMany(PMCycle::class, 'pm_schedule_id');
    }

    /** The currently active (in-progress) cycle */
    public function currentCycle(): HasOne
    {
        return $this->hasOne(PMCycle::class, 'pm_schedule_id')
            ->whereNull('completed_at')
            ->latestOfMany();
    }

    /** Per-division records (all cycles) — used for history display */
    public function divisionSchedules(): HasMany
    {
        return $this->hasMany(PMDivisionSchedule::class, 'pm_schedule_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Calculate next scheduled date for a division based on frequency.
     */
    public function calculateNextDate(?string $fromDate = null): string
    {
        $base = $fromDate ?? now()->toDateString();
        $freqMonths = match ($this->frequency) {
            'Monthly'     => 1,
            'Quarterly'   => 3,
            'Semi-annual' => 6,
            'Annual'      => 12,
            default       => 6,
        };
        return \Carbon\Carbon::parse($base)->addMonths($freqMonths)->toDateString();
    }
}
