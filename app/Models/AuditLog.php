<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'module',
        'details',
        'region',
        'ip_address',
        'user_agent'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Helper to quickly log an action
     * 
     * @param string $action Action performed (e.g., 'Update Status', 'Create User')
     * @param string $module Module affected (e.g., 'Inventory', 'Requests', 'Personnel')
     * @param string|null $details Detailed description of what changed
     * @param string|null $region Region/branch/office affected (stored in 'region' column)
     */
    public static function log($action, $module, $details = null, $region = null)
    {
        return self::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'module' => $module,
            'details' => $details,
            'region' => $region ?? (auth()->user()->region ?? auth()->user()->branch ?? 'N/A'),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
