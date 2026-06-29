<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PMSchedule extends Model
{
    use SoftDeletes;

    protected $table = 'pm_schedules';

    protected $fillable = [
        'schedule_name',
        'asset_categories',
        'division_filter',
        'frequency',
        'last_generated_date',
        'next_scheduled_date',
        'is_active',
        'is_paused',
        'paused_at',
        'cycle_stopped_at',
        'current_focus_division',
        'created_by',
    ];

    protected $attributes = [
        'is_active' => true,
        'asset_categories' => '[]',
    ];

    protected $casts = [
        'asset_categories' => 'array',
        'is_active' => 'boolean',
        'is_paused' => 'boolean',
        'paused_at' => 'datetime',
        'cycle_stopped_at' => 'datetime',
        'last_generated_date' => 'date',
        'next_scheduled_date' => 'date',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function history(): HasMany
    {
        return $this->hasMany(PMScheduleHistory::class, 'pm_schedule_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function calculateNextDate(?string $fromDate = null): string
    {
        // Use provided date, or now() — NOT last_generated_date.
        // Each division completes at a different time, so next due = completion date + frequency.
        $base = $fromDate ?? now()->toDateString();
        $freqMonths = match ($this->frequency) {
            'Monthly'     => 1,
            'Quarterly'   => 3,
            'Semi-annual' => 6,
            'Annual'      => 12,
            default       => 1,
        };
        return \Carbon\Carbon::parse($base)->addMonths($freqMonths)->toDateString();
    }
}
