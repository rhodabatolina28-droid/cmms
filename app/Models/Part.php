<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A parts/consumable stock item tracked by quantity + unit (no serial).
 * Table: parts_stock
 */
class Part extends Model
{
    use HasFactory;

    protected $table = 'parts_stock';

    protected $fillable = [
        'item_name',
        'unit',
        'category',
        'on_hand_qty',
        'reorder_level',
        'region',
        'branch',
        'is_active',
    ];

    protected $casts = [
        'on_hand_qty' => 'integer',
        'reorder_level' => 'integer',
        'is_active' => 'boolean',
    ];

    // ---- Relationships -------------------------------------------------

    public function movements(): HasMany
    {
        return $this->hasMany(PartMovement::class, 'part_id');
    }

    // ---- Scopes --------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeRegion($query, $region)
    {
        return $query->where('region', $region);
    }

    public function scopeBranch($query, $branch)
    {
        return $query->where('branch', $branch);
    }

    // ---- Helpers -------------------------------------------------------

    /**
     * Stock health label used for status badges.
     *
     * @return string 'ok' | 'low' | 'critical'
     */
    public function statusLevel(): string
    {
        if ($this->on_hand_qty <= 0) {
            return 'critical';
        }

        if ($this->reorder_level > 0 && $this->on_hand_qty < $this->reorder_level) {
            return 'low';
        }

        return 'ok';
    }
}