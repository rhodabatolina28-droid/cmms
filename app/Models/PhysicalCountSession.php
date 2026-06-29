<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhysicalCountSession extends Model
{
    protected $fillable = [
        'started_by',
        'started_at',
        'completed_at',
        'status',
        'scope_region',
        'scope_branch',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function startedBy()
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function counts()
    {
        return $this->hasMany(PhysicalCount::class, 'session_id');
    }
}
