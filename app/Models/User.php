<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

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
        // 1. Admin or supply_officer role
        // 2. Has can_supply flag
        return ($this->isAdmin() || $this->isSupplyOfficer()) && $this->can_supply;
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
