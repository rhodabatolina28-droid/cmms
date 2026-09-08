<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $appends = ['is_high_official'];

    protected $fillable = [
        'full_name',
        'email',
        'password',
        'role',
        'position',
        'region',
        'branch',
        'office',
        'department',
        'can_supply',
        'is_active'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'can_supply' => 'boolean',
    ];

    // Relationships
    public function requests()
    {
        return $this->hasMany(Request::class);
    }

    /**
     * Oldest completed ICT request that still needs a CSM survey.
     * PM requests are excluded — CSM is only for ICT support tickets.
     */
    public function pendingSurveyRequest(): ?\App\Models\Request
    {
        return $this->requests()
            ->where('type', 'ICT')
            ->where('status', \App\Models\Request::STATUS_COMPLETED)
            ->whereDoesntHave('csmSurvey')
            ->orderBy('updated_at')
            ->first();
    }

    public function assets()
    {
        return $this->hasMany(InventoryAsset::class, 'assigned_to_user');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }



    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    public function scopeByOffice($query, $office)
    {
        return $query->where('office', $office);
    }

    public function scopeByDepartment($query, $department)
    {
        return $query->where('department', $department);
    }

    // Helpers
    public function isAdmin()
    {
        return $this->role === 'admin' || $this->role === 'supply_officer';
    }

    /**
     * D4a — High Official detection (NCMB).
     * Case-insensitive keyword match against users.position using the list in
     * config/priority.php. Full-phrase keywords only ("executive director",
     * "director ii", "chief", "state auditor") so titles like
     * "Director's Secretary" or "Programmer" can never match.
     * Empty/null position (54 of 58 live users today) is never an official.
     */
    public function getIsHighOfficialAttribute(): bool
    {
        $position = trim((string) ($this->position ?? ''));
        if ($position === '') {
            return false;
        }

        foreach (config('priority.high_official_keywords', []) as $keyword) {
            if (str_contains(strtolower($position), strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }

    /**
     * D4a companion scope — query all high officials at once (used later by
     * the D4b queue-jump on the IT Dashboard / ICT ticket lists).
     */
    public function scopeHighOfficials($query)
    {
        $keywords = config('priority.high_official_keywords', []);
        return $query->where(function ($q) use ($keywords) {
            foreach ($keywords as $keyword) {
                $q->orWhere('position', 'like', '%' . $keyword . '%');
            }
        });
    }

    public function isSuperAdmin()
    {
        return $this->role === 'super_admin';
    }

    public function isUser()
    {
        return $this->role === 'user';
    }

    public function isIt(): bool
    {
        return $this->role === 'it';
    }

    public function isDivisionAdmin(): bool
    {
        return $this->isAdmin();
    }

    public function isSupplyOfficer(): bool
    {
        return $this->role === 'supply_officer';
    }

    public function canProcessSupply(): bool
    {
        // Supply admin must be:
        // 1. supply_officer role (automatic - they handle supply)
        // 2. OR admin role with can_supply flag
        return $this->isSupplyOfficer() || ($this->isAdmin() && $this->can_supply);
    }

    /** Named route for role dashboard (e.g. dashboard.admin). */
    public function dashboardRouteName(): string
    {
        return match ($this->role) {
            'admin' => 'dashboard.admin',
            'supply_officer' => 'dashboard.admin',
            'super_admin' => 'dashboard.super-admin',
            'it' => 'dashboard.it',
            default => 'dashboard.user',
        };
    }

    public function dashboardPath(): string
    {
        return match ($this->role) {
            'admin' => '/dashboard/admin',
            'supply_officer' => '/dashboard/admin',
            'super_admin' => '/dashboard/super-admin',
            'it' => '/dashboard/it',
            default => '/dashboard/user',
        };
    }

    public static function assignableRoles(): array
    {
        return ['user', 'admin', 'supply_officer', 'super_admin', 'it'];
    }
}
