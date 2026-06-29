<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PMScheduleHistory extends Model
{
    protected $table = 'pm_schedule_history';

    public $timestamps = false;

    protected $fillable = [
        'pm_schedule_id',
        'action',
        'generated_count',
        'generated_at',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(PMSchedule::class, 'pm_schedule_id');
    }
}
