<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\CarbonInterface;

class PMGenerationSchedule extends Model
{
    protected $table = 'pm_generation_schedules';

    public const STATUS_PENDING    = 'Pending';
    public const STATUS_PROCESSING = 'Processing';
    public const STATUS_GENERATED  = 'Generated';
    public const STATUS_CANCELLED  = 'Cancelled';
    public const STATUS_FAILED     = 'Failed';

    protected $fillable = [
        'pm_schedule_id',
        'scheduled_date',
        'generated_at',
        'generated_by',
        'status',
        'remarks',
        'estimated_asset_count',
        'generated_count',
        'division_filter_snapshot',
        'generated_division',
        'pm_cycle_id',
        'failure_message',
    ];

    protected $casts = [
        'scheduled_date'        => 'date',
        'generated_at'           => 'datetime',
        'estimated_asset_count'  => 'integer',
        'generated_count'        => 'integer',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    // Relationships

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(PMSchedule::class, 'pm_schedule_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(PMCycle::class, 'pm_cycle_id');
    }

    // Query Scopes

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeDueOnOrBefore($query, CarbonInterface $date)
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where('scheduled_date', '<=', $date->toDateString());
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where('scheduled_date', '<', now()->toDateString());
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where('scheduled_date', '>=', now()->toDateString());
    }

    // Helpers

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isGenerated(): bool
    {
        return $this->status === self::STATUS_GENERATED;
    }

    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->scheduled_date < now()->toDateString();
    }

    public function getCalendarStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_CANCELLED  => 'Cancelled',
            self::STATUS_PENDING    => $this->isOverdue() ? 'Overdue' : 'Pending',
            self::STATUS_PROCESSING => 'Scheduled',
            self::STATUS_GENERATED  => 'Completed',
            self::STATUS_FAILED     => 'Overdue',
            default                 => 'Pending',
        };
    }
}