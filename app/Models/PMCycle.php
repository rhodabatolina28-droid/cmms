<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PMCycle extends Model
{
    protected $table = 'pm_cycles';

    protected $fillable = [
        'pm_schedule_id',
        'cycle_number',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(PMSchedule::class, 'pm_schedule_id');
    }

    public function divisionSchedules(): HasMany
    {
        return $this->hasMany(PMDivisionSchedule::class, 'pm_cycle_id');
    }

    public function isComplete(): bool
    {
        return $this->completed_at !== null;
    }
}
